<?php

namespace Tests\Feature\Week10;

use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageRegistrationService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Economics\Week10\Week10ConvergenceEconomicEngine;
use App\Domain\Economics\Week8\Week8EconomicEngine;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\DecisionFieldType;
use App\Enums\KpiSnapshotStatus;
use App\Enums\RankingSnapshotStatus;
use App\Enums\SectionSimulationWeekStatus;
use App\Enums\StandingValue;
use App\Enums\SubmissionStatus;
use App\Livewire\FacultyWeekControl;
use App\Models\CapitalAllocationDecision;
use App\Models\CapitalAllocationEvaluation;
use App\Models\CohortFeedbackEffect;
use App\Models\ConsequenceLink;
use App\Models\Counterparty;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\KpiSnapshot;
use App\Models\MemoDefinition;
use App\Models\RankingSnapshot;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\StandingState;
use App\Models\TeamSimulation;
use App\Models\Week10EconomicEvaluation;
use App\Models\Week5EconomicEvaluation;
use App\Models\Week8EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week10RuntimeIntegrationSmokeTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week10_runs_through_student_submission_and_faculty_execution_with_historical_state(): void
    {
        $context = $this->week10RuntimeContext();
        $packageType = app(AuthoritativeContentPackageManifest::class)->packageType(10);

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['week10']))
            ->assertOk()
            ->assertSee('halden_week10.xlsx', false)
            ->assertDontSee('faculty/halden_week10_FACULTY_SOLUTION.xlsx', false)
            ->assertInertia(fn ($page) => $page
                ->where('contentPackage.status', 'active')
                ->where('contentPackage.package_type', $packageType)
                ->where('decisionDefinition.fields.0.key', 'operating_posture')
                ->where('memoDefinition.title', 'Week 10 convergence memo')
                ->where('status.resolution_status', 'unresolved'));

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.decisions.submit', $context['week10']), [
                'definition_ulid' => $context['decisionDefinition']->ulid,
                'answers' => [
                    'operating_posture' => 'preserve liquidity and operational flexibility',
                ],
            ])
            ->assertRedirect();

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.memo.submit', $context['week10']), [
                'definition_ulid' => $context['memoDefinition']->ulid,
                'body' => 'We preserve liquidity because prior choices leave capex, hedge, partner, and cash constraints binding.',
            ])
            ->assertRedirect();

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyWeekControl::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('runtimeWeekId', $context['week10']->id)
            ->call('executeSelectedWeek')
            ->assertSee('completed')
            ->assertSee('deferred');

        $evaluation = Week10EconomicEvaluation::query()
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->where('section_simulation_week_id', $context['week10']->id)
            ->firstOrFail();

        $this->assertSame(Week10EconomicEvaluation::STATUS_CALCULATED, $evaluation->status);
        $this->assertSame(Week10ConvergenceEconomicEngine::ENGINE_IDENTIFIER, $evaluation->engine_identifier);
        $this->assertSame(Week10ConvergenceEconomicEngine::ENGINE_VERSION, $evaluation->engine_version);
        $this->assertSame('week_execution_service', $evaluation->evaluated_by_process);
        $this->assertSame('-0.023700', $evaluation->blendedDemandHitValue());
        $this->assertSame('-0.026220', $evaluation->singaporeDemandHitValue());
        $this->assertSame(5, $evaluation->binding_constraint_count);
        $this->assertSame([], $evaluation->unresolved_dependencies);

        $this->assertSame(7, KpiSnapshot::query()->where('section_simulation_week_id', $context['week10']->id)->count());
        $this->assertSame(7, KpiSnapshot::query()
            ->where('section_simulation_week_id', $context['week10']->id)
            ->where('status', KpiSnapshotStatus::Unavailable->value)
            ->whereNull('value')
            ->count());
        $ranking = RankingSnapshot::query()
            ->where('section_simulation_week_id', $context['week10']->id)
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->firstOrFail();
        $this->assertSame(RankingSnapshotStatus::Incomplete, $ranking->statusEnum());
        $this->assertNull($ranking->composite_score);
        $this->assertNull($ranking->rank);
        $this->assertSame(0, ConsequenceLink::query()->where('source_section_simulation_week_id', $context['week10']->id)->count());
        $this->assertSame(0, CohortFeedbackEffect::query()->where('source_section_simulation_week_id', $context['week10']->id)->count());

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['week10']))
            ->assertOk()
            ->assertDontSee('faculty/halden_week10_FACULTY_SOLUTION.xlsx', false)
            ->assertInertia(fn ($page) => $page
                ->where('decisionDefinition.status', 'submitted')
                ->where('memoDefinition.status', 'submitted')
                ->where('status.resolution_status', 'resolved'));
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     week10: SectionSimulationWeek,
     *     decisionDefinition: DecisionFormDefinition,
     *     memoDefinition: MemoDefinition,
     *     teamSimulation: TeamSimulation
     * }
     */
    private function week10RuntimeContext(): array
    {
        $graph = $this->tenantGraph('W10Smoke');
        $structure = $this->simulationStructure(10);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        $weeks = [];

        foreach ([4, 5, 6, 8, 10] as $weekNumber) {
            $definition = $structure['simulationWeeks']->firstWhere('week_number', $weekNumber);
            $weeks[$weekNumber] = $sectionSimulation->weeks()
                ->where('simulation_week_id', $definition->id)
                ->firstOrFail();
        }

        app(SimulationContentActivationService::class)->activate(
            app(AuthoritativeContentPackageRegistrationService::class)->register($weeks[10]->definition, 'week10-runtime-smoke-v1'),
        );

        $lifecycle = app(SimulationLifecycleService::class);
        $week10 = $lifecycle->transitionWeek($weeks[10], SectionSimulationWeekStatus::Released, $graph['faculty']);
        $week10 = $lifecycle->transitionWeek($week10->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());
        $weeks[10] = $week10;

        $decisionDefinition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $week10->simulation_version_id,
            'simulation_week_id' => $week10->simulation_week_id,
            'key' => 'week10_convergence_plan',
            'name' => 'Week 10 convergence plan',
            'version' => Week10ConvergenceEconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week10ConvergenceEconomicEngine::ENGINE_IDENTIFIER],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
            'field_key' => 'operating_posture',
            'label' => 'Operating posture',
            'field_type' => DecisionFieldType::ShortText,
            'is_required' => true,
            'display_order' => 1,
            'validation' => ['max_length' => 200],
        ]);

        $memoDefinition = MemoDefinition::factory()->create([
            'simulation_version_id' => $week10->simulation_version_id,
            'simulation_week_id' => $week10->simulation_week_id,
            'key' => 'week10_convergence_memo',
            'title' => 'Week 10 convergence memo',
            'version' => Week10ConvergenceEconomicEngine::ENGINE_VERSION,
            'is_required' => true,
            'character_limit' => 4000,
        ]);

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        $this->seedHistory($graph, $weeks, $teamSimulation);

        return compact('graph', 'sectionSimulation', 'week10', 'decisionDefinition', 'memoDefinition', 'teamSimulation');
    }

    /**
     * @param  array<string, mixed>  $graph
     * @param  array<int, SectionSimulationWeek>  $weeks
     */
    private function seedHistory(array $graph, array $weeks, TeamSimulation $teamSimulation): void
    {
        $week4Submission = $this->historicalSubmission($graph, $weeks[4], $teamSimulation, 'week4_transfer_pricing', ['transfer_price' => '73.70']);

        EconomicResolution::query()->create([
            'tenant_id' => $weeks[4]->tenant_id,
            'section_simulation_id' => $weeks[4]->section_simulation_id,
            'section_simulation_week_id' => $weeks[4]->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_submission_id' => $week4Submission->id,
            'economic_engine' => 'week4_transfer_pricing',
            'engine_version' => 'week4_transfer_pricing_v1',
            'input_snapshot' => ['transfer_price' => '73.70'],
            'output_snapshot' => ['week10_inherited_state' => ['br_reported_margin_strong' => true]],
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

        $this->seedWeek5Evaluation($graph, $weeks[5], $teamSimulation, '0.45');

        $decision = CapitalAllocationDecision::query()->create([
            'tenant_id' => $weeks[6]->tenant_id,
            'section_simulation_id' => $weeks[6]->section_simulation_id,
            'section_simulation_week_id' => $weeks[6]->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'submitted_by_user_id' => $graph['student']->id,
            'selected_projects' => [['key' => 'helix']],
            'rejected_projects' => [['key' => 'baton_rouge'], ['key' => 'rotterdam']],
            'context_snapshot' => ['discount_rate_percent' => '8.5'],
            'memo_references' => [],
            'submitted_at' => now(),
        ]);

        CapitalAllocationEvaluation::query()->create([
            'tenant_id' => $weeks[6]->tenant_id,
            'section_simulation_id' => $weeks[6]->section_simulation_id,
            'section_simulation_week_id' => $weeks[6]->id,
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
            'output_snapshot' => ['week10_inherited_state' => ['cancellable_capex_musd' => '120.0']],
            'evaluated_by_user_id' => $graph['faculty']->id,
            'evaluated_by_process' => 'test_fixture',
            'evaluated_at' => now(),
        ]);

        $counterparty = Counterparty::query()->create([
            'key' => 'straits_pacific',
            'name' => 'Straits Pacific',
            'sort_order' => 1,
            'is_active' => true,
            'metadata' => [],
        ]);

        StandingState::query()->create([
            'tenant_id' => $teamSimulation->tenant_id,
            'section_simulation_id' => $teamSimulation->section_simulation_id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'counterparty_id' => $counterparty->id,
            'state' => StandingValue::Strained->value,
            'reason' => 'Historical Week 10 fixture state.',
            'state_changed_at' => now(),
        ]);

        $week8Submission = $this->historicalSubmission($graph, $weeks[8], $teamSimulation, 'week8_opec_prediction', ['scenario' => 'fail']);

        Week8EconomicEvaluation::query()->create([
            'tenant_id' => $weeks[8]->tenant_id,
            'section_simulation_id' => $weeks[8]->section_simulation_id,
            'section_simulation_week_id' => $weeks[8]->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_submission_id' => $week8Submission->id,
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
            'output_snapshot' => ['week10_inherited_state' => ['cash_cushion_musd' => '85.0']],
            'evaluated_by_user_id' => $graph['faculty']->id,
            'evaluated_by_process' => 'test_fixture',
            'evaluated_at' => now(),
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
