<?php

namespace Tests\Feature\Assessment;

use App\Domain\Assessment\Week14BoardDefenseService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\BoardDefenseAssessment;
use App\Models\BoardDefenseAssessmentDimension;
use App\Models\BoardDefenseAssessmentFeedback;
use App\Models\BoardDefenseSubmission;
use App\Models\BoardDefenseSubmissionRevision;
use App\Models\Enrollment;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week14AssessmentWorkflowTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_student_can_submit_own_board_defense_and_submission_locks(): void
    {
        $context = $this->week14Context('OwnSubmission');

        $this->actingAs($context['graph']['student'])
            ->postJson(route('student.week14.defense.draft', $context['runtimeWeek']), [
                'final_synthesis_memo' => 'Draft strategic narrative.',
                'artifact_references' => [[
                    'type' => 'board_presentation',
                    'label' => 'Deck',
                    'reference' => 'https://example.test/deck',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('submission.status', BoardDefenseSubmission::STATUS_DRAFT);

        $this->actingAs($context['graph']['student'])
            ->postJson(route('student.week14.defense.submit', $context['runtimeWeek']), [
                'final_synthesis_memo' => 'Final synthesis memo with strategic coherence and counterfactual reflection.',
                'artifact_references' => [[
                    'type' => 'board_presentation',
                    'label' => 'Final deck',
                    'reference' => 'https://example.test/final-deck',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('submission.status', BoardDefenseSubmission::STATUS_SUBMITTED);

        $submission = BoardDefenseSubmission::query()->sole();
        $this->assertSame(BoardDefenseSubmission::STATUS_SUBMITTED, $submission->status);
        $this->assertSame(2, $submission->lock_version);
        $this->assertSame(2, BoardDefenseSubmissionRevision::query()->where('board_defense_submission_id', $submission->id)->count());

        $this->actingAs($context['graph']['student'])
            ->postJson(route('student.week14.defense.draft', $context['runtimeWeek']), [
                'final_synthesis_memo' => 'Trying to mutate a locked submission.',
            ])
            ->assertUnprocessable();
    }

    public function test_faculty_can_assess_assigned_team_and_publish_feedback_to_students(): void
    {
        $context = $this->week14Context('PublishFeedback');

        $this->submitDefense($context);

        $this->actingAs($context['graph']['faculty'])
            ->get(route('faculty.week14.assessment.show', [$context['runtimeWeek'], $context['teamSimulation']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Faculty/Week14Assessment')
                ->where('submission.status', BoardDefenseSubmission::STATUS_SUBMITTED)
                ->where('routes.save', route('faculty.week14.assessment.save', [$context['runtimeWeek'], $context['teamSimulation']]))
            );

        $this->actingAs($context['graph']['faculty'])
            ->postJson(route('faculty.week14.assessment.save', [$context['runtimeWeek'], $context['teamSimulation']]), [
                'dimensions' => [
                    'strategic_coherence' => [
                        'faculty_evaluation' => 'coherent',
                        'comments' => 'The Week 12 portfolio tied back to the earlier operating choices.',
                    ],
                    'decision_quality' => [
                        'faculty_evaluation' => 'sound',
                        'comments' => 'The team defended Week 8 and Week 10 using information available at the time.',
                    ],
                    'self_understanding' => [
                        'faculty_evaluation' => 'clear',
                        'comments' => 'The counterfactual named the Week 6 constraint honestly.',
                    ],
                ],
                'reasoning_outcome_tier' => 'strong_reasoning_weaker_outcomes',
                'faculty_private_notes' => 'Probe the Week 10 answer during debrief.',
                'feedback_body' => 'Published later: strong reasoning despite weaker outcomes.',
                'complete' => true,
            ])
            ->assertOk()
            ->assertJsonPath('assessment.status', BoardDefenseAssessment::STATUS_COMPLETED);

        $assessment = BoardDefenseAssessment::query()->with('feedback')->sole();
        $this->assertSame('strong_reasoning_weaker_outcomes', $assessment->reasoning_outcome_tier);
        $this->assertSame(3, BoardDefenseAssessmentDimension::query()->where('board_defense_assessment_id', $assessment->id)->count());
        $this->assertFalse($assessment->feedback->is_published);

        $this->actingAs($context['graph']['student'])
            ->getJson(route('student.week14.defense.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertJsonPath('assessment', null);

        $this->actingAs($context['graph']['faculty'])
            ->postJson(route('faculty.week14.assessment.publish', $assessment))
            ->assertOk();

        $this->assertTrue($assessment->refresh()->feedback->is_published);
        $this->assertInstanceOf(BoardDefenseAssessmentFeedback::class, $assessment->feedback);

        $this->actingAs($context['graph']['student'])
            ->getJson(route('student.week14.defense.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertJsonPath('assessment.reasoning_outcome_tier', 'strong_reasoning_weaker_outcomes')
            ->assertJsonPath('assessment.feedback.body', 'Published later: strong reasoning despite weaker outcomes.')
            ->assertJsonMissing(['faculty_private_notes' => 'Probe the Week 10 answer during debrief.']);
    }

    public function test_student_cannot_view_other_team_or_faculty_assessment(): void
    {
        $context = $this->week14Context('StudentIsolation', includeSecondTeam: true);

        app(Week14BoardDefenseService::class)->saveAssessment(
            $context['graph']['faculty'],
            $context['runtimeWeek'],
            $context['otherTeamSimulation'],
            ['strategic_coherence' => ['faculty_evaluation' => 'private']],
            null,
            'Private faculty note for another team.',
            'Other team feedback.',
        );

        $this->actingAs($context['graph']['student'])
            ->getJson(route('student.week14.defense.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertJsonPath('assessment', null)
            ->assertJsonMissing(['feedback_body' => 'Other team feedback.']);

        $this->actingAs($context['graph']['student'])
            ->getJson(route('faculty.week14.assessment.show', [$context['runtimeWeek'], $context['otherTeamSimulation']]))
            ->assertForbidden();
    }

    public function test_faculty_cannot_assess_outside_assigned_section_or_cross_tenant(): void
    {
        $context = $this->week14Context('AssignedSection');
        $other = $this->week14Context('OtherTenant');

        $this->actingAs($context['graph']['faculty'])
            ->postJson(route('faculty.week14.assessment.save', [$other['runtimeWeek'], $other['teamSimulation']]), [
                'dimensions' => [
                    'strategic_coherence' => ['faculty_evaluation' => 'not allowed'],
                ],
            ])
            ->assertForbidden();

        $unassigned = User::factory()->faculty()->create([
            'tenant_id' => $context['graph']['tenant']->id,
            'email' => 'unassigned-week14-'.$context['graph']['tenant']->id.'@example.test',
        ]);

        $this->actingAs($unassigned)
            ->postJson(route('faculty.week14.assessment.save', [$context['runtimeWeek'], $context['teamSimulation']]), [
                'dimensions' => [
                    'strategic_coherence' => ['faculty_evaluation' => 'not allowed'],
                ],
            ])
            ->assertForbidden();

        $this->actingAs($context['graph']['admin'])
            ->postJson(route('faculty.week14.assessment.save', [$context['runtimeWeek'], $context['teamSimulation']]), [
                'dimensions' => [
                    'strategic_coherence' => ['faculty_evaluation' => 'admin allowed'],
                ],
            ])
            ->assertOk();
    }

    public function test_week14_assessment_schema_does_not_create_automatic_grading_fields(): void
    {
        foreach ([
            'board_defense_assessments',
            'board_defense_assessment_dimensions',
            'board_defense_assessment_feedback',
        ] as $table) {
            $columns = Schema::getColumnListing($table);

            $this->assertNotContains('score', $columns);
            $this->assertNotContains('points', $columns);
            $this->assertNotContains('weight', $columns);
            $this->assertNotContains('grade', $columns);
            $this->assertNotContains('ranking_snapshot_id', $columns);
            $this->assertNotContains('composite_score', $columns);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function week14Context(string $suffix, bool $includeSecondTeam = false): array
    {
        $graph = $this->tenantGraph($suffix);

        $otherStudent = null;
        $otherTeam = null;
        if ($includeSecondTeam) {
            [$otherStudent, $otherTeam] = $this->addSecondTeam($graph, $suffix);
        }

        $structure = $this->simulationStructure(14);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        $runtimeWeek = $sectionSimulation->weeks()
            ->whereHas('definition', fn ($query) => $query->where('week_number', 14))
            ->firstOrFail();

        $service = app(SimulationLifecycleService::class);
        $service->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $service->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addWeek());

        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        $context = [
            'graph' => $graph,
            'structure' => $structure,
            'sectionSimulation' => $sectionSimulation,
            'runtimeWeek' => $runtimeWeek->refresh(),
            'teamSimulation' => $teamSimulation,
        ];

        if ($includeSecondTeam) {
            $context['otherStudent'] = $otherStudent;
            $context['otherTeam'] = $otherTeam;
            $context['otherTeamSimulation'] = $sectionSimulation->teamSimulations()
                ->where('team_id', $otherTeam->id)
                ->firstOrFail();
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array{0: User, 1: Team}
     */
    private function addSecondTeam(array $graph, string $suffix): array
    {
        $student = User::factory()->student()->create([
            'tenant_id' => $graph['tenant']->id,
            'email' => 'student-week14-second-'.$suffix.'@example.test',
        ]);

        Enrollment::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'user_id' => $student->id,
            'status' => 'active',
        ]);

        $team = Team::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'name' => 'Second Team '.$suffix,
            'slug' => 'second-team-'.strtolower($suffix),
        ]);

        TeamMember::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'team_id' => $team->id,
            'user_id' => $student->id,
        ]);

        return [$student, $team];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function submitDefense(array $context): void
    {
        $this->actingAs($context['graph']['student'])
            ->postJson(route('student.week14.defense.submit', $context['runtimeWeek']), [
                'final_synthesis_memo' => 'Final synthesis memo for faculty assessment.',
                'artifact_references' => [[
                    'type' => 'board_presentation',
                    'reference' => 'https://example.test/week14',
                ]],
            ])
            ->assertOk();
    }
}
