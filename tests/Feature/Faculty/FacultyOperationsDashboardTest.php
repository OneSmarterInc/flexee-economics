<?php

namespace Tests\Feature\Faculty;

use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageRegistrationService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Economics\Week13\Week13EconomicEngine;
use App\Domain\Execution\WeekExecutionService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Livewire\FacultyOperationsDashboard;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\MemoDefinition;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use App\Models\User;
use App\Models\WeekExecutionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class FacultyOperationsDashboardTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_student_is_denied_faculty_operations_dashboard(): void
    {
        $graph = $this->tenantGraph('OpsDenied');

        $this->actingAs($graph['student'])
            ->get(route('faculty.dashboard'))
            ->assertForbidden();
    }

    public function test_assigned_faculty_sees_only_authorized_section_simulations(): void
    {
        $context = $this->week13DashboardContext('OpsScope');
        $other = $this->week13DashboardContext('OpsOther');

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyOperationsDashboard::class)
            ->assertSee($context['sectionSimulation']->name)
            ->assertDontSee($other['sectionSimulation']->name)
            ->assertSee('Faculty operations')
            ->assertSee('Course / section')
            ->assertSee('Current week')
            ->assertSee('Readiness');
    }

    public function test_admin_can_view_tenant_scoped_faculty_dashboard(): void
    {
        $context = $this->week13DashboardContext('OpsAdmin');

        Livewire::actingAs($context['graph']['admin'])
            ->test(FacultyOperationsDashboard::class)
            ->assertSee($context['sectionSimulation']->name)
            ->assertSee('Simulation control dashboard');
    }

    public function test_dashboard_surfaces_week_timeline_operations_and_results_without_submission_leakage(): void
    {
        $context = $this->week13DashboardContext('OpsReview');
        $this->recordCompletedWeek4($context['sectionSimulation'], $context['graph']['faculty']);
        $this->submitAndExecuteWeek13($context);

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyOperationsDashboard::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('runtimeWeekId', $context['runtimeWeek']->id)
            ->assertSee('Week timeline')
            ->assertSee('Week 4')
            ->assertSee('completed')
            ->assertSee('Week 13')
            ->assertSee('Package')
            ->assertSee('Execution state')
            ->assertSee('completed')
            ->assertSee('Results review')
            ->assertSee('Week 13 factor-market evaluations')
            ->assertSee('calculated')
            ->assertSee('KPI Basis')
            ->assertSee('No KPI snapshots exist for this week yet.')
            ->assertSee('Ranking')
            ->assertSee('Ranking snapshots appear after supported KPI calculation runs.')
            ->assertSee('No authoritative consequence mapping available.')
            ->assertSee('Open causal trace')
            ->assertSee('Open what-if console')
            ->assertDontSee('accept_norway_concession_compete_permian_delay_turnaround');
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     runtimeWeek: SectionSimulationWeek,
     *     decisionDefinition: DecisionFormDefinition,
     *     memoDefinition: MemoDefinition,
     *     teamSimulation: TeamSimulation
     * }
     */
    private function week13DashboardContext(string $suffix): array
    {
        $graph = $this->tenantGraph($suffix);
        $structure = $this->simulationStructure(14);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week13Definition */
        $week13Definition = $structure['simulationWeeks']->firstWhere('week_number', 13);

        app(SimulationContentActivationService::class)->activate(
            app(AuthoritativeContentPackageRegistrationService::class)->register($week13Definition, 'week13-dashboard-'.$suffix),
        );

        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week13Definition->id)
            ->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week13_factor_markets',
            'name' => 'Week 13 factor markets',
            'version' => Week13EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week13EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
            'field_key' => 'factor_market_strategy',
            'label' => 'Factor market strategy',
            'field_type' => DecisionFieldType::ShortText,
            'is_required' => true,
            'display_order' => 1,
            'help_text' => 'Package-backed Week 13 factor-market strategy note.',
            'validation' => ['max_length' => 255],
        ]);

        $memoDefinition = MemoDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week13_factor_markets_memo',
            'title' => 'Week 13 factor markets memo',
            'version' => Week13EconomicEngine::ENGINE_VERSION,
            'is_required' => true,
            'character_limit' => 4000,
        ]);

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        return compact('graph', 'sectionSimulation', 'runtimeWeek', 'decisionDefinition', 'memoDefinition', 'teamSimulation');
    }

    /**
     * @param  array{
     *     graph: array<string, mixed>,
     *     runtimeWeek: SectionSimulationWeek,
     *     decisionDefinition: DecisionFormDefinition,
     *     memoDefinition: MemoDefinition,
     *     teamSimulation: TeamSimulation
     * }  $context
     */
    private function submitAndExecuteWeek13(array $context): void
    {
        $submissions = app(SubmissionService::class);
        $submissions->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['factor_market_strategy' => 'accept_norway_concession_compete_permian_delay_turnaround'],
        );
        $submissions->submitMemo(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['memoDefinition'],
            'We compare Norway concessions, Permian labor productivity, and turnaround timing.',
        );

        app(WeekExecutionService::class)->execute(
            $context['runtimeWeek'],
            $context['graph']['faculty'],
            app(AuthoritativeContentPackageManifest::class)->packageType(13),
        );
    }

    private function recordCompletedWeek4(SectionSimulation $sectionSimulation, User $faculty): void
    {
        /** @var SectionSimulationWeek $week4 */
        $week4 = $sectionSimulation->weeks()
            ->whereHas('definition', fn ($query) => $query->where('week_number', 4))
            ->firstOrFail();

        WeekExecutionRecord::query()->create([
            'tenant_id' => $week4->tenant_id,
            'section_simulation_id' => $week4->section_simulation_id,
            'section_simulation_week_id' => $week4->id,
            'simulation_week_id' => $week4->simulation_week_id,
            'execution_version' => 'dashboard_fixture_v1',
            'status' => WeekExecutionRecord::STATUS_COMPLETED,
            'steps' => [
                [
                    'key' => 'validate_content_package',
                    'status' => 'completed',
                    'summary' => 'Fixture package validated.',
                    'completed_at' => now()->toISOString(),
                ],
            ],
            'outputs' => [],
            'started_by_user_id' => $faculty->id,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
