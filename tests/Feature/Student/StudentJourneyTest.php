<?php

namespace Tests\Feature\Student;

use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Content\SimulationContentPackageService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\Enrollment;
use App\Models\MemoDefinition;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationContentPackage;
use App\Models\SimulationWeek;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use App\Models\Week1EconomicEvaluation;
use App\Models\WeekExecutionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class StudentJourneyTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_student_dashboard_shows_own_multi_week_journey_and_safe_current_content(): void
    {
        $context = $this->studentJourneyContext();

        $this->actingAs($context['graph']['student'])
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Dashboard')
                ->where('journey.simulations.0.course', 'Managerial Economics Journey')
                ->where('journey.simulations.0.section', 'Section Journey')
                ->where('journey.simulations.0.team', 'Team Journey')
                ->where('journey.simulations.0.current_week.number', 2)
                ->where('journey.simulations.0.current_week.decision_status', 'not_started')
                ->where('journey.simulations.0.current_content.package.status', 'active')
                ->where('journey.simulations.0.current_content.artifacts.0.key', 'student-brief')
                ->where('journey.simulations.0.current_content.artifacts.1.key', 'shared-guide')
                ->missing('journey.simulations.0.current_content.artifacts.2')
                ->where('journey.simulations.0.timeline.0.state', 'completed')
                ->where('journey.simulations.0.timeline.1.state', 'active')
                ->where('journey.simulations.0.timeline.2.state', 'upcoming')
                ->where('journey.simulations.0.history.0.decision_status', 'submitted')
                ->where('journey.simulations.0.history.0.memo_status', 'submitted')
                ->where('journey.simulations.0.history.0.resolution_status', 'resolved')
                ->where('journey.simulations.0.history.0.result_summary.label', 'Result')
            )
            ->assertDontSee('faculty-solution', false)
            ->assertDontSee('golden-fixture', false)
            ->assertDontSee('future-secret', false)
            ->assertDontSee('Peer Team', false)
            ->assertDontSee('student-peer-journey@example.test', false);
    }

    public function test_student_dashboard_denies_non_students(): void
    {
        $context = $this->studentJourneyContext('FacultyDenied');

        $this->actingAs($context['graph']['faculty'])
            ->get(route('student.dashboard'))
            ->assertForbidden();
    }

    public function test_student_pages_treat_completed_deferred_week_execution_as_resolved(): void
    {
        $context = $this->studentJourneyContext('DeferredResolved');

        WeekExecutionRecord::query()->create([
            'tenant_id' => $context['week14']->tenant_id,
            'section_simulation_id' => $context['week14']->section_simulation_id,
            'section_simulation_week_id' => $context['week14']->id,
            'simulation_week_id' => $context['week14']->simulation_week_id,
            'execution_version' => 'week_execution_v1',
            'status' => WeekExecutionRecord::STATUS_COMPLETED,
            'steps' => [
                ['key' => 'validate_content_package', 'status' => 'completed'],
                ['key' => 'resolve_decisions', 'status' => 'deferred'],
            ],
            'outputs' => [],
            'started_by_user_id' => $context['graph']['faculty']->id,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $lifecycle = app(SimulationLifecycleService::class);
        $week14 = $lifecycle->transitionWeek($context['week14']->refresh(), SectionSimulationWeekStatus::Released, $context['graph']['faculty']);
        $week14 = $lifecycle->transitionWeek($week14->refresh(), SectionSimulationWeekStatus::Open, $context['graph']['faculty'], now()->addDay());
        $week14 = $lifecycle->transitionWeek($week14->refresh(), SectionSimulationWeekStatus::Closed, $context['graph']['faculty']);
        $context['week14'] = $lifecycle->transitionWeek($week14->refresh(), SectionSimulationWeekStatus::Published, $context['graph']['faculty']);

        $this->actingAs($context['graph']['student'])
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('journey.simulations.0.history.2.resolution_status', 'resolved')
                ->where('journey.simulations.0.history.2.result_summary.label', 'Execution completed')
            );

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['week14']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status.resolution_status', 'resolved')
            );
    }

    public function test_student_cannot_access_other_team_or_faculty_tools(): void
    {
        $context = $this->studentJourneyContext('SecureA');
        $other = $this->studentJourneyContext('SecureB');

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $other['week2']))
            ->assertForbidden();

        $this->actingAs($context['graph']['student'])
            ->get(route('faculty.dashboard'))
            ->assertForbidden();

        $this->actingAs($context['graph']['student'])
            ->get(route('faculty.causal-trace'))
            ->assertForbidden();

        $this->actingAs($context['graph']['student'])
            ->get(route('faculty.what-if'))
            ->assertForbidden();
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     week1: SectionSimulationWeek,
     *     week2: SectionSimulationWeek,
     *     week14: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation
     * }
     */
    private function studentJourneyContext(string $suffix = 'Journey'): array
    {
        $graph = $this->tenantGraph($suffix);
        $structure = $this->simulationStructure(14);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        $this->addPeerTeam($graph, $sectionSimulation->id);

        /** @var SimulationWeek $week1Definition */
        $week1Definition = $structure['simulationWeeks']->firstWhere('week_number', 1);
        /** @var SimulationWeek $week2Definition */
        $week2Definition = $structure['simulationWeeks']->firstWhere('week_number', 2);
        /** @var SimulationWeek $week3Definition */
        $week3Definition = $structure['simulationWeeks']->firstWhere('week_number', 3);
        /** @var SimulationWeek $week14Definition */
        $week14Definition = $structure['simulationWeeks']->firstWhere('week_number', 14);

        /** @var SectionSimulationWeek $week1 */
        $week1 = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week1Definition->id)
            ->firstOrFail();
        /** @var SectionSimulationWeek $week2 */
        $week2 = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week2Definition->id)
            ->firstOrFail();
        /** @var SectionSimulationWeek $week3 */
        $week3 = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week3Definition->id)
            ->firstOrFail();
        /** @var SectionSimulationWeek $week14 */
        $week14 = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week14Definition->id)
            ->firstOrFail();

        $this->activateStudentPackage($week2Definition);
        $this->activateStudentPackage($week3Definition, 'future-secret');

        $lifecycle = app(SimulationLifecycleService::class);
        $week1 = $lifecycle->transitionWeek($week1, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $week1 = $lifecycle->transitionWeek($week1->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());
        $week2 = $lifecycle->transitionWeek($week2, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $week2 = $lifecycle->transitionWeek($week2->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());
        $lifecycle->transitionWeek($week3, SectionSimulationWeekStatus::Scheduled, $graph['faculty']);

        $week1Decision = $this->decisionDefinition($week1, 'week1_decision');
        $week1Memo = $this->memoDefinition($week1, 'week1_memo');
        $this->decisionDefinition($week2, 'week2_decision');
        $this->memoDefinition($week2, 'week2_memo');

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        $submission = app(SubmissionService::class)->submitDecision(
            $graph['student'],
            $week1,
            $teamSimulation,
            $week1Decision,
            ['student_choice' => 42],
        );
        app(SubmissionService::class)->submitMemo(
            $graph['student'],
            $week1,
            $teamSimulation,
            $week1Memo,
            'Our team submitted the first week memo.',
        );

        Week1EconomicEvaluation::query()->create([
            'tenant_id' => $week1->tenant_id,
            'section_simulation_id' => $week1->section_simulation_id,
            'section_simulation_week_id' => $week1->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => 'student_journey_fixture',
            'engine_version' => 'test_v1',
            'package_identifier' => 'student_journey_fixture',
            'package_version' => 'test_v1',
            'status' => Week1EconomicEvaluation::STATUS_CALCULATED,
            'permian_margin' => '56.400000',
            'rot_net' => '-0.300000',
            'economic_rank' => 'Permian > Kessana > Baton Rouge > Norwegian > Singapore > Rotterdam',
            'reported_rank' => 'Permian > Baton Rouge > Kessana > Norwegian > Singapore > Rotterdam',
            'top_economic_asset' => 'Permian',
            'input_snapshot' => ['fixture' => true],
            'output_snapshot' => ['released' => true],
            'evaluated_by_user_id' => $graph['faculty']->id,
            'evaluated_by_process' => 'student_journey_fixture',
            'evaluated_at' => now(),
        ]);

        $week1 = $lifecycle->transitionWeek($week1->refresh(), SectionSimulationWeekStatus::Closed, $graph['faculty']);
        $lifecycle->transitionWeek($week1->refresh(), SectionSimulationWeekStatus::Published, $graph['faculty']);

        return compact('graph', 'sectionSimulation', 'week1', 'week2', 'week14', 'teamSimulation');
    }

    private function activateStudentPackage(SimulationWeek $week, string $studentKey = 'student-brief'): SimulationContentPackage
    {
        $doc = 'docs/BATCH30A_IMPLEMENTATION.md';
        $package = app(SimulationContentPackageService::class)->register(
            $week,
            'reference_package',
            'student-journey-'.$week->week_number,
            ['week' => $week->week_number, 'package' => 'student-journey'],
            [
                [
                    'artifact_key' => $studentKey,
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
                    'artifact_key' => 'golden-fixture',
                    'artifact_type' => 'fixture',
                    'visibility' => 'faculty',
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

    private function decisionDefinition(SectionSimulationWeek $runtimeWeek, string $key): DecisionFormDefinition
    {
        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => $key,
            'name' => 'Student journey decision',
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $definition->id,
            'field_key' => 'student_choice',
            'label' => 'Student choice',
            'field_type' => DecisionFieldType::Integer,
            'is_required' => true,
            'display_order' => 1,
            'validation' => ['min' => 0, 'max' => 100],
        ]);

        return $definition;
    }

    private function memoDefinition(SectionSimulationWeek $runtimeWeek, string $key): MemoDefinition
    {
        return MemoDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => $key,
            'title' => 'Student journey memo',
            'is_required' => true,
            'character_limit' => 4000,
        ]);
    }

    private function addPeerTeam(array $graph, int $sectionSimulationId): void
    {
        $student = User::factory()->student()->create([
            'tenant_id' => $graph['tenant']->id,
            'email' => 'student-peer-journey@example.test',
        ]);
        $team = Team::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'name' => 'Peer Team',
            'slug' => 'peer-journey-team',
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

        TeamSimulation::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_simulation_id' => $sectionSimulationId,
            'section_id' => $graph['section']->id,
            'team_id' => $team->id,
        ]);
    }
}
