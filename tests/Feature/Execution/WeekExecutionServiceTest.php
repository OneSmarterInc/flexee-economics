<?php

namespace Tests\Feature\Execution;

use App\Domain\Capital\CapitalAllocationService;
use App\Domain\Capital\DiscountRateConsequenceService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Content\SimulationContentPackageService;
use App\Domain\Content\Week6\Week6ContentPackageManifest;
use App\Domain\Content\Week6\Week6ContentPackageRegistrationService;
use App\Domain\Economics\Resolution\WeekResolutionService;
use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Execution\WeekExecutionService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\CapitalAllocationEvaluation;
use App\Models\CapitalProject;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DiscountRateConsequence;
use App\Models\DiscountRateSchedule;
use App\Models\EconomicResolution;
use App\Models\KpiSnapshot;
use App\Models\RankingSnapshot;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use App\Models\WeekExecutionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class WeekExecutionServiceTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week_execution_runs_supported_steps_in_order(): void
    {
        $context = $this->week4ContextWithSubmittedDecision();
        $this->activateContent($context['runtimeWeek']);

        $record = app(WeekExecutionService::class)->execute($context['runtimeWeek'], $context['graph']['faculty']);

        $this->assertSame(WeekExecutionRecord::STATUS_COMPLETED, $record->status);
        $this->assertSame([
            'validate_content_package',
            'lock_submissions',
            'resolve_decisions',
            'apply_kpi_calculations',
            'calculate_rankings',
            'generate_consequences',
            'apply_cohort_effects',
            'publish_allowed_outputs',
        ], collect($record->steps)->pluck('key')->all());
        $this->assertSame('completed', $record->steps[0]['status']);
        $this->assertSame('completed', $record->steps[2]['status']);
        $this->assertSame('deferred', $record->steps[5]['status']);
        $this->assertSame(1, EconomicResolution::query()->where('section_simulation_week_id', $context['runtimeWeek']->id)->count());
        $this->assertGreaterThan(0, KpiSnapshot::query()->where('section_simulation_week_id', $context['runtimeWeek']->id)->count());
        $this->assertGreaterThan(0, RankingSnapshot::query()->where('section_simulation_week_id', $context['runtimeWeek']->id)->count());
    }

    public function test_week_execution_evaluates_week6_capital_allocations(): void
    {
        $context = $this->week6ContextWithCapitalAllocation();
        app(SimulationContentActivationService::class)->activate(
            app(Week6ContentPackageRegistrationService::class)->register(
                $context['week6']->definition,
                'week6-execution-active-v1',
            ),
        );

        $record = app(WeekExecutionService::class)->execute(
            $context['week6'],
            $context['graph']['faculty'],
            Week6ContentPackageManifest::PACKAGE_TYPE,
        );

        $evaluation = CapitalAllocationEvaluation::query()
            ->where('section_simulation_week_id', $context['week6']->id)
            ->firstOrFail();

        $this->assertSame(WeekExecutionRecord::STATUS_COMPLETED, $record->status);
        $this->assertSame('completed', $record->steps[2]['status']);
        $this->assertSame('deferred', $record->steps[3]['status']);
        $this->assertSame(1, $record->outputs['resolve_decisions']['capital_allocation_evaluation_count']);
        $this->assertSame(1, $record->outputs['resolve_decisions']['evaluation_status_counts'][CapitalAllocationEvaluation::STATUS_CALCULATED]);
        $this->assertSame(CapitalAllocationEvaluation::STATUS_CALCULATED, $evaluation->status);
        $this->assertSame('492.927', $evaluation->portfolioNpvMusdValue());
        $this->assertSame('week_execution_service', $evaluation->evaluated_by_process);
        $this->assertSame(0, KpiSnapshot::query()->where('section_simulation_week_id', $context['week6']->id)->count());
        $this->assertSame(0, RankingSnapshot::query()->where('section_simulation_week_id', $context['week6']->id)->count());
    }

    public function test_week_execution_requires_active_content_package_before_resolution(): void
    {
        $context = $this->week4ContextWithSubmittedDecision();

        $record = app(WeekExecutionService::class)->execute($context['runtimeWeek'], $context['graph']['faculty']);

        $this->assertSame(WeekExecutionRecord::STATUS_FAILED, $record->status);
        $this->assertSame('validate_content_package', $record->steps[0]['key']);
        $this->assertSame('failed', $record->steps[0]['status']);
        $this->assertSame(0, EconomicResolution::query()->where('section_simulation_week_id', $context['runtimeWeek']->id)->count());
    }

    public function test_duplicate_week_execution_is_prevented(): void
    {
        $context = $this->week4ContextWithSubmittedDecision();
        $this->activateContent($context['runtimeWeek']);
        $service = app(WeekExecutionService::class);

        $service->execute($context['runtimeWeek'], $context['graph']['faculty']);

        $this->expectException(InvalidArgumentException::class);

        $service->execute($context['runtimeWeek'], $context['graph']['faculty']);
    }

    public function test_failed_late_step_preserves_completed_step_history(): void
    {
        $context = $this->week4ContextWithSubmittedDecision();
        $this->activateContent($context['runtimeWeek']);

        $record = app(WeekExecutionService::class)->execute(
            $context['runtimeWeek'],
            $context['graph']['faculty'],
            forcedFailures: ['apply_cohort_effects' => 'cohort response function missing'],
        );

        $this->assertSame(WeekExecutionRecord::STATUS_FAILED, $record->status);
        $this->assertSame('apply_cohort_effects', collect($record->steps)->last()['key']);
        $this->assertSame('failed', collect($record->steps)->last()['status']);
        $this->assertSame('completed', $record->steps[4]['status']);
        $this->assertSame('cohort response function missing', $record->failure_message);
        $this->assertGreaterThan(0, KpiSnapshot::query()->where('section_simulation_week_id', $context['runtimeWeek']->id)->count());
    }

    public function test_cross_tenant_actor_cannot_execute_week(): void
    {
        $first = $this->week4ContextWithSubmittedDecision('A');
        $second = $this->week4ContextWithSubmittedDecision('B');
        $this->activateContent($second['runtimeWeek']);

        $this->expectException(InvalidArgumentException::class);

        app(WeekExecutionService::class)->execute($second['runtimeWeek'], $first['graph']['faculty']);
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     runtimeWeek: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation
     * }
     */
    private function week4ContextWithSubmittedDecision(string $suffix = 'A'): array
    {
        $graph = $this->tenantGraph($suffix);
        $structure = $this->simulationStructure(4);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $weekDefinition */
        $weekDefinition = $structure['simulationWeeks']->firstWhere('week_number', 4);
        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()->where('simulation_week_id', $weekDefinition->id)->firstOrFail();
        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week4_transfer_pricing',
            'name' => 'Week 4 transfer pricing',
            'version' => Week4EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week4EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
            'field_key' => 'transfer_price',
            'label' => 'Transfer price',
            'field_type' => DecisionFieldType::Currency,
            'is_required' => true,
            'display_order' => 1,
            'validation' => ['min' => 0, 'max' => 250],
        ]);

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        app(SubmissionService::class)->submitDecision(
            $graph['student'],
            $runtimeWeek,
            $teamSimulation,
            $decisionDefinition,
            ['transfer_price' => '46.20'],
        );

        return compact('graph', 'runtimeWeek', 'teamSimulation');
    }

    private function activateContent(SectionSimulationWeek $runtimeWeek): void
    {
        $doc = 'docs/BATCH12A_IMPLEMENTATION.md';
        $package = app(SimulationContentPackageService::class)->register(
            $runtimeWeek->definition,
            'reference_package',
            'execution-test-v1-'.uniqid(),
            ['week' => $runtimeWeek->definition->week_number],
            [[
                'artifact_key' => 'execution-test-artifact',
                'artifact_type' => 'documentation',
                'visibility' => 'faculty',
                'path_reference' => $doc,
                'checksum' => hash_file('sha256', base_path($doc)),
                'version' => 'v1',
            ]],
        );

        app(SimulationContentActivationService::class)->activate($package);
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     week4: SectionSimulationWeek,
     *     week6: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation,
     *     discountRateConsequence: DiscountRateConsequence
     * }
     */
    private function week6ContextWithCapitalAllocation(): array
    {
        $graph = $this->tenantGraph('A');
        $structure = $this->simulationStructure(6);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week4Definition */
        $week4Definition = $structure['simulationWeeks']->firstWhere('week_number', 4);
        /** @var SimulationWeek $week6Definition */
        $week6Definition = $structure['simulationWeeks']->firstWhere('week_number', 6);
        /** @var SectionSimulationWeek $week4 */
        $week4 = $sectionSimulation->weeks()->where('simulation_week_id', $week4Definition->id)->firstOrFail();
        /** @var SectionSimulationWeek $week6 */
        $week6 = $sectionSimulation->weeks()->where('simulation_week_id', $week6Definition->id)->firstOrFail();
        $lifecycle = app(SimulationLifecycleService::class);
        $week4 = $lifecycle->transitionWeek($week4, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $week4 = $lifecycle->transitionWeek($week4->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());
        $week6 = $lifecycle->transitionWeek($week6, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $week6 = $lifecycle->transitionWeek($week6->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $week4->simulation_version_id,
            'simulation_week_id' => $week4->simulation_week_id,
            'key' => 'week4_transfer_pricing',
            'name' => 'Week 4 transfer pricing',
            'version' => Week4EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week4EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
            'field_key' => 'transfer_price',
            'label' => 'Transfer price',
            'field_type' => DecisionFieldType::Currency,
            'is_required' => true,
            'display_order' => 1,
            'validation' => ['min' => 0, 'max' => 250],
        ]);

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();
        $submission = app(SubmissionService::class)->submitDecision(
            $graph['student'],
            $week4,
            $teamSimulation,
            $decisionDefinition,
            ['transfer_price' => '46.20'],
        );
        $resolution = app(WeekResolutionService::class)->resolveSubmittedDecision($submission, $graph['student']);
        $discountRateConsequence = app(DiscountRateConsequenceService::class)->resolve(
            $resolution,
            $this->discountRateSchedule(),
            $graph['faculty'],
        );

        $this->seedWeek6PackageProjects();
        app(CapitalAllocationService::class)->submitAllocation(
            $graph['student'],
            $teamSimulation,
            $week6,
            ['baton_rouge', 'helix'],
            ['rotterdam'],
        );

        return compact('graph', 'sectionSimulation', 'week4', 'week6', 'teamSimulation', 'discountRateConsequence');
    }

    private function seedWeek6PackageProjects(): void
    {
        foreach ([
            ['key' => 'baton_rouge', 'name' => 'Baton Rouge Upgrade', 'category' => 'refining', 'risk_class' => 'refining_upgrade'],
            ['key' => 'rotterdam', 'name' => 'Rotterdam Upgrade', 'category' => 'refining', 'risk_class' => 'refining_upgrade'],
            ['key' => 'helix', 'name' => 'Project Helix', 'category' => 'transition', 'risk_class' => 'adjacent_transition'],
        ] as $project) {
            CapitalProject::query()->create([
                ...$project,
                'version' => 'week6_reference_package_v1',
                'cash_flow_reference' => 'halden-week6-data-package/data/project_cashflows.csv#'.$project['key'],
                'required_inputs' => ['requires_week6_reference_package' => true],
                'metadata' => ['package_root' => 'halden-week6-data-package'],
                'is_active' => true,
            ]);
        }
    }

    private function discountRateSchedule(): DiscountRateSchedule
    {
        return DiscountRateSchedule::query()->create([
            'key' => 'week4_to_week6_discount_rate',
            'name' => 'Week 4 to Week 6 discount rate',
            'version' => 'discount_rate_v1_'.uniqid(),
            'source_week_number' => 4,
            'target_week_number' => 6,
            'classification_rules' => [
                ['field' => 'geneva_capture_per_bbl', 'operator' => '<=', 'value' => '9.625', 'classification' => 'base'],
            ],
            'classification_outcomes' => [
                'base' => [
                    'discount_rate_percent' => '8.5',
                    'capital_envelope_musd' => '1150',
                ],
            ],
            'is_active' => true,
        ]);
    }
}
