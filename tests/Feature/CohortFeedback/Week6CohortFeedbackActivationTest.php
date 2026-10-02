<?php

namespace Tests\Feature\CohortFeedback;

use App\Domain\Capital\CapitalAllocationService;
use App\Domain\CohortFeedback\CohortFeedbackService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\CapitalProject;
use App\Models\CohortFeedbackEffect;
use App\Models\CohortResponseFunction;
use App\Models\Enrollment;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week6CohortFeedbackActivationTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week6_capital_allocations_can_drive_configured_week8_cohort_feedback(): void
    {
        $context = $this->week6CohortContext();
        $this->seedCapacityProjects();

        app(CapitalAllocationService::class)->submitAllocation(
            $context['graph']['student'],
            $context['teamSimulation'],
            $context['week6'],
            ['baton_rouge', 'helix'],
            ['rotterdam'],
        );
        app(CapitalAllocationService::class)->submitAllocation(
            $context['otherStudent'],
            $context['otherTeamSimulation'],
            $context['week6'],
            ['helix'],
            ['baton_rouge', 'rotterdam'],
        );
        $this->closeWeek6($context);

        $aggregate = app(CohortFeedbackService::class)->resolveForSectionWeek(
            $context['function'],
            $context['week6']->refresh(),
            $context['graph']['faculty'],
        );
        $effect = CohortFeedbackEffect::query()->firstOrFail();

        $this->assertSame(2, $aggregate->aggregate_snapshot['decision_count']);
        $this->assertSame('40.000000', $aggregate->aggregate_snapshot['sum']);
        $this->assertSame('40.000000', $aggregate->aggregate_snapshot['value']);
        $this->assertSame('fixture_linear_response_v1', $aggregate->function_version);
        $this->assertSame('week6_gulf_coast_capacity_to_week8_margin', $effect->effect_key);
        $this->assertSame('-2.000000', $effect->effect_snapshot['response']['bounded_value']);
        $this->assertSame('21.50', $effect->effect_snapshot['response']['parallel_universe_baseline']);
        $this->assertSame($context['week8']->id, $effect->target_section_simulation_week_id);
        $this->assertArrayNotHasKey('individual_decisions_snapshot', $effect->effect_snapshot);
    }

    public function test_current_week6_package_does_not_contain_authoritative_week6_to_week8_response_parameters(): void
    {
        $packageFiles = collect([
            base_path('halden-week6-data-package/MANIFEST.md'),
            base_path('halden-week6-data-package/fixtures/week6_golden.json'),
            base_path('halden-week6-data-package/data/cohort_discount_schedule.csv'),
        ])->map(fn (string $path): string => file_get_contents($path) ?: '')->implode("\n");

        $this->assertStringNotContainsString('Week 6 Gulf Coast capacity additions', $packageFiles);
        $this->assertStringNotContainsString('Week 8 GC refining margin', $packageFiles);
        $this->assertStringNotContainsString('gulf_coast_capacity_to_week8_margin', $packageFiles);
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     otherStudent: User,
     *     sectionSimulation: SectionSimulation,
     *     week6: SectionSimulationWeek,
     *     week8: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation,
     *     otherTeamSimulation: TeamSimulation,
     *     function: CohortResponseFunction
     * }
     */
    private function week6CohortContext(): array
    {
        $graph = $this->tenantGraph('A');
        [$otherStudent] = $this->addSecondTeam($graph);
        $structure = $this->simulationStructure(8);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week6Definition */
        $week6Definition = $structure['simulationWeeks']->firstWhere('week_number', 6);
        /** @var SimulationWeek $week8Definition */
        $week8Definition = $structure['simulationWeeks']->firstWhere('week_number', 8);
        /** @var SectionSimulationWeek $week6 */
        $week6 = $sectionSimulation->weeks()->where('simulation_week_id', $week6Definition->id)->firstOrFail();
        /** @var SectionSimulationWeek $week8 */
        $week8 = $sectionSimulation->weeks()->where('simulation_week_id', $week8Definition->id)->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $week6 = $lifecycle->transitionWeek($week6, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $week6 = $lifecycle->transitionWeek($week6->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();
        $otherTeamSimulation = TeamSimulation::query()
            ->where('tenant_id', $graph['tenant']->id)
            ->where('section_simulation_id', $sectionSimulation->id)
            ->whereHas('team.members', fn ($query) => $query->whereKey($otherStudent->id))
            ->firstOrFail();

        $function = CohortResponseFunction::query()->create([
            'key' => 'fixture_week6_to_week8_capacity',
            'name' => 'Fixture Week 6 to Week 8 Gulf Coast capacity response',
            'description' => 'Framework activation fixture only; authoritative Window 2 calibration is not present in the Week 6 package.',
            'version' => 'fixture_linear_response_v1',
            'source_week_number' => 6,
            'target_week_number' => 8,
            'input_definition' => [
                'source' => 'capital_allocation',
                'project_keys' => ['baton_rouge'],
                'project_metric' => 'gc_capacity_addition_mkbd',
                'unit' => 'mbkd',
            ],
            'output_definition' => [
                'key' => 'week6_gulf_coast_capacity_to_week8_margin',
                'unit' => 'usd_bbl',
            ],
            'bounds' => [
                'min' => '-3.225',
                'max' => '3.225',
            ],
            'parameters' => [
                'aggregate' => 'sum',
                'intercept' => '0',
                'slope' => '-0.05',
                'parallel_universe_baseline' => '21.50',
                'source_status' => 'fixture_until_authoritative_window2_package',
            ],
            'is_active' => true,
        ]);

        return compact('graph', 'otherStudent', 'sectionSimulation', 'week6', 'week8', 'teamSimulation', 'otherTeamSimulation', 'function');
    }

    private function seedCapacityProjects(): void
    {
        foreach ([
            ['key' => 'baton_rouge', 'name' => 'Baton Rouge Upgrade', 'category' => 'refining', 'risk_class' => 'refining_upgrade', 'capacity' => '40'],
            ['key' => 'rotterdam', 'name' => 'Rotterdam Upgrade', 'category' => 'refining', 'risk_class' => 'refining_upgrade', 'capacity' => '0'],
            ['key' => 'helix', 'name' => 'Project Helix', 'category' => 'transition', 'risk_class' => 'adjacent_transition', 'capacity' => '0'],
        ] as $project) {
            CapitalProject::query()->create([
                'key' => $project['key'],
                'name' => $project['name'],
                'category' => $project['category'],
                'version' => 'week6_capacity_fixture_v1',
                'cash_flow_reference' => 'halden-week6-data-package/data/project_cashflows.csv#'.$project['key'],
                'risk_class' => $project['risk_class'],
                'required_inputs' => ['requires_week6_reference_package' => true],
                'metadata' => [
                    'package_root' => 'halden-week6-data-package',
                    'gc_capacity_addition_mkbd' => $project['capacity'],
                ],
                'is_active' => true,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function closeWeek6(array $context): void
    {
        app(SimulationLifecycleService::class)->transitionWeek(
            $context['week6']->refresh(),
            SectionSimulationWeekStatus::Closed,
            $context['graph']['faculty'],
        );
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array{User, Team}
     */
    private function addSecondTeam(array $graph): array
    {
        $student = User::factory()->student()->create([
            'tenant_id' => $graph['tenant']->id,
            'email' => 'student-second-week6-cohort-'.$graph['tenant']->id.'@example.test',
        ]);
        $team = Team::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'name' => 'Team second Week 6 cohort',
            'slug' => 'team-second-week6-cohort-'.$graph['tenant']->id,
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

        return [$student, $team];
    }
}
