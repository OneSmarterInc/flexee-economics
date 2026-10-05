<?php

namespace Tests\Feature\Economics;

use App\Domain\Consequences\ConsequenceService;
use App\Domain\Consequences\DerivedWeek10ConstraintService;
use App\Domain\Consequences\KpiConsequenceDefinitionCatalog;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageRegistrationService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Economics\Week10\Week10ConvergenceEconomicEngine;
use App\Domain\Economics\Week10\Week10EconomicEvaluationService;
use App\Domain\Economics\Week10\Week10EconomicResult;
use App\Domain\Economics\Week10\Week10ReferencePackage;
use App\Domain\Economics\Week8\Week8EconomicEngine;
use App\Domain\Economics\Week9\Week9EconomicEngine;
use App\Domain\Execution\WeekExecutionService;
use App\Enums\KpiSnapshotStatus;
use App\Enums\RankingSnapshotStatus;
use App\Enums\StandingValue;
use App\Enums\SubmissionStatus;
use App\Models\CapitalAllocationDecision;
use App\Models\CapitalAllocationEvaluation;
use App\Models\CohortFeedbackEffect;
use App\Models\ConsequenceDefinition;
use App\Models\Counterparty;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\Enrollment;
use App\Models\KpiSnapshot;
use App\Models\RankingSnapshot;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\StandingState;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use App\Models\Week10EconomicEvaluation;
use App\Models\Week5EconomicEvaluation;
use App\Models\Week8EconomicEvaluation;
use App\Models\Week9EconomicEvaluation;
use App\Models\WeekExecutionRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class MultiWeekHistoricalIntegrationTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    private const GOLDEN_RELATIVE_TOLERANCE = 1e-3;

    private const GOLDEN_ABSOLUTE_TOLERANCE = 1e-5;

    public function test_week10_consumes_persisted_history_and_matches_golden_outputs(): void
    {
        $context = $this->historicalContext();
        $this->seedHistoricalChain($context, $context['teamSimulation'], $this->constrainedState());
        $submission = $this->week10Submission($context, $context['teamSimulation']);

        $evaluation = app(Week10EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);

        $this->assertSame(Week10EconomicEvaluation::STATUS_CALCULATED, $evaluation->status);
        $this->assertGoldenOutputs($evaluation);
        $this->assertSame(5, $evaluation->binding_constraint_count);
        $this->assertSame([], $evaluation->unresolved_dependencies);
        $this->assertSame('consequence_link.week6_cancellable_capex_musd', $evaluation->inherited_state_snapshot['dependencies']['cancellable_capex_musd']['source_entity']);
        $this->assertSame('consequence_link.week5_hedge_coverage', $evaluation->inherited_state_snapshot['dependencies']['crude_hedge_coverage']['source_entity']);
        $this->assertSame('consequence_link.week4_tp_delacroix_cover', $evaluation->inherited_state_snapshot['dependencies']['br_reported_margin_strong']['source_entity']);
        $this->assertSame('standing_state', $evaluation->inherited_state_snapshot['dependencies']['straits_pacific_standing']['source_entity']);
        $this->assertSame('consequence_link.week8_cash_cushion_musd', $evaluation->inherited_state_snapshot['dependencies']['cash_cushion_musd']['source_entity']);
    }

    public function test_disciplined_reference_history_produces_zero_binding_constraints(): void
    {
        $context = $this->historicalContext('Disciplined');
        $this->seedHistoricalChain($context, $context['teamSimulation'], $this->disciplinedState());
        $submission = $this->week10Submission($context, $context['teamSimulation']);

        $evaluation = app(Week10EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);

        $this->assertSame(Week10EconomicEvaluation::STATUS_CALCULATED, $evaluation->status);
        $this->assertGoldenOutputs($evaluation);
        $this->assertSame(0, $evaluation->binding_constraint_count);
        $this->assertSame('310.0', $evaluation->inherited_state_snapshot['values']['cancellable_capex_musd']);
        $this->assertSame('0.700000', $evaluation->inherited_state_snapshot['values']['crude_hedge_coverage']);
        $this->assertFalse($evaluation->inherited_state_snapshot['values']['br_reported_margin_strong']);
        $this->assertSame('cooperative', $evaluation->inherited_state_snapshot['values']['straits_pacific_standing']);
        $this->assertSame('240.0', $evaluation->inherited_state_snapshot['values']['cash_cushion_musd']);
    }

    public function test_changing_persisted_historical_state_changes_week10_inherited_context(): void
    {
        $disciplined = $this->historicalContext('MutationA');
        $this->seedHistoricalChain($disciplined, $disciplined['teamSimulation'], $this->disciplinedState());
        $disciplinedEvaluation = app(Week10EconomicEvaluationService::class)->evaluate(
            $this->week10Submission($disciplined, $disciplined['teamSimulation']),
            $disciplined['graph']['faculty'],
        );

        $constrained = $this->historicalContext('MutationB');
        $this->seedHistoricalChain($constrained, $constrained['teamSimulation'], $this->constrainedState());
        $constrainedEvaluation = app(Week10EconomicEvaluationService::class)->evaluate(
            $this->week10Submission($constrained, $constrained['teamSimulation']),
            $constrained['graph']['faculty'],
        );

        $this->assertSame(0, $disciplinedEvaluation->binding_constraint_count);
        $this->assertSame(5, $constrainedEvaluation->binding_constraint_count);
        $this->assertSame('310.0', $disciplinedEvaluation->inherited_state_snapshot['values']['cancellable_capex_musd']);
        $this->assertSame('120.0', $constrainedEvaluation->inherited_state_snapshot['values']['cancellable_capex_musd']);
        $this->assertSame('0.700000', $disciplinedEvaluation->inherited_state_snapshot['values']['crude_hedge_coverage']);
        $this->assertSame('0.450000', $constrainedEvaluation->inherited_state_snapshot['values']['crude_hedge_coverage']);
    }

    public function test_missing_dependency_returns_unresolved_dependency_without_defaulting(): void
    {
        $context = $this->historicalContext('MissingW6');
        $this->seedHistoricalChain($context, $context['teamSimulation'], $this->constrainedState(), omit: ['week6']);
        $submission = $this->week10Submission($context, $context['teamSimulation']);

        $evaluation = app(Week10EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);

        $this->assertSame(Week10EconomicResult::STATUS_UNRESOLVED_DEPENDENCY, $evaluation->status);
        $this->assertContains('cancellable_capex_musd', $evaluation->unresolved_dependencies);
        $this->assertNull($evaluation->binding_constraint_count);
        $this->assertNull($evaluation->inherited_state_snapshot['values']['cancellable_capex_musd']);
    }

    public function test_wrong_team_and_tenant_history_do_not_satisfy_week10_dependencies(): void
    {
        $context = $this->historicalContext('WrongTeam', createOtherTeam: true);
        $this->seedHistoricalChain($context, $context['otherTeamSimulation'], $this->constrainedState());
        $foreign = $this->historicalContext('ForeignTenant');
        $this->seedHistoricalChain($foreign, $foreign['teamSimulation'], $this->constrainedState());
        $submission = $this->week10Submission($context, $context['teamSimulation']);

        $evaluation = app(Week10EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);

        $this->assertSame(Week10EconomicResult::STATUS_UNRESOLVED_DEPENDENCY, $evaluation->status);
        $this->assertEqualsCanonicalizing([
            'cancellable_capex_musd',
            'crude_hedge_coverage',
            'br_reported_margin_strong',
            'straits_pacific_standing',
            'cash_cushion_musd',
        ], $evaluation->unresolved_dependencies);
    }

    public function test_wrong_week_records_do_not_masquerade_as_week10_dependencies(): void
    {
        $context = $this->historicalContext('WrongWeek');
        $this->seedHistoricalChain($context, $context['teamSimulation'], $this->constrainedState(), omit: ['week8']);
        $this->seedWeek9EvaluationWithWeek10CashValue($context, $context['teamSimulation']);
        $submission = $this->week10Submission($context, $context['teamSimulation']);

        $evaluation = app(Week10EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);

        $this->assertSame(Week10EconomicResult::STATUS_UNRESOLVED_DEPENDENCY, $evaluation->status);
        $this->assertContains('cash_cushion_musd', $evaluation->unresolved_dependencies);
        $this->assertNull($evaluation->inherited_state_snapshot['values']['cash_cushion_musd']);
    }

    public function test_week10_evaluation_snapshot_remains_immutable_after_later_state_changes(): void
    {
        $context = $this->historicalContext('Immutable');
        $this->seedHistoricalChain($context, $context['teamSimulation'], $this->constrainedState());
        $submission = $this->week10Submission($context, $context['teamSimulation']);
        $evaluation = app(Week10EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);

        StandingState::query()
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->firstOrFail()
            ->forceFill([
                'state' => StandingValue::Cooperative->value,
                'reason' => 'Later change after Week 10 evaluation.',
                'state_changed_at' => now()->addDay(),
            ])
            ->save();

        $fresh = $evaluation->refresh();

        $this->assertSame('strained', $fresh->inherited_state_snapshot['values']['straits_pacific_standing']);
        $this->assertSame(5, $fresh->binding_constraint_count);
        $this->assertSame(Week10ConvergenceEconomicEngine::ENGINE_VERSION, $fresh->engine_version);
        $this->assertSame('1.0.1', $fresh->package_version);
    }

    public function test_week_execution_service_runs_week10_using_assembled_history(): void
    {
        $context = $this->historicalContext('Execution');
        $this->activateWeek10Package($context['weeks'][10]);
        $this->seedHistoricalChain($context, $context['teamSimulation'], $this->constrainedState());
        $this->week10Submission($context, $context['teamSimulation']);

        $record = app(WeekExecutionService::class)->execute(
            $context['weeks'][10],
            $context['graph']['faculty'],
            app(AuthoritativeContentPackageManifest::class)->packageType(10),
        );

        $evaluation = Week10EconomicEvaluation::query()
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->where('section_simulation_week_id', $context['weeks'][10]->id)
            ->firstOrFail();

        $this->assertSame(WeekExecutionRecord::STATUS_COMPLETED, $record->status);
        $this->assertSame(1, $record->outputs['resolve_decisions']['week10_economic_evaluation_count']);
        $this->assertSame(1, $record->outputs['resolve_decisions']['evaluation_status_counts'][Week10EconomicEvaluation::STATUS_CALCULATED]);
        $this->assertSame('week_execution_service', $evaluation->evaluated_by_process);
        $this->assertSame(7, KpiSnapshot::query()->where('section_simulation_week_id', $context['weeks'][10]->id)->count());
        $this->assertSame(7, KpiSnapshot::query()
            ->where('section_simulation_week_id', $context['weeks'][10]->id)
            ->where('status', KpiSnapshotStatus::Unavailable->value)
            ->whereNull('value')
            ->count());
        $ranking = RankingSnapshot::query()
            ->where('section_simulation_week_id', $context['weeks'][10]->id)
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->firstOrFail();
        $this->assertSame(RankingSnapshotStatus::Incomplete, $ranking->statusEnum());
        $this->assertNull($ranking->composite_score);
        $this->assertNull($ranking->rank);
        $this->assertSame(0, CohortFeedbackEffect::query()->where('source_section_simulation_week_id', $context['weeks'][10]->id)->count());
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     weeks: array<int, SectionSimulationWeek>,
     *     teamSimulation: TeamSimulation,
     *     otherTeamSimulation?: TeamSimulation
     * }
     */
    private function historicalContext(string $suffix = 'A', bool $createOtherTeam = false): array
    {
        $graph = $this->tenantGraph($suffix);

        if ($createOtherTeam) {
            $otherStudent = User::factory()->student()->create([
                'tenant_id' => $graph['tenant']->id,
                'email' => 'other-student-'.$suffix.'@example.test',
            ]);
            $otherTeam = Team::factory()->create([
                'tenant_id' => $graph['tenant']->id,
                'section_id' => $graph['section']->id,
                'name' => 'Other Team '.$suffix,
                'slug' => 'other-team-'.strtolower($suffix),
            ]);
            Enrollment::query()->create([
                'tenant_id' => $graph['tenant']->id,
                'section_id' => $graph['section']->id,
                'user_id' => $otherStudent->id,
                'status' => 'active',
            ]);
            TeamMember::query()->create([
                'tenant_id' => $graph['tenant']->id,
                'team_id' => $otherTeam->id,
                'user_id' => $otherStudent->id,
            ]);
        }

        $structure = $this->simulationStructure(10);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        $weeks = [];

        foreach ([4, 5, 6, 8, 9, 10] as $weekNumber) {
            $definition = $structure['simulationWeeks']->firstWhere('week_number', $weekNumber);
            $weeks[$weekNumber] = $sectionSimulation->weeks()
                ->where('simulation_week_id', $definition->id)
                ->firstOrFail();
        }

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        $context = compact('graph', 'sectionSimulation', 'weeks', 'teamSimulation');

        if ($createOtherTeam) {
            /** @var TeamSimulation $otherTeamSimulation */
            $otherTeamSimulation = $sectionSimulation->teamSimulations()
                ->where('team_id', $otherTeam->id)
                ->firstOrFail();
            $context['otherTeamSimulation'] = $otherTeamSimulation;
        }

        return $context;
    }

    /**
     * @return array<string, string|bool>
     */
    private function constrainedState(): array
    {
        return [
            'cancellable_capex_musd' => '120.0',
            'crude_hedge_coverage' => '0.45',
            'br_reported_margin_strong' => true,
            'straits_pacific_standing' => StandingValue::Strained->value,
            'cash_cushion_musd' => '85.0',
        ];
    }

    /**
     * @return array<string, string|bool>
     */
    private function disciplinedState(): array
    {
        return [
            'cancellable_capex_musd' => '310.0',
            'crude_hedge_coverage' => '0.7',
            'br_reported_margin_strong' => false,
            'straits_pacific_standing' => StandingValue::Cooperative->value,
            'cash_cushion_musd' => '240.0',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, string|bool>  $state
     * @param  list<string>  $omit
     */
    private function seedHistoricalChain(array $context, TeamSimulation $teamSimulation, array $state, array $omit = []): void
    {
        if (! in_array('week4', $omit, true)) {
            $this->seedWeek4Resolution($context, $teamSimulation, (bool) $state['br_reported_margin_strong']);
        }

        if (! in_array('week5', $omit, true)) {
            $this->seedWeek5Evaluation($context, $teamSimulation, (string) $state['crude_hedge_coverage']);
        }

        if (! in_array('week6', $omit, true)) {
            $this->seedWeek6Evaluation($context, $teamSimulation, (string) $state['cancellable_capex_musd']);
        }

        if (! in_array('standing', $omit, true)) {
            $this->seedStanding($teamSimulation, (string) $state['straits_pacific_standing']);
        }

        if (! in_array('week8', $omit, true)) {
            $this->seedWeek8Evaluation($context, $teamSimulation, (string) $state['cash_cushion_musd']);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function seedWeek4Resolution(array $context, TeamSimulation $teamSimulation, bool $strong): void
    {
        $week = $context['weeks'][4];
        $submission = $this->historicalSubmission($context, $week, $teamSimulation, 'week4_transfer_pricing', ['transfer_price' => '73.70']);

        $resolution = EconomicResolution::query()->create([
            'tenant_id' => $week->tenant_id,
            'section_simulation_id' => $week->section_simulation_id,
            'section_simulation_week_id' => $week->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_submission_id' => $submission->id,
            'economic_engine' => 'week4_transfer_pricing',
            'engine_version' => 'week4_transfer_pricing_v1',
            'input_snapshot' => ['transfer_price' => '73.70'],
            'output_snapshot' => [],
            'transfer_price' => '73.700',
            'integrated_margin' => '76.750',
            'upstream_margin' => '58.050',
            'refining_margin' => '18.700',
            'upstream_vs_target' => '0.000',
            'refining_vs_target' => $strong ? '14.650' : '-12.850',
            'geneva_gap' => '0.000',
            'geneva_capture_per_bbl' => $strong ? '9.625' : '0.000',
            'geneva_max_volume_bbl_day' => '0.000',
            'resolved_by_user_id' => $context['graph']['faculty']->id,
            'resolved_by_process' => 'multi_week_regression_fixture',
            'resolved_at' => now(),
        ]);

        app(DerivedWeek10ConstraintService::class)->resolveWeek4Consequences($resolution, $context['graph']['faculty']);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function seedWeek5Evaluation(array $context, TeamSimulation $teamSimulation, string $coverage): void
    {
        $week = $context['weeks'][5];
        $submission = $this->historicalSubmission($context, $week, $teamSimulation, 'week5_currency_exposure', [
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
            'evaluated_by_user_id' => $context['graph']['faculty']->id,
            'evaluated_by_process' => 'multi_week_regression_fixture',
            'evaluated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function seedWeek6Evaluation(array $context, TeamSimulation $teamSimulation, string $cancellableCapex): void
    {
        $week = $context['weeks'][6];
        $decision = CapitalAllocationDecision::query()->create([
            'tenant_id' => $week->tenant_id,
            'section_simulation_id' => $week->section_simulation_id,
            'section_simulation_week_id' => $week->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'submitted_by_user_id' => $context['graph']['student']->id,
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
            'output_snapshot' => [],
            'evaluated_by_user_id' => $context['graph']['faculty']->id,
            'evaluated_by_process' => 'multi_week_regression_fixture',
            'evaluated_at' => now(),
        ]);

        $evaluation = CapitalAllocationEvaluation::query()->latest('id')->firstOrFail();
        $this->linkConsequence(
            teamSimulation: $teamSimulation,
            definition: app(KpiConsequenceDefinitionCatalog::class)->cancellableCapex(),
            source: $evaluation,
            target: $evaluation,
            sourceWeek: $week,
            targetWeek: $context['weeks'][10],
            value: $cancellableCapex,
        );
    }

    private function seedStanding(TeamSimulation $teamSimulation, string $state): void
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
            'state' => $state,
            'reason' => 'Multi-week regression fixture state.',
            'state_changed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function seedWeek8Evaluation(array $context, TeamSimulation $teamSimulation, string $cashCushion): void
    {
        $week = $context['weeks'][8];
        $submission = $this->historicalSubmission($context, $week, $teamSimulation, 'week8_opec_prediction', ['scenario' => 'fail']);

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
            'output_snapshot' => [],
            'evaluated_by_user_id' => $context['graph']['faculty']->id,
            'evaluated_by_process' => 'multi_week_regression_fixture',
            'evaluated_at' => now(),
        ]);

        $evaluation = Week8EconomicEvaluation::query()->latest('id')->firstOrFail();
        $this->linkConsequence(
            teamSimulation: $teamSimulation,
            definition: app(KpiConsequenceDefinitionCatalog::class)->cashCushion(),
            source: $evaluation,
            target: $evaluation,
            sourceWeek: $week,
            targetWeek: $context['weeks'][10],
            value: $cashCushion,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function seedWeek9EvaluationWithWeek10CashValue(array $context, TeamSimulation $teamSimulation): void
    {
        $week = $context['weeks'][9];
        $submission = $this->historicalSubmission($context, $week, $teamSimulation, 'week9_rebrand', ['nonfuel_state_key' => 'base']);

        Week9EconomicEvaluation::query()->create([
            'tenant_id' => $week->tenant_id,
            'section_simulation_id' => $week->section_simulation_id,
            'section_simulation_week_id' => $week->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => Week9EconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week9EconomicEngine::ENGINE_VERSION,
            'package_version' => '1.0.0-draft',
            'status' => Week9EconomicEvaluation::STATUS_CALCULATED,
            'nonfuel_state_key' => 'base',
            'selected_rebrand_markets' => ['gulf_secondary'],
            'cost_per_site' => '0.250000',
            'partial_gain_musd' => '22.680000',
            'partial_cost_musd' => '12.000000',
            'partial_payback_years' => '8.541500',
            'full_net_gain_musd' => '7.695000',
            'pricewar_partial_payback_years' => '9.440591',
            'input_snapshot' => [],
            'output_snapshot' => ['week10_inherited_state' => ['cash_cushion_musd' => '85.0']],
            'evaluated_by_user_id' => $context['graph']['faculty']->id,
            'evaluated_by_process' => 'wrong_week_fixture',
            'evaluated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function week10Submission(array $context, TeamSimulation $teamSimulation): DecisionSubmission
    {
        $week = $context['weeks'][10];
        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $week->simulation_version_id,
            'simulation_week_id' => $week->simulation_week_id,
            'key' => 'week10_convergence_plan',
            'name' => 'Week 10 convergence plan',
            'version' => Week10ConvergenceEconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week10ConvergenceEconomicEngine::ENGINE_IDENTIFIER],
        ]);

        return DecisionSubmission::query()->create([
            'tenant_id' => $week->tenant_id,
            'section_simulation_id' => $week->section_simulation_id,
            'section_simulation_week_id' => $week->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_form_definition_id' => $definition->id,
            'status' => SubmissionStatus::Submitted->value,
            'answers' => ['operating_posture' => 'liquidity_first'],
            'lock_version' => 1,
            'updated_by_user_id' => $context['graph']['student']->id,
            'submitted_by_user_id' => $context['graph']['student']->id,
            'draft_saved_at' => now(),
            'submitted_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $answers
     */
    private function historicalSubmission(array $context, SectionSimulationWeek $week, TeamSimulation $teamSimulation, string $key, array $answers): DecisionSubmission
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
            'updated_by_user_id' => $context['graph']['student']->id,
            'submitted_by_user_id' => $context['graph']['student']->id,
            'draft_saved_at' => now(),
            'submitted_at' => now(),
        ]);
    }

    private function activateWeek10Package(SectionSimulationWeek $week): void
    {
        app(SimulationContentActivationService::class)->activate(
            app(AuthoritativeContentPackageRegistrationService::class)->register($week->definition, 'week10-multi-week-regression-v1'),
        );
    }

    private function assertGoldenOutputs(Week10EconomicEvaluation $evaluation): void
    {
        $golden = Week10ReferencePackage::fromRepository()->inputs()->golden['results'];

        $this->assertWithinGoldenTolerance((float) $golden['demand_hit_gasoline'], $evaluation->getRawOriginal('gasoline_demand_hit'));
        $this->assertWithinGoldenTolerance((float) $golden['demand_hit_diesel'], $evaluation->getRawOriginal('diesel_demand_hit'));
        $this->assertWithinGoldenTolerance((float) $golden['demand_hit_jet'], $evaluation->getRawOriginal('jet_demand_hit'));
        $this->assertWithinGoldenTolerance((float) $golden['blended_demand_hit'], $evaluation->getRawOriginal('blended_demand_hit'));
        $this->assertWithinGoldenTolerance((float) $golden['refinery_hit_br'], $evaluation->getRawOriginal('baton_rouge_demand_hit'));
        $this->assertWithinGoldenTolerance((float) $golden['refinery_hit_rot'], $evaluation->getRawOriginal('rotterdam_demand_hit'));
        $this->assertWithinGoldenTolerance((float) $golden['refinery_hit_sing'], $evaluation->getRawOriginal('singapore_demand_hit'));
        $this->assertSame($golden['hardest_hit_refinery'], $evaluation->hardest_hit_refinery);
    }

    private function assertWithinGoldenTolerance(float $expected, mixed $actual): void
    {
        $actualFloat = (float) $actual;
        $delta = max(self::GOLDEN_ABSOLUTE_TOLERANCE, abs($expected) * self::GOLDEN_RELATIVE_TOLERANCE);

        $this->assertEqualsWithDelta($expected, $actualFloat, $delta);
    }

    private function linkConsequence(
        TeamSimulation $teamSimulation,
        ConsequenceDefinition $definition,
        Model $source,
        Model $target,
        SectionSimulationWeek $sourceWeek,
        SectionSimulationWeek $targetWeek,
        string $value,
    ): void {
        app(ConsequenceService::class)->createLink(
            teamSimulation: $teamSimulation,
            definition: $definition,
            source: $source,
            target: $target,
            explanation: 'Multi-week regression fixture consequence link.',
            sourceWeek: $sourceWeek,
            targetWeek: $targetWeek,
            actor: null,
            metadata: [
                'target_value' => $value,
                'package_version' => '1.0.1',
            ],
        );
    }
}
