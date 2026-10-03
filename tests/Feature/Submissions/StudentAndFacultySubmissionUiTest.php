<?php

namespace Tests\Feature\Submissions;

use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Content\SimulationContentPackageService;
use App\Domain\Submissions\SubmissionService;
use App\Models\DecisionSubmission;
use App\Models\Enrollment;
use App\Models\MemoSubmission;
use App\Models\SimulationContentPackage;
use App\Models\SimulationWeek;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class StudentAndFacultySubmissionUiTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_student_can_view_authorized_submission_screen_and_other_student_cannot(): void
    {
        $graph = $this->tenantGraph('A');
        $other = $this->tenantGraph('B');
        $context = $this->openRuntimeWeekWithDefinitions($graph);
        $this->activateContentPackage($context['runtimeWeek']->definition);

        $this->actingAs($graph['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('week.title', 'Week 1')
                ->where('week.can_write', true)
                ->where('contentPackage.status', 'active')
                ->where('artifacts.0.key', 'student-brief')
                ->where('artifacts.0.label', 'Student Brief')
                ->where('artifacts.1.key', 'shared-guide')
                ->where('artifacts.1.label', 'Shared Guide')
                ->missing('artifacts.2')
                ->where('decisionDefinition.fields.0.key', 'demo_quantity')
                ->where('memoDefinition.title', 'Test memo')
                ->where('status.resolution_status', 'unresolved'));

        $this->actingAs($other['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertForbidden();
    }

    public function test_student_workspace_submits_decision_and_memo_and_locks_submitted_work(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);

        $this->actingAs($graph['student'])
            ->post(route('student.submissions.decisions.draft', $context['runtimeWeek']), [
                'definition_ulid' => $context['decisionDefinition']->ulid,
                'answers' => ['demo_choice' => 'alpha'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('decision_submissions', [
            'tenant_id' => $graph['tenant']->id,
            'team_simulation_id' => $context['teamSimulation']->id,
            'status' => 'draft',
        ]);

        $this->actingAs($graph['student'])
            ->post(route('student.submissions.decisions.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['decisionDefinition']->ulid,
                'answers' => ['demo_quantity' => 42, 'demo_choice' => 'beta'],
            ])
            ->assertRedirect();

        $this->actingAs($graph['student'])
            ->post(route('student.submissions.memo.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['memoDefinition']->ulid,
                'body' => 'Final memo text',
            ])
            ->assertRedirect();

        $this->assertSame(1, DecisionSubmission::query()->where('team_simulation_id', $context['teamSimulation']->id)->count());
        $this->assertSame(1, MemoSubmission::query()->where('team_simulation_id', $context['teamSimulation']->id)->count());

        $this->actingAs($graph['student'])
            ->post(route('student.submissions.decisions.draft', $context['runtimeWeek']), [
                'definition_ulid' => $context['decisionDefinition']->ulid,
                'answers' => ['demo_quantity' => 7],
            ])
            ->assertSessionHasErrors('submission');

        $this->actingAs($graph['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('decisionDefinition.status', 'submitted')
                ->where('memoDefinition.status', 'submitted')
                ->where('status.complete', true)
                ->where('status.ready_for_evaluation', true));
    }

    public function test_student_workspace_does_not_leak_peer_or_faculty_content(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);
        $peer = $this->addPeerTeam($graph, $context['sectionSimulation']->id);
        $this->activateContentPackage($context['runtimeWeek']->definition);

        app(SubmissionService::class)->submitDecision(
            $peer['student'],
            $context['runtimeWeek'],
            $peer['teamSimulation'],
            $context['decisionDefinition'],
            ['demo_quantity' => 9],
        );

        $this->actingAs($graph['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('team.name', 'Team A')
                ->where('decisionDefinition.status', 'not_started')
                ->where('status.decision_status', 'not_started')
                ->where('artifacts.0.key', 'student-brief')
                ->where('artifacts.0.label', 'Student Brief')
                ->where('artifacts.1.key', 'shared-guide')
                ->where('artifacts.1.label', 'Shared Guide')
                ->missing('artifacts.2')
                ->missing('peerSubmissions')
                ->missing('cohortStatus'));

        $this->actingAs($graph['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertDontSee('faculty-solution', false)
            ->assertDontSee('Peer Team', false)
            ->assertDontSee('student-peer@example.test', false);
    }

    public function test_faculty_lifecycle_page_shows_submission_status_for_authorized_section(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);

        app(SubmissionService::class)->submitDecision(
            $graph['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['demo_quantity' => 5],
        );

        $this->actingAs($graph['faculty'])
            ->get(route('simulation-lifecycle.overview'))
            ->assertOk()
            ->assertSee('Team submission status')
            ->assertSee('Decisions: submitted');
    }

    private function activateContentPackage(SimulationWeek $week): SimulationContentPackage
    {
        $doc = 'docs/BATCH11A_IMPLEMENTATION.md';
        $package = app(SimulationContentPackageService::class)->register(
            $week,
            'reference_package',
            'student-week-v1',
            ['week' => $week->week_number, 'package' => 'student-week-v1'],
            [
                [
                    'artifact_key' => 'student-brief',
                    'artifact_type' => 'briefing',
                    'visibility' => 'student',
                    'path_reference' => $doc,
                    'checksum' => hash_file('sha256', base_path($doc)),
                    'version' => 'student-v1',
                ],
                [
                    'artifact_key' => 'faculty-solution',
                    'artifact_type' => 'solution',
                    'visibility' => 'solution',
                    'path_reference' => $doc,
                    'checksum' => hash_file('sha256', base_path($doc)),
                    'version' => 'faculty-v1',
                ],
                [
                    'artifact_key' => 'shared-guide',
                    'artifact_type' => 'guide',
                    'visibility' => 'shared',
                    'path_reference' => $doc,
                    'checksum' => hash_file('sha256', base_path($doc)),
                    'version' => 'shared-v1',
                ],
            ],
        );

        app(SimulationContentActivationService::class)->activate($package);

        return $package;
    }

    /**
     * @return array{student: User, teamSimulation: TeamSimulation}
     */
    private function addPeerTeam(array $graph, int $sectionSimulationId): array
    {
        $student = User::factory()->student()->create([
            'tenant_id' => $graph['tenant']->id,
            'email' => 'student-peer@example.test',
        ]);
        $team = Team::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'name' => 'Peer Team',
            'slug' => 'peer-team',
        ]);

        Enrollment::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'user_id' => $student->id,
            'status' => 'active',
        ]);

        TeamMember::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'team_id' => $team->id,
            'user_id' => $student->id,
        ]);

        $teamSimulation = TeamSimulation::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_simulation_id' => $sectionSimulationId,
            'section_id' => $graph['section']->id,
            'team_id' => $team->id,
        ]);

        return compact('student', 'teamSimulation');
    }
}
