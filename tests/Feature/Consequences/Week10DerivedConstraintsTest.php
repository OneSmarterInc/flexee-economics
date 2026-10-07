<?php

namespace Tests\Feature\Consequences;

use App\Domain\Consequences\DerivedWeek10ConstraintService;
use App\Domain\Economics\Week10\Week10InheritedStateAssembler;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Enums\StandingValue;
use App\Enums\SubmissionStatus;
use App\Models\CapitalAllocationDecision;
use App\Models\CapitalAllocationEvaluation;
use App\Models\CapitalProject;
use App\Models\Counterparty;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\DiscountRateConsequence;
use App\Models\DiscountRateSchedule;
use App\Models\EconomicResolution;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\StandingState;
use App\Models\TeamSimulation;
use App\Models\Week8EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week10DerivedConstraintsTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_student_week10_constraint_values_are_rejected_from_week6_submission_path(): void
    {
        $context = $this->weekContext(6);
        $student = $context['graph']['student'];
        $this->capitalProject('helix');

        $this->actingAs($student)
            ->post(route('student.submissions.capital-allocation.submit', $context['week']), [
                'selected_project_keys' => ['helix'],
                'week10_inherited_state' => [
                    'cancellable_capex_musd' => '999',
                    'crude_hedge_coverage' => '1',
                ],
            ])
            ->assertSessionHasNoErrors();

        $decision = CapitalAllocationDecision::query()->firstOrFail();

        $this->assertArrayNotHasKey('week10_inherited_state', $decision->contextSnapshot());
    }

    public function test_week4_delacroix_cover_consequence_changes_with_refining_result(): void
    {
        $positive = $this->week4Resolution('A', '46.20', '14.65');
        $negative = $this->week4Resolution('B', '73.70', '-12.85');

        $service = app(DerivedWeek10ConstraintService::class);
        $service->resolveWeek4Consequences($positive['resolution'], $positive['graph']['faculty']);
        $service->resolveWeek4Consequences($negative['resolution'], $negative['graph']['faculty']);

        $this->assertSame('1', $service->latestConsequenceValue($positive['teamSimulation'], 'week4_tp_delacroix_cover')?->metadata['target_value']);
        $this->assertSame('0', $service->latestConsequenceValue($negative['teamSimulation'], 'week4_tp_delacroix_cover')?->metadata['target_value']);
    }

    public function test_whitaker_standing_derives_hedge_coverage(): void
    {
        $context = $this->teamContext();
        $service = app(DerivedWeek10ConstraintService::class);

        $this->standing($context['teamSimulation'], 'whitaker', StandingValue::Cooperative);
        $this->assertSame('0.700000', $service->resolveHedgeCoverage($context['teamSimulation'], $context['graph']['faculty'])?->metadata['target_value']);

        $context = $this->teamContext('B');
        $this->standing($context['teamSimulation'], 'whitaker', StandingValue::Guarded);
        $this->assertSame('0.450000', $service->resolveHedgeCoverage($context['teamSimulation'], $context['graph']['faculty'])?->metadata['target_value']);

        $context = $this->teamContext('C');
        $this->standing($context['teamSimulation'], 'whitaker', StandingValue::Hostile);
        $this->assertSame('0.250000', $service->resolveHedgeCoverage($context['teamSimulation'], $context['graph']['faculty'])?->metadata['target_value']);
    }

    public function test_cancellable_capex_uses_week6_envelope_and_helix_selection(): void
    {
        $context = $this->teamContext();
        $week6 = $this->runtimeWeek($context['sectionSimulation'], 6);
        $decision = $this->capitalDecision($context, $week6, [['key' => 'helix']]);
        $evaluation = CapitalAllocationEvaluation::query()->create([
            'tenant_id' => $context['graph']['tenant']->id,
            'section_simulation_id' => $context['sectionSimulation']->id,
            'section_simulation_week_id' => $week6->id,
            'team_simulation_id' => $context['teamSimulation']->id,
            'team_id' => $context['teamSimulation']->team_id,
            'capital_allocation_decision_id' => $decision->id,
            'engine_identifier' => 'test',
            'engine_version' => 'test',
            'package_version' => 'test',
            'status' => CapitalAllocationEvaluation::STATUS_CALCULATED,
            'portfolio_npv_musd' => '0',
            'portfolio_irr_percent' => '0',
            'capital_required_musd' => '880',
            'capital_envelope_feasible' => true,
            'input_snapshot' => ['selected_projects' => [['key' => 'helix']]],
            'output_snapshot' => ['selected_project_keys' => ['helix']],
            'evaluated_by_user_id' => $context['graph']['faculty']->id,
            'evaluated_by_process' => 'test',
            'evaluated_at' => now(),
        ]);
        $this->discountConsequence($context, $week6, 'disciplined', '1520');

        $link = app(DerivedWeek10ConstraintService::class)->resolveCancellableCapex($evaluation, $context['graph']['faculty']);

        $this->assertSame('192.000', $link?->metadata['target_value']);
    }

    public function test_cash_cushion_uses_prior_evaluated_state_and_missing_week7_or_week9_runtime_outputs_are_zero(): void
    {
        $context = $this->teamContext(weeks: 14);
        $week6 = $this->runtimeWeek($context['sectionSimulation'], 6);
        $week8 = $this->runtimeWeek($context['sectionSimulation'], 8);
        $decision = $this->capitalDecision($context, $week6, [['key' => 'helix']]);

        CapitalAllocationEvaluation::query()->create([
            'tenant_id' => $context['graph']['tenant']->id,
            'section_simulation_id' => $context['sectionSimulation']->id,
            'section_simulation_week_id' => $week6->id,
            'team_simulation_id' => $context['teamSimulation']->id,
            'team_id' => $context['teamSimulation']->team_id,
            'capital_allocation_decision_id' => $decision->id,
            'engine_identifier' => 'test',
            'engine_version' => 'test',
            'package_version' => 'test',
            'status' => CapitalAllocationEvaluation::STATUS_CALCULATED,
            'portfolio_npv_musd' => '0',
            'portfolio_irr_percent' => '0',
            'capital_required_musd' => '880',
            'capital_envelope_feasible' => true,
            'input_snapshot' => [],
            'output_snapshot' => [],
            'evaluated_by_user_id' => $context['graph']['faculty']->id,
            'evaluated_by_process' => 'test',
            'evaluated_at' => now(),
        ]);
        Week8EconomicEvaluation::query()->create([
            'tenant_id' => $context['graph']['tenant']->id,
            'section_simulation_id' => $context['sectionSimulation']->id,
            'section_simulation_week_id' => $week8->id,
            'team_simulation_id' => $context['teamSimulation']->id,
            'team_id' => $context['teamSimulation']->team_id,
            'decision_submission_id' => $this->submission($context, $week8, [])->id,
            'engine_identifier' => 'test',
            'engine_version' => 'test',
            'package_version' => 'test',
            'status' => Week8EconomicEvaluation::STATUS_CALCULATED,
            'expected_wti' => '0',
            'expected_upstream_impact_per_bbl' => '0',
            'expected_refining_crack' => '0',
            'realized_wti' => '91.00',
            'realized_upstream_impact_per_bbl' => '10',
            'realization_snapshot' => ['delta_wti' => '10'],
            'input_snapshot' => [],
            'output_snapshot' => [],
            'evaluated_by_user_id' => $context['graph']['faculty']->id,
            'evaluated_by_process' => 'test',
            'evaluated_at' => now(),
        ]);

        $link = app(DerivedWeek10ConstraintService::class)->resolveCashCushion($context['teamSimulation'], $context['graph']['faculty']);

        $this->assertSame('310.281', $link?->metadata['target_value']);
        $this->assertSame('0.000', $link?->metadata['week7_capacity_match']);
        $this->assertSame('0.000', $link?->metadata['week9_rebrand_cost_musd']);
    }

    public function test_straits_pacific_binding_follows_standing(): void
    {
        $context = $this->teamContext();
        $service = app(DerivedWeek10ConstraintService::class);

        $this->standing($context['teamSimulation'], 'straits_pacific', StandingValue::Cooperative);
        $this->assertSame('0', $service->resolveStraitsPacificFlex($context['teamSimulation'], $context['graph']['faculty'])?->metadata['target_value']);

        $context = $this->teamContext('B');
        $this->standing($context['teamSimulation'], 'straits_pacific', StandingValue::Strained);
        $this->assertSame('1', $service->resolveStraitsPacificFlex($context['teamSimulation'], $context['graph']['faculty'])?->metadata['target_value']);
    }

    public function test_week10_assembler_does_not_accept_student_entered_fallbacks(): void
    {
        $context = $this->weekContext(10);
        $submission = $this->submission($context, $context['week'], [
            'week10_inherited_state' => [
                'cancellable_capex_musd' => '999',
                'crude_hedge_coverage' => '1',
                'br_reported_margin_strong' => true,
                'cash_cushion_musd' => '999',
            ],
        ]);

        $state = app(Week10InheritedStateAssembler::class)->assemble($submission);

        $this->assertSame([
            'cancellable_capex_musd',
            'crude_hedge_coverage',
            'br_reported_margin_strong',
            'straits_pacific_standing',
            'cash_cushion_musd',
        ], $state->unresolvedDependencyKeys());
    }

    /**
     * @return array<string, mixed>
     */
    private function week4Resolution(string $suffix, string $transferPrice, string $refiningVsTarget): array
    {
        $context = $this->weekContext(4, $suffix);
        $submission = $this->submission($context, $context['week'], ['transfer_price' => $transferPrice]);
        $resolution = EconomicResolution::query()->create([
            'tenant_id' => $context['graph']['tenant']->id,
            'section_simulation_id' => $context['sectionSimulation']->id,
            'section_simulation_week_id' => $context['week']->id,
            'team_simulation_id' => $context['teamSimulation']->id,
            'team_id' => $context['teamSimulation']->team_id,
            'decision_submission_id' => $submission->id,
            'economic_engine' => 'test',
            'engine_version' => 'test',
            'input_snapshot' => [],
            'output_snapshot' => [],
            'transfer_price' => $transferPrice,
            'integrated_margin' => '0',
            'upstream_margin' => '0',
            'refining_margin' => '0',
            'upstream_vs_target' => '0',
            'refining_vs_target' => $refiningVsTarget,
            'geneva_gap' => '0',
            'geneva_capture_per_bbl' => $transferPrice === '73.70' ? '1' : '0',
            'geneva_max_volume_bbl_day' => '0',
            'resolved_by_user_id' => $context['graph']['faculty']->id,
            'resolved_by_process' => 'test',
            'resolved_at' => now(),
        ]);

        return [...$context, 'submission' => $submission, 'resolution' => $resolution];
    }

    /**
     * @return array<string, mixed>
     */
    private function weekContext(int $weekNumber, string $suffix = 'A', int $weeks = 14): array
    {
        $context = $this->teamContext($suffix, $weeks);
        $week = $this->runtimeWeek($context['sectionSimulation'], $weekNumber);
        $service = app(SimulationLifecycleService::class);
        $service->transitionWeek($week, SectionSimulationWeekStatus::Released, $context['graph']['faculty']);
        $week = $service->transitionWeek($week->refresh(), SectionSimulationWeekStatus::Open, $context['graph']['faculty'], now()->addDay());
        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $week->simulation_version_id,
            'simulation_week_id' => $week->simulation_week_id,
            'key' => 'test_week_'.$weekNumber,
            'name' => 'Test Week '.$weekNumber,
        ]);
        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $definition->id,
            'field_key' => 'test_value',
            'label' => 'Test value',
            'field_type' => DecisionFieldType::ShortText,
            'is_required' => false,
            'display_order' => 1,
            'validation' => [],
        ]);

        return [...$context, 'week' => $week, 'decisionDefinition' => $definition];
    }

    /**
     * @return array<string, mixed>
     */
    private function teamContext(string $suffix = 'A', int $weeks = 14): array
    {
        $graph = $this->tenantGraph($suffix);
        $structure = $this->simulationStructure($weeks);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        $teamSimulation = $sectionSimulation->teamSimulations()->where('team_id', $graph['team']->id)->firstOrFail();

        return compact('graph', 'structure', 'sectionSimulation', 'teamSimulation');
    }

    private function runtimeWeek(SectionSimulation $sectionSimulation, int $weekNumber): SectionSimulationWeek
    {
        return $sectionSimulation->weeks()
            ->whereHas('definition', fn ($query) => $query->where('week_number', $weekNumber))
            ->firstOrFail();
    }

    private function submission(array $context, SectionSimulationWeek $week, array $answers): DecisionSubmission
    {
        $definition = DecisionFormDefinition::query()
            ->where('simulation_week_id', $week->simulation_week_id)
            ->first()
            ?? DecisionFormDefinition::factory()->create([
                'simulation_version_id' => $week->simulation_version_id,
                'simulation_week_id' => $week->simulation_week_id,
            ]);

        return DecisionSubmission::query()->create([
            'tenant_id' => $context['graph']['tenant']->id,
            'section_simulation_id' => $context['sectionSimulation']->id,
            'section_simulation_week_id' => $week->id,
            'team_simulation_id' => $context['teamSimulation']->id,
            'team_id' => $context['teamSimulation']->team_id,
            'decision_form_definition_id' => $definition->id,
            'status' => SubmissionStatus::Submitted->value,
            'answers' => $answers,
            'lock_version' => 1,
            'submitted_at' => now(),
        ]);
    }

    private function standing(TeamSimulation $teamSimulation, string $counterpartyKey, StandingValue $state): StandingState
    {
        $counterparty = Counterparty::query()->firstOrCreate(['key' => $counterpartyKey], [
            'name' => str($counterpartyKey)->replace('_', ' ')->title()->toString(),
            'sort_order' => 1,
            'is_active' => true,
            'metadata' => [],
        ]);

        return StandingState::query()->create([
            'tenant_id' => $teamSimulation->tenant_id,
            'section_simulation_id' => $teamSimulation->section_simulation_id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'counterparty_id' => $counterparty->id,
            'state' => $state->value,
            'reason' => 'test',
            'state_changed_at' => now(),
        ]);
    }

    private function discountConsequence(array $context, SectionSimulationWeek $targetWeek, string $classification, string $envelope): void
    {
        $sourceWeek = $this->runtimeWeek($context['sectionSimulation'], 4);
        $sourceSubmission = $this->submission($context, $sourceWeek, ['transfer_price' => '18.70']);
        $sourceResolution = EconomicResolution::query()->create([
            'tenant_id' => $context['graph']['tenant']->id,
            'section_simulation_id' => $context['sectionSimulation']->id,
            'section_simulation_week_id' => $sourceWeek->id,
            'team_simulation_id' => $context['teamSimulation']->id,
            'team_id' => $context['teamSimulation']->team_id,
            'decision_submission_id' => $sourceSubmission->id,
            'economic_engine' => 'test',
            'engine_version' => 'test',
            'input_snapshot' => [],
            'output_snapshot' => [],
            'transfer_price' => '18.70',
            'integrated_margin' => '0',
            'upstream_margin' => '0',
            'refining_margin' => '0',
            'upstream_vs_target' => '0',
            'refining_vs_target' => '0',
            'geneva_gap' => '0',
            'geneva_capture_per_bbl' => '0',
            'geneva_max_volume_bbl_day' => '0',
            'resolved_by_user_id' => $context['graph']['faculty']->id,
            'resolved_by_process' => 'test',
            'resolved_at' => now(),
        ]);
        $schedule = DiscountRateSchedule::query()->create([
            'key' => 'week4_to_week6_discount_rate',
            'version' => 'test',
            'name' => 'test',
            'source_week_number' => 4,
            'target_week_number' => 6,
            'classification_rules' => [],
            'classification_outcomes' => [],
            'is_active' => true,
        ]);

        DiscountRateConsequence::query()->create([
            'tenant_id' => $context['graph']['tenant']->id,
            'section_simulation_id' => $context['sectionSimulation']->id,
            'source_section_simulation_week_id' => $sourceWeek->id,
            'target_section_simulation_week_id' => $targetWeek->id,
            'team_simulation_id' => $context['teamSimulation']->id,
            'team_id' => $context['teamSimulation']->team_id,
            'economic_resolution_id' => $sourceResolution->id,
            'discount_rate_schedule_id' => $schedule->id,
            'schedule_key' => 'week4_to_week6_discount_rate',
            'schedule_version' => 'test',
            'status' => DiscountRateConsequence::STATUS_RESOLVED,
            'classification' => $classification,
            'discount_rate_percent' => '6.500',
            'capital_envelope_musd' => $envelope,
            'input_snapshot' => [],
            'result_snapshot' => [],
            'resolved_by_user_id' => $context['graph']['faculty']->id,
            'resolved_at' => now(),
        ]);
    }

    private function capitalProject(string $key): CapitalProject
    {
        return CapitalProject::query()->firstOrCreate([
            'key' => $key,
            'version' => 'test',
        ], [
            'name' => 'Project '.$key,
            'category' => 'test',
            'risk_class' => 'test',
            'cash_flow_reference' => 'test',
            'required_inputs' => [],
            'metadata' => [],
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $selected
     */
    private function capitalDecision(array $context, SectionSimulationWeek $week6, array $selected): CapitalAllocationDecision
    {
        return CapitalAllocationDecision::query()->create([
            'tenant_id' => $context['graph']['tenant']->id,
            'section_simulation_id' => $context['sectionSimulation']->id,
            'section_simulation_week_id' => $week6->id,
            'team_simulation_id' => $context['teamSimulation']->id,
            'team_id' => $context['teamSimulation']->team_id,
            'submitted_by_user_id' => $context['graph']['student']->id,
            'selected_projects' => $selected,
            'rejected_projects' => [],
            'context_snapshot' => ['status' => 'available'],
            'memo_references' => [],
            'submitted_at' => now(),
        ]);
    }
}
