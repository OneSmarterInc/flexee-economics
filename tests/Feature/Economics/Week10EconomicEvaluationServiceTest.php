<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week10\Week10ConvergenceEconomicEngine;
use App\Domain\Economics\Week10\Week10EconomicEvaluationService;
use App\Domain\Economics\Week10\Week10EconomicResult;
use App\Domain\Economics\Week8\Week8EconomicEngine;
use App\Enums\StandingValue;
use App\Enums\SubmissionStatus;
use App\Models\CapitalAllocationDecision;
use App\Models\CapitalAllocationEvaluation;
use App\Models\Counterparty;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\StandingState;
use App\Models\TeamSimulation;
use App\Models\Week10EconomicEvaluation;
use App\Models\Week5EconomicEvaluation;
use App\Models\Week8EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week10EconomicEvaluationServiceTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week10_evaluation_assembles_historical_state_and_matches_golden_convergence_outputs(): void
    {
        $context = $this->week10HistoricalContext();
        $submission = $this->week10Submission($context);

        $evaluation = app(Week10EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);

        $this->assertSame(Week10EconomicEvaluation::STATUS_CALCULATED, $evaluation->status);
        $this->assertSame(Week10ConvergenceEconomicEngine::ENGINE_IDENTIFIER, $evaluation->engine_identifier);
        $this->assertSame(Week10ConvergenceEconomicEngine::ENGINE_VERSION, $evaluation->engine_version);
        $this->assertSame('1.0.0-draft', $evaluation->package_version);
        $this->assertSame('-0.023700', $evaluation->blendedDemandHitValue());
        $this->assertSame('-0.026220', $evaluation->singaporeDemandHitValue());
        $this->assertSame('Singapore', $evaluation->hardest_hit_refinery);
        $this->assertSame(5, $evaluation->binding_constraint_count);
        $this->assertSame([], $evaluation->unresolved_dependencies);
        $this->assertSame('120.0', $evaluation->inherited_state_snapshot['values']['cancellable_capex_musd']);
        $this->assertSame('0.45', $evaluation->inherited_state_snapshot['values']['crude_hedge_coverage']);
        $this->assertTrue($evaluation->inherited_state_snapshot['values']['br_reported_margin_strong']);
        $this->assertSame('strained', $evaluation->inherited_state_snapshot['values']['straits_pacific_standing']);
        $this->assertSame('85.0', $evaluation->inherited_state_snapshot['values']['cash_cushion_musd']);
    }

    public function test_missing_historical_dependency_is_recorded_without_defaulting(): void
    {
        $context = $this->week10HistoricalContext(includeCashCushion: false);
        $submission = $this->week10Submission($context);

        $evaluation = app(Week10EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);

        $this->assertSame(Week10EconomicResult::STATUS_UNRESOLVED_DEPENDENCY, $evaluation->status);
        $this->assertSame(['cash_cushion_musd'], $evaluation->unresolved_dependencies);
        $this->assertNull($evaluation->binding_constraint_count);
        $this->assertSame('Week 10 inherited state is incomplete.', $evaluation->unavailable_reason);
        $this->assertNull($evaluation->inherited_state_snapshot['values']['cash_cushion_musd']);
    }

    public function test_week10_evaluation_is_idempotent_for_team_submission(): void
    {
        $context = $this->week10HistoricalContext();
        $submission = $this->week10Submission($context);
        $service = app(Week10EconomicEvaluationService::class);

        $first = $service->evaluate($submission, $context['graph']['faculty']);
        $second = $service->evaluate($submission, $context['graph']['faculty']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Week10EconomicEvaluation::query()->where('decision_submission_id', $submission->id)->count());
    }

    public function test_week5_submission_without_runtime_evaluation_does_not_satisfy_hedge_dependency(): void
    {
        $context = $this->week10HistoricalContext();
        Week5EconomicEvaluation::query()->delete();
        $submission = $this->week10Submission($context);

        $evaluation = app(Week10EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);

        $this->assertSame(Week10EconomicResult::STATUS_UNRESOLVED_DEPENDENCY, $evaluation->status);
        $this->assertSame(['crude_hedge_coverage'], $evaluation->unresolved_dependencies);
        $this->assertNull($evaluation->inherited_state_snapshot['values']['crude_hedge_coverage']);
        $this->assertSame('week5_economic_evaluation', $evaluation->inherited_state_snapshot['dependencies']['crude_hedge_coverage']['source_entity']);
    }

    public function test_draft_submission_is_rejected(): void
    {
        $context = $this->week10HistoricalContext();
        $submission = $this->week10Submission($context, SubmissionStatus::Draft->value);

        $this->expectExceptionMessage('Week 10 economic evaluation requires a submitted decision.');

        app(Week10EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     weeks: array<int, SectionSimulationWeek>,
     *     teamSimulation: TeamSimulation
     * }
     */
    private function week10HistoricalContext(bool $includeCashCushion = true): array
    {
        $graph = $this->tenantGraph(uniqid('W10'));
        $structure = $this->simulationStructure(10);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        $weeks = [];

        foreach ([4, 5, 6, 8, 10] as $weekNumber) {
            $definition = $structure['simulationWeeks']->firstWhere('week_number', $weekNumber);
            $weeks[$weekNumber] = $sectionSimulation->weeks()
                ->where('simulation_week_id', $definition->id)
                ->firstOrFail();
        }

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        $this->seedWeek4Condition($graph, $weeks[4], $teamSimulation, true);
        $this->seedWeek5Evaluation($graph, $weeks[5], $teamSimulation, '0.45');
        $this->seedWeek6CapitalEvaluation($graph, $weeks[6], $teamSimulation, '120.0');
        $this->seedStraitsPacificStanding($teamSimulation, StandingValue::Strained);

        if ($includeCashCushion) {
            $this->seedWeek8Evaluation($graph, $weeks[8], $teamSimulation, '85.0');
        }

        return compact('graph', 'sectionSimulation', 'weeks', 'teamSimulation');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function week10Submission(array $context, string $status = 'submitted'): DecisionSubmission
    {
        /** @var SectionSimulationWeek $week10 */
        $week10 = $context['weeks'][10];
        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $context['teamSimulation'];

        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $week10->simulation_version_id,
            'simulation_week_id' => $week10->simulation_week_id,
            'key' => 'week10_convergence_plan',
            'name' => 'Week 10 convergence plan',
            'version' => Week10ConvergenceEconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week10ConvergenceEconomicEngine::ENGINE_IDENTIFIER],
        ]);

        return DecisionSubmission::query()->create([
            'tenant_id' => $week10->tenant_id,
            'section_simulation_id' => $week10->section_simulation_id,
            'section_simulation_week_id' => $week10->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_form_definition_id' => $definition->id,
            'status' => $status,
            'answers' => ['operating_posture' => 'liquidity_first'],
            'lock_version' => 1,
            'updated_by_user_id' => $context['graph']['student']->id,
            'submitted_by_user_id' => $status === 'submitted' ? $context['graph']['student']->id : null,
            'draft_saved_at' => now(),
            'submitted_at' => $status === 'submitted' ? now() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $graph
     */
    private function seedWeek4Condition(array $graph, SectionSimulationWeek $week, TeamSimulation $teamSimulation, bool $strong): void
    {
        $submission = $this->historicalSubmission($graph, $week, $teamSimulation, 'week4_transfer_pricing', ['transfer_price' => '73.70']);

        EconomicResolution::query()->create([
            'tenant_id' => $week->tenant_id,
            'section_simulation_id' => $week->section_simulation_id,
            'section_simulation_week_id' => $week->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_submission_id' => $submission->id,
            'economic_engine' => 'week4_transfer_pricing',
            'engine_version' => 'week4_transfer_pricing_v1',
            'input_snapshot' => ['transfer_price' => '73.70'],
            'output_snapshot' => ['week10_inherited_state' => ['br_reported_margin_strong' => $strong]],
            'transfer_price' => '73.700',
            'integrated_margin' => '76.750',
            'upstream_margin' => '58.050',
            'refining_margin' => '18.700',
            'upstream_vs_target' => '0.000',
            'refining_vs_target' => '0.000',
            'geneva_gap' => '0.000',
            'geneva_capture_per_bbl' => '0.000',
            'geneva_max_volume_bbl_day' => '0.000',
            'resolved_by_user_id' => $graph['faculty']->id,
            'resolved_by_process' => 'test_fixture',
            'resolved_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $graph
     */
    private function seedWeek5Evaluation(array $graph, SectionSimulationWeek $week, TeamSimulation $teamSimulation, string $coverage): void
    {
        $submission = $this->historicalSubmission($graph, $week, $teamSimulation, 'week5_currency_exposure', [
            'crude_hedge_coverage' => $coverage,
        ]);

        Week5EconomicEvaluation::query()->create([
            'tenant_id' => $week->tenant_id,
            'section_simulation_id' => $week->section_simulation_id,
            'section_simulation_week_id' => $week->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => 'week5_currency_exposure',
            'engine_version' => 'week5_currency_exposure_v1',
            'package_version' => '1.0.0-draft',
            'status' => Week5EconomicEvaluation::STATUS_CALCULATED,
            'eur_change' => '-0.064516',
            'nok_usd_value_change' => '-0.076923',
            'sgd_usd_value_change' => '-0.007407',
            'norway_benefit_musd' => '84.615385',
            'norway_lifting_post' => '25.846154',
            'euro_retail_translation_musd' => '-77.419355',
            'existing_hedge_gain_musd' => '19.354839',
            'rot_net_eur_musd' => '250.000000',
            'rot_natural_hedge_ratio' => '0.900000',
            'rot_net_impact_musd' => '-16.129032',
            'rot_overhedge_loss_musd' => '-145.161290',
            'sing_impact_musd' => '-2.222222',
            'decision_snapshot' => [
                'id' => $submission->id,
                'answers' => ['crude_hedge_coverage' => $coverage],
            ],
            'input_snapshot' => [
                'decision_submission' => [
                    'id' => $submission->id,
                    'answers' => ['crude_hedge_coverage' => $coverage],
                ],
            ],
            'output_snapshot' => ['week10_inherited_state' => ['crude_hedge_coverage' => $coverage]],
            'unavailable_reason' => null,
            'evaluated_by_user_id' => $graph['faculty']->id,
            'evaluated_by_process' => 'test_fixture',
            'evaluated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $graph
     */
    private function seedWeek6CapitalEvaluation(array $graph, SectionSimulationWeek $week, TeamSimulation $teamSimulation, string $cancellableCapex): void
    {
        $decision = CapitalAllocationDecision::query()->create([
            'tenant_id' => $week->tenant_id,
            'section_simulation_id' => $week->section_simulation_id,
            'section_simulation_week_id' => $week->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'discount_rate_consequence_id' => null,
            'submitted_by_user_id' => $graph['student']->id,
            'selected_projects' => [['key' => 'helix']],
            'rejected_projects' => [['key' => 'baton_rouge'], ['key' => 'rotterdam']],
            'context_snapshot' => ['discount_rate_percent' => '8.5'],
            'memo_references' => [],
            'submitted_at' => now(),
        ]);

        CapitalAllocationEvaluation::query()->create([
            'tenant_id' => $week->tenant_id,
            'section_simulation_id' => $week->section_simulation_id,
            'section_simulation_week_id' => $week->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'capital_allocation_decision_id' => $decision->id,
            'engine_identifier' => 'week6_capital_economics',
            'engine_version' => 'week6_capital_economics_v1',
            'status' => CapitalAllocationEvaluation::STATUS_CALCULATED,
            'portfolio_npv_musd' => '492.927',
            'portfolio_irr_percent' => '12.4000',
            'capital_required_musd' => '1200.000',
            'capital_envelope_feasible' => false,
            'input_snapshot' => ['selected_project_keys' => ['helix']],
            'output_snapshot' => ['week10_inherited_state' => ['cancellable_capex_musd' => $cancellableCapex]],
            'unavailable_reason' => null,
            'evaluated_by_user_id' => $graph['faculty']->id,
            'evaluated_by_process' => 'test_fixture',
            'evaluated_at' => now(),
        ]);
    }

    private function seedStraitsPacificStanding(TeamSimulation $teamSimulation, StandingValue $state): void
    {
        $counterparty = Counterparty::query()->firstOrCreate(
            ['key' => 'straits_pacific'],
            [
                'name' => 'Straits Pacific',
                'description' => 'Singapore JV partner',
                'sort_order' => 1,
                'is_active' => true,
                'metadata' => [],
            ],
        );

        StandingState::query()->create([
            'tenant_id' => $teamSimulation->tenant_id,
            'section_simulation_id' => $teamSimulation->section_simulation_id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'counterparty_id' => $counterparty->id,
            'state' => $state->value,
            'reason' => 'Historical Week 10 fixture state.',
            'state_changed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $graph
     */
    private function seedWeek8Evaluation(array $graph, SectionSimulationWeek $week, TeamSimulation $teamSimulation, string $cashCushion): void
    {
        $submission = $this->historicalSubmission($graph, $week, $teamSimulation, 'week8_opec_prediction', ['scenario' => 'fail']);

        Week8EconomicEvaluation::query()->create([
            'tenant_id' => $week->tenant_id,
            'section_simulation_id' => $week->section_simulation_id,
            'section_simulation_week_id' => $week->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => Week8EconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week8EconomicEngine::ENGINE_VERSION,
            'package_version' => '1.0.0-draft',
            'status' => Week8EconomicEvaluation::STATUS_CALCULATED,
            'expected_wti' => '80.70',
            'expected_upstream_impact_per_bbl' => '6.70',
            'expected_refining_crack' => '19.16',
            'realized_scenario_key' => 'fail',
            'realized_wti' => '70.00',
            'realized_upstream_impact_per_bbl' => '-4.00',
            'realized_refining_crack' => '22.90',
            'realized_retail_volume_percent' => '0.300',
            'prediction_snapshot' => [],
            'realization_snapshot' => [],
            'input_snapshot' => [],
            'output_snapshot' => ['week10_inherited_state' => ['cash_cushion_musd' => $cashCushion]],
            'unavailable_reason' => null,
            'evaluated_by_user_id' => $graph['faculty']->id,
            'evaluated_by_process' => 'test_fixture',
            'evaluated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $graph
     * @param  array<string, mixed>  $answers
     */
    private function historicalSubmission(array $graph, SectionSimulationWeek $week, TeamSimulation $teamSimulation, string $key, array $answers): DecisionSubmission
    {
        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $week->simulation_version_id,
            'simulation_week_id' => $week->simulation_week_id,
            'key' => $key,
            'name' => $key,
        ]);

        return DecisionSubmission::query()->create([
            'tenant_id' => $week->tenant_id,
            'section_simulation_id' => $week->section_simulation_id,
            'section_simulation_week_id' => $week->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_form_definition_id' => $definition->id,
            'status' => SubmissionStatus::Submitted->value,
            'answers' => $answers,
            'lock_version' => 1,
            'updated_by_user_id' => $graph['student']->id,
            'submitted_by_user_id' => $graph['student']->id,
            'draft_saved_at' => now(),
            'submitted_at' => now(),
        ]);
    }
}
