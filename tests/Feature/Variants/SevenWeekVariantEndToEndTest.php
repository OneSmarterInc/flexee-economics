<?php

namespace Tests\Feature\Variants;

use App\Domain\CausalTrace\CausalTraceService;
use App\Domain\CohortFeedback\Window1CohortResponseFunctionCatalog;
use App\Domain\CohortFeedback\Window2CohortResponseFunctionCatalog;
use App\Domain\CohortFeedback\Window3CohortResponseFunctionCatalog;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageRegistrationService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Content\Week4\Week4ContentPackageRegistrationService;
use App\Domain\Content\Week6\Week6ContentPackageRegistrationService;
use App\Domain\Content\Week8\Week8ContentPackageRegistrationService;
use App\Domain\Economics\Week1\Week1EconomicEngine;
use App\Domain\Economics\Week10\Week10ConvergenceEconomicEngine;
use App\Domain\Economics\Week12\Week12EconomicEngine;
use App\Domain\Economics\Week8\Week8EconomicEngine;
use App\Domain\Execution\WeekExecutionService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Enums\StandingValue;
use App\Livewire\FacultyOperationsDashboard;
use App\Livewire\FacultyWeekControl;
use App\Models\BoardDefenseAssessment;
use App\Models\CapitalAllocationEvaluation;
use App\Models\CapitalProject;
use App\Models\CohortDecisionAggregate;
use App\Models\CohortFeedbackEffect;
use App\Models\Counterparty;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\DiscountRateConsequence;
use App\Models\DiscountRateSchedule;
use App\Models\EconomicResolution;
use App\Models\MemoDefinition;
use App\Models\Seat;
use App\Models\SectionSimulationWeek;
use App\Models\Simulation;
use App\Models\SimulationSeatAssignment;
use App\Models\SimulationVariant;
use App\Models\SimulationVersion;
use App\Models\SimulationWeek;
use App\Models\StandingState;
use App\Models\TeamSimulation;
use App\Models\Week10EconomicEvaluation;
use App\Models\Week12EconomicEvaluation;
use App\Models\Week1EconomicEvaluation;
use App\Models\Week8EconomicEvaluation;
use App\Models\WeekContentVersion;
use App\Models\WeekExecutionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class SevenWeekVariantEndToEndTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    private const SEQUENCE = [1, 4, 6, 8, 10, 12, 14];

    public function test_seven_week_variant_runs_authoritative_compressed_sequence_end_to_end(): void
    {
        $context = $this->sevenWeekContext();
        $this->activateVariantPackages($context['weeks']);
        $definitions = $this->createDecisionAndMemoDefinitions($context['weeks']);
        $this->seedWeek6PackageProjects();
        $this->createInitialSeatAssignment($context);

        $this->assertSame(self::SEQUENCE, $context['weeks']->keys()->values()->all());

        $this->actingAs($context['graph']['student'])
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('journey.simulations.0.variant', 'Seven-week compressed variant')
                ->where('journey.simulations.0.progress.total', 7)
                ->where('journey.simulations.0.timeline.0.number', 1)
                ->where('journey.simulations.0.timeline.1.number', 4)
                ->where('journey.simulations.0.timeline.6.number', 14));

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyOperationsDashboard::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->assertSee('Week 1')
            ->assertSee('Week 14')
            ->assertDontSee('Week 5');

        $week1Submission = $this->submitDecisionWeek(
            $context,
            $context['weeks'][1],
            $definitions[1]['decision'],
            $definitions[1]['memo'],
            [
                'permian_rig_count' => '12',
                'rotterdam_review_posture' => 'accelerate_review',
                'first_meeting_choice' => 'vestergaard',
            ],
            'Week 1 asset register memo for the compressed variant.',
        );
        $this->executeThroughFacultyControl($context, $context['weeks'][1]);
        $this->assertSame(Week1EconomicEvaluation::STATUS_CALCULATED, Week1EconomicEvaluation::query()->sole()->status);

        $week4Submission = $this->submitDecisionWeek(
            $context,
            $context['weeks'][4],
            $definitions[4]['decision'],
            $definitions[4]['memo'],
            [
                'transfer_price' => '46.20',
                'br_reported_margin_strong' => true,
            ],
            'Week 4 transfer-price memo for the single seven-week lag.',
        );
        $this->discountRateSchedule();
        $this->executeThroughFacultyControl($context, $context['weeks'][4]);
        $resolution = EconomicResolution::query()
            ->where('decision_submission_id', $week4Submission->id)
            ->firstOrFail();
        $discountConsequence = DiscountRateConsequence::query()
            ->where('economic_resolution_id', $resolution->id)
            ->firstOrFail();
        $this->assertSame(DiscountRateConsequence::STATUS_RESOLVED, $discountConsequence->status);
        $this->assertSame('8.500', $discountConsequence->discount_rate_percent);
        $this->assertSame(1, DiscountRateConsequence::query()->count());

        $this->openWeek($context, $context['weeks'][6]);
        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.capital-allocation.submit', $context['weeks'][6]), [
                'selected_project_keys' => ['baton_rouge', 'helix'],
                'week10_inherited_state' => [
                    'cancellable_capex_musd' => '120.0',
                    'crude_hedge_coverage' => '0.45',
                ],
            ])
            ->assertRedirect();
        $this->submitMemo($context, $context['weeks'][6], $definitions[6]['memo'], 'Week 6 memo with capital allocation and folded currency coverage.');
        $this->executeThroughFacultyControl($context, $context['weeks'][6]);
        $week6Evaluation = CapitalAllocationEvaluation::query()->firstOrFail();
        $this->assertSame('120.00', $week6Evaluation->output_snapshot['week10_inherited_state']['cancellable_capex_musd']);
        $this->assertSame('0.450000', $week6Evaluation->output_snapshot['week10_inherited_state']['crude_hedge_coverage']);

        $week8Submission = $this->submitDecisionWeek(
            $context,
            $context['weeks'][8],
            $definitions[8]['decision'],
            $definitions[8]['memo'],
            [
                'probability_holds_full' => '0.10',
                'probability_holds_partial' => '0.20',
                'probability_fails' => '0.70',
                'realized_scenario_key' => 'fails',
                'cash_cushion_musd' => '85.0',
            ],
            'Week 8 memo distinguishes prediction from realized OPEC scenario.',
        );
        $this->executeThroughFacultyControl($context, $context['weeks'][8]);
        $week8Evaluation = Week8EconomicEvaluation::query()->firstOrFail();
        $this->assertSame('85.00', $week8Evaluation->output_snapshot['week10_inherited_state']['cash_cushion_musd']);

        $this->rotateSeatForWeek10($context);
        $this->seedStraitsPacificStanding($context['teamSimulation']);

        $week10Submission = $this->submitDecisionWeek(
            $context,
            $context['weeks'][10],
            $definitions[10]['decision'],
            $definitions[10]['memo'],
            ['operating_posture' => 'preserve liquidity and operational flexibility'],
            'Week 10 memo explains inherited constraints from the compressed history.',
        );
        $this->executeThroughFacultyControl($context, $context['weeks'][10]);
        $week10Evaluation = Week10EconomicEvaluation::query()->firstOrFail();
        $this->assertSame(Week10EconomicEvaluation::STATUS_CALCULATED, $week10Evaluation->status);
        $this->assertSame([], $week10Evaluation->unresolved_dependencies);
        $this->assertSame('6', $week10Evaluation->inherited_state_snapshot['dependencies']['crude_hedge_coverage']['source_week']);
        $this->assertSame('capital_allocation_evaluation', $week10Evaluation->inherited_state_snapshot['dependencies']['crude_hedge_coverage']['source_entity']);

        $week12Submission = $this->submitDecisionWeek(
            $context,
            $context['weeks'][12],
            $definitions[12]['decision'],
            $definitions[12]['memo'],
            ['selected_project_keys' => 'helix_rotterdam offshore_wind euro_retail_divest'],
            'Week 12 memo defends the divestment-enabled transition portfolio.',
        );
        $this->executeThroughFacultyControl($context, $context['weeks'][12]);
        $this->assertSame(Week12EconomicEvaluation::STATUS_CALCULATED, Week12EconomicEvaluation::query()->sole()->status);

        $this->openWeek($context, $context['weeks'][14]);
        $this->actingAs($context['graph']['student'])
            ->postJson(route('student.week14.defense.submit', $context['weeks'][14]), [
                'final_synthesis_memo' => 'Seven-week synthesis memo linking decisions, counterfactuals, and defense narrative.',
                'artifact_references' => [[
                    'type' => 'board_presentation',
                    'label' => 'Seven-week board deck',
                    'reference' => 'https://example.test/seven-week-deck',
                ]],
            ])
            ->assertOk();
        $this->actingAs($context['graph']['faculty'])
            ->postJson(route('faculty.week14.assessment.save', [$context['weeks'][14], $context['teamSimulation']]), [
                'dimensions' => [
                    'strategic_coherence' => ['faculty_evaluation' => 'coherent'],
                    'decision_quality' => ['faculty_evaluation' => 'sound'],
                    'self_understanding' => ['faculty_evaluation' => 'clear'],
                ],
                'feedback_body' => 'Seven-week defense feedback.',
                'complete' => true,
            ])
            ->assertOk();
        $this->assertSame(BoardDefenseAssessment::STATUS_COMPLETED, BoardDefenseAssessment::query()->sole()->status);

        $this->assertSame(0, CohortFeedbackEffect::query()->where('effect_key', Window2CohortResponseFunctionCatalog::EFFECT_KEY)->count());
        $this->assertSame(0, CohortFeedbackEffect::query()->count());
        $this->assertSame(0, CohortDecisionAggregate::query()->count());
        $this->assertSame(0, CohortFeedbackEffect::query()
            ->whereIn('effect_key', [
                Window1CohortResponseFunctionCatalog::EFFECT_KEY,
                Window2CohortResponseFunctionCatalog::EFFECT_KEY,
                Window3CohortResponseFunctionCatalog::EFFECT_KEY,
            ])
            ->count());

        $this->assertSame($context['teamSimulation']->id, $week8Submission->team_simulation_id);
        $this->assertSame($context['teamSimulation']->id, $week10Submission->team_simulation_id);
        $this->assertSame('first_seat', $week8Submission->definition_snapshot['definition']['metadata']['seven_week_role_phase']);
        $this->assertSame('second_seat', $week10Submission->definition_snapshot['definition']['metadata']['seven_week_role_phase']);
        $this->assertSame('operations_finance', SimulationSeatAssignment::query()->sole()->seat->code);

        $trace = app(CausalTraceService::class)->forwardFromDecision($context['graph']['faculty'], $week1Submission);
        $decisionNode = collect($trace->nodes)->firstWhere('type', 'decision');
        $this->assertNotNull($decisionNode);
        $this->assertSame('accelerate_review', $decisionNode->payload['available_alternatives']['rotterdam_review_posture']['selected']);
        $this->assertContains('hold_review', collect($decisionNode->payload['available_alternatives']['rotterdam_review_posture']['not_selected_options'])->pluck('value')->all());

        $this->expectException(InvalidArgumentException::class);
        app(WeekExecutionService::class)->execute(
            $context['weeks'][10]->refresh(),
            $context['graph']['faculty'],
            app(AuthoritativeContentPackageManifest::class)->packageType(10),
        );
    }

    public function test_seven_week_week10_fails_safely_without_folded_week6_hedge_and_recovers_for_a_fresh_submission(): void
    {
        $context = $this->sevenWeekContext('MissingDependency');
        $this->activateVariantPackages($context['weeks']);
        $definitions = $this->createDecisionAndMemoDefinitions($context['weeks']);
        $this->seedWeek6PackageProjects();
        $this->prepareWeek10History($context, $definitions, includeFoldedHedge: false);

        $submission = $this->submitDecisionWeek(
            $context,
            $context['weeks'][10],
            $definitions[10]['decision'],
            $definitions[10]['memo'],
            ['operating_posture' => 'preserve liquidity and operational flexibility'],
            'Week 10 memo with missing folded currency state.',
        );

        $record = app(WeekExecutionService::class)->execute(
            $context['weeks'][10],
            $context['graph']['faculty'],
            app(AuthoritativeContentPackageManifest::class)->packageType(10),
        );
        $this->assertSame(WeekExecutionRecord::STATUS_COMPLETED, $record->status);
        $evaluation = Week10EconomicEvaluation::query()->where('decision_submission_id', $submission->id)->firstOrFail();
        $this->assertSame('unresolved_dependency', $evaluation->status);
        $this->assertSame(['crude_hedge_coverage'], $evaluation->unresolved_dependencies);

        $recovered = $this->sevenWeekContext('RecoveredDependency');
        $this->activateVariantPackages($recovered['weeks']);
        $recoveredDefinitions = $this->createDecisionAndMemoDefinitions($recovered['weeks']);
        $this->seedWeek6PackageProjects();
        $this->prepareWeek10History($recovered, $recoveredDefinitions, includeFoldedHedge: true);

        $recoveredSubmission = $this->submitDecisionWeek(
            $recovered,
            $recovered['weeks'][10],
            $recoveredDefinitions[10]['decision'],
            $recoveredDefinitions[10]['memo'],
            ['operating_posture' => 'preserve liquidity and operational flexibility'],
            'Week 10 memo after persisted folded currency state is available.',
        );
        app(WeekExecutionService::class)->execute(
            $recovered['weeks'][10],
            $recovered['graph']['faculty'],
            app(AuthoritativeContentPackageManifest::class)->packageType(10),
        );

        $this->assertSame(
            Week10EconomicEvaluation::STATUS_CALCULATED,
            Week10EconomicEvaluation::query()->where('decision_submission_id', $recoveredSubmission->id)->firstOrFail()->status,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function sevenWeekContext(string $suffix = 'E2E'): array
    {
        $graph = $this->tenantGraph('SevenWeek'.$suffix);
        $structure = $this->sevenWeekStructure();
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        $weeks = collect(self::SEQUENCE)
            ->mapWithKeys(fn (int $weekNumber): array => [
                $weekNumber => $sectionSimulation->weeks()
                    ->whereHas('definition', fn ($query) => $query->where('week_number', $weekNumber))
                    ->firstOrFail(),
            ]);
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        return compact('graph', 'structure', 'sectionSimulation', 'weeks', 'teamSimulation');
    }

    /**
     * @return array<string, mixed>
     */
    private function sevenWeekStructure(): array
    {
        $simulation = Simulation::factory()->create([
            'slug' => 'halden-energy-seven-week-'.uniqid(),
            'name' => 'Halden Energy',
        ]);
        $variant = SimulationVariant::factory()->create([
            'simulation_id' => $simulation->id,
            'slug' => 'seven-week-compressed-'.uniqid(),
            'name' => 'Seven-week compressed variant',
            'duration_weeks' => 7,
        ]);
        $version = SimulationVersion::factory()->published()->create([
            'simulation_id' => $simulation->id,
            'simulation_variant_id' => $variant->id,
            'version' => '2026-seven-week-'.uniqid(),
            'configuration' => [
                'authoritative_week_sequence' => self::SEQUENCE,
                'cohort_windows' => ['week4_to_week6_discount_rate'],
                'excluded_windows' => ['window1', 'window2', 'window3'],
                'role_rotation_after_week' => 8,
            ],
        ]);

        $titles = [
            1 => 'Asset register and cost structure',
            4 => 'Transfer pricing',
            6 => 'Capital allocation and folded currency',
            8 => 'OPEC scenario',
            10 => 'Recession convergence and folded factor markets',
            12 => 'Transition portfolio',
            14 => 'Board defense',
        ];
        $simulationWeeks = collect(self::SEQUENCE)->map(function (int $weekNumber) use ($simulation, $variant, $version, $titles): SimulationWeek {
            $week = SimulationWeek::factory()->create([
                'simulation_id' => $simulation->id,
                'simulation_variant_id' => $variant->id,
                'simulation_version_id' => $version->id,
                'week_number' => $weekNumber,
                'slug' => 'seven-week-'.$weekNumber,
                'title' => $titles[$weekNumber],
                'content_metadata' => [
                    'seven_week_variant' => true,
                    'authoritative_sequence' => self::SEQUENCE,
                ],
            ]);

            WeekContentVersion::factory()->create([
                'simulation_version_id' => $version->id,
                'simulation_week_id' => $week->id,
            ]);

            return $week;
        });

        return compact('simulation', 'variant', 'version', 'simulationWeeks');
    }

    /**
     * @param  Collection<int, SectionSimulationWeek>  $weeks
     */
    private function activateVariantPackages(Collection $weeks): void
    {
        foreach ([1, 10, 12] as $weekNumber) {
            app(SimulationContentActivationService::class)->activate(
                app(AuthoritativeContentPackageRegistrationService::class)->register(
                    $weeks[$weekNumber]->definition,
                    'seven-week-e2e-week-'.$weekNumber,
                ),
            );
        }

        app(Week4ContentPackageRegistrationService::class)->ensureActivated($weeks[4]->definition);
        app(SimulationContentActivationService::class)->activate(
            app(Week6ContentPackageRegistrationService::class)->register($weeks[6]->definition, 'seven-week-e2e-week-6'),
        );
        app(SimulationContentActivationService::class)->activate(
            app(Week8ContentPackageRegistrationService::class)->register($weeks[8]->definition, 'seven-week-e2e-week-8'),
        );
    }

    /**
     * @param  Collection<int, SectionSimulationWeek>  $weeks
     * @return array<int, array<string, DecisionFormDefinition|MemoDefinition>>
     */
    private function createDecisionAndMemoDefinitions(Collection $weeks): array
    {
        $definitions = [];

        $definitions[1] = $this->definitionsFor($weeks[1], 'week1_asset_register', Week1EconomicEngine::ENGINE_VERSION, Week1EconomicEngine::ENGINE_IDENTIFIER, [
            ['permian_rig_count', 'Permian rig count', DecisionFieldType::Integer, true, ['min' => 0, 'max' => 50], []],
            ['rotterdam_review_posture', 'Rotterdam review posture', DecisionFieldType::Radio, true, [], [
                ['value' => 'accelerate_review', 'label' => 'Accelerate review'],
                ['value' => 'hold_review', 'label' => 'Hold review'],
                ['value' => 'slow_review', 'label' => 'Slow review'],
            ]],
            ['first_meeting_choice', 'First meeting choice', DecisionFieldType::Radio, true, [], [
                ['value' => 'delacroix', 'label' => 'Delacroix'],
                ['value' => 'vestergaard', 'label' => 'Vestergaard'],
                ['value' => 'other', 'label' => 'Other'],
            ]],
        ]);
        $definitions[4] = $this->definitionsFor($weeks[4], 'week4_transfer_pricing', 'week4_transfer_pricing_v1', 'week4_transfer_pricing', [
            ['transfer_price', 'Transfer price', DecisionFieldType::Currency, true, ['min' => 0, 'max' => 250], []],
            ['br_reported_margin_strong', 'Baton Rouge reported margin strong', DecisionFieldType::Boolean, true, [], []],
        ]);
        $definitions[6]['memo'] = MemoDefinition::factory()->create([
            'simulation_version_id' => $weeks[6]->simulation_version_id,
            'simulation_week_id' => $weeks[6]->simulation_week_id,
            'key' => 'week6_capital_allocation_memo',
            'title' => 'Week 6 capital allocation memo',
            'version' => 'seven_week_week6_v1',
            'is_required' => true,
            'character_limit' => 4000,
        ]);
        $definitions[8] = $this->definitionsFor($weeks[8], 'week8_opec_prediction', Week8EconomicEngine::ENGINE_VERSION, Week8EconomicEngine::ENGINE_IDENTIFIER, [
            ['probability_holds_full', 'Full hold probability', DecisionFieldType::Decimal, true, ['min' => 0, 'max' => 1], []],
            ['probability_holds_partial', 'Partial hold probability', DecisionFieldType::Decimal, true, ['min' => 0, 'max' => 1], []],
            ['probability_fails', 'Fail probability', DecisionFieldType::Decimal, true, ['min' => 0, 'max' => 1], []],
            ['realized_scenario_key', 'Realized scenario', DecisionFieldType::Radio, true, [], [
                ['value' => 'holds_full', 'label' => 'Full hold'],
                ['value' => 'holds_partial', 'label' => 'Partial hold'],
                ['value' => 'fails', 'label' => 'Fail'],
            ]],
            ['cash_cushion_musd', 'Cash cushion', DecisionFieldType::Decimal, true, ['min' => 0, 'max' => 1000], []],
        ], ['seven_week_role_phase' => 'first_seat', 'seat_code' => 'commercial_operations']);
        $definitions[10] = $this->definitionsFor($weeks[10], 'week10_convergence_plan', Week10ConvergenceEconomicEngine::ENGINE_VERSION, Week10ConvergenceEconomicEngine::ENGINE_IDENTIFIER, [
            ['operating_posture', 'Operating posture', DecisionFieldType::ShortText, true, ['max_length' => 200], []],
        ], ['seven_week_role_phase' => 'second_seat', 'seat_code' => 'operations_finance']);
        $definitions[12] = $this->definitionsFor($weeks[12], 'week12_transition_portfolio', Week12EconomicEngine::ENGINE_VERSION, Week12EconomicEngine::ENGINE_IDENTIFIER, [
            ['selected_project_keys', 'Selected project keys', DecisionFieldType::ShortText, true, ['max_length' => 255], []],
        ]);

        return $definitions;
    }

    /**
     * @param  list<array{0: string, 1: string, 2: DecisionFieldType, 3: bool, 4: array<string, mixed>, 5: list<array<string, string>>}>  $fields
     * @param  array<string, mixed>  $metadata
     * @return array{decision: DecisionFormDefinition, memo: MemoDefinition}
     */
    private function definitionsFor(SectionSimulationWeek $week, string $key, string $version, string $engine, array $fields, array $metadata = []): array
    {
        $decision = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $week->simulation_version_id,
            'simulation_week_id' => $week->simulation_week_id,
            'key' => $key,
            'name' => $key,
            'version' => $version,
            'metadata' => ['economic_engine' => $engine, ...$metadata],
        ]);

        foreach ($fields as $index => [$fieldKey, $label, $type, $required, $validation, $options]) {
            DecisionFieldDefinition::factory()->create([
                'decision_form_definition_id' => $decision->id,
                'field_key' => $fieldKey,
                'label' => $label,
                'field_type' => $type,
                'is_required' => $required,
                'display_order' => $index + 1,
                'validation' => $validation,
                'options' => $options,
            ]);
        }

        $memo = MemoDefinition::factory()->create([
            'simulation_version_id' => $week->simulation_version_id,
            'simulation_week_id' => $week->simulation_week_id,
            'key' => $key.'_memo',
            'title' => str_replace('_', ' ', $key).' memo',
            'version' => $version,
            'is_required' => true,
            'character_limit' => 4000,
        ]);

        return compact('decision', 'memo');
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $answers
     */
    private function submitDecisionWeek(array $context, SectionSimulationWeek $week, DecisionFormDefinition $decision, MemoDefinition $memo, array $answers, string $memoBody): DecisionSubmission
    {
        $this->openWeek($context, $week);

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $week))
            ->assertOk()
            ->assertDontSee('faculty/', false)
            ->assertDontSee('golden', false);

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.decisions.submit', $week), [
                'definition_ulid' => $decision->ulid,
                'answers' => $answers,
            ])
            ->assertRedirect();

        $this->submitMemo($context, $week, $memo, $memoBody);

        return DecisionSubmission::query()
            ->where('section_simulation_week_id', $week->id)
            ->where('decision_form_definition_id', $decision->id)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function submitMemo(array $context, SectionSimulationWeek $week, MemoDefinition $memo, string $body): void
    {
        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.memo.submit', $week), [
                'definition_ulid' => $memo->ulid,
                'body' => $body,
            ])
            ->assertRedirect();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function openWeek(array $context, SectionSimulationWeek $week): SectionSimulationWeek
    {
        $lifecycle = app(SimulationLifecycleService::class);

        if ($week->statusEnum() === SectionSimulationWeekStatus::Draft) {
            $week = $lifecycle->transitionWeek($week, SectionSimulationWeekStatus::Released, $context['graph']['faculty']);
        }

        if ($week->statusEnum() === SectionSimulationWeekStatus::Released) {
            $week = $lifecycle->transitionWeek($week->refresh(), SectionSimulationWeekStatus::Open, $context['graph']['faculty'], now()->addDay());
        }

        return $week->refresh();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function executeThroughFacultyControl(array $context, SectionSimulationWeek $week): void
    {
        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyWeekControl::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('runtimeWeekId', $week->id)
            ->call('executeSelectedWeek')
            ->assertSee('completed');
    }

    private function seedWeek6PackageProjects(): void
    {
        foreach ([
            ['key' => 'baton_rouge', 'name' => 'Baton Rouge Upgrade', 'category' => 'refining', 'risk_class' => 'refining_upgrade'],
            ['key' => 'rotterdam', 'name' => 'Rotterdam Upgrade', 'category' => 'refining', 'risk_class' => 'refining_upgrade'],
            ['key' => 'helix', 'name' => 'Project Helix', 'category' => 'transition', 'risk_class' => 'adjacent_transition'],
        ] as $project) {
            CapitalProject::query()->firstOrCreate(
                ['key' => $project['key'], 'version' => 'week6_reference_package_v1'],
                [
                    ...$project,
                    'cash_flow_reference' => 'halden-week6-data-package/data/project_cashflows.csv#'.$project['key'],
                    'required_inputs' => ['requires_week6_reference_package' => true],
                    'metadata' => ['package_root' => 'halden-week6-data-package'],
                    'is_active' => true,
                ],
            );
        }
    }

    private function discountRateSchedule(): DiscountRateSchedule
    {
        return DiscountRateSchedule::query()->create([
            'key' => 'seven_week_week4_to_week6_discount_rate',
            'name' => 'Seven-week Week 4 to Week 6 discount-rate consequence',
            'version' => 'discount_rate_v1_'.uniqid(),
            'source_week_number' => 4,
            'target_week_number' => 6,
            'classification_rules' => [
                ['field' => 'geneva_capture_per_bbl', 'operator' => '<=', 'value' => '9.625', 'classification' => 'base'],
            ],
            'classification_outcomes' => [
                'base' => ['discount_rate_percent' => '8.5', 'capital_envelope_musd' => '1150'],
            ],
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function createInitialSeatAssignment(array $context): void
    {
        $seat = Seat::factory()->create(['code' => 'commercial_operations', 'name' => 'Commercial operations']);

        SimulationSeatAssignment::query()->create([
            'tenant_id' => $context['graph']['tenant']->id,
            'team_simulation_id' => $context['teamSimulation']->id,
            'team_id' => $context['graph']['team']->id,
            'user_id' => $context['graph']['student']->id,
            'seat_id' => $seat->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function rotateSeatForWeek10(array $context): void
    {
        $seat = Seat::factory()->create(['code' => 'operations_finance', 'name' => 'Operations finance']);

        SimulationSeatAssignment::query()
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->where('user_id', $context['graph']['student']->id)
            ->sole()
            ->update(['seat_id' => $seat->id]);
    }

    private function seedStraitsPacificStanding(TeamSimulation $teamSimulation): void
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
            'state' => StandingValue::Strained->value,
            'reason' => 'Seven-week variant historical standing fixture.',
            'state_changed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, DecisionFormDefinition|MemoDefinition>>  $definitions
     */
    private function prepareWeek10History(array $context, array $definitions, bool $includeFoldedHedge): void
    {
        $this->submitDecisionWeek(
            $context,
            $context['weeks'][4],
            $definitions[4]['decision'],
            $definitions[4]['memo'],
            ['transfer_price' => '46.20', 'br_reported_margin_strong' => true],
            'Week 4 prior state memo.',
        );
        $this->discountRateSchedule();
        $this->executeThroughFacultyControl($context, $context['weeks'][4]);

        $this->openWeek($context, $context['weeks'][6]);
        $payload = [
            'selected_project_keys' => ['baton_rouge', 'helix'],
            'week10_inherited_state' => ['cancellable_capex_musd' => '120.0'],
        ];
        if ($includeFoldedHedge) {
            $payload['week10_inherited_state']['crude_hedge_coverage'] = '0.45';
        }
        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.capital-allocation.submit', $context['weeks'][6]), $payload)
            ->assertRedirect();
        $this->submitMemo($context, $context['weeks'][6], $definitions[6]['memo'], 'Week 6 prior state memo.');
        $this->executeThroughFacultyControl($context, $context['weeks'][6]);

        $this->submitDecisionWeek(
            $context,
            $context['weeks'][8],
            $definitions[8]['decision'],
            $definitions[8]['memo'],
            [
                'probability_holds_full' => '0.10',
                'probability_holds_partial' => '0.20',
                'probability_fails' => '0.70',
                'realized_scenario_key' => 'fails',
                'cash_cushion_musd' => '85.0',
            ],
            'Week 8 prior state memo.',
        );
        $this->executeThroughFacultyControl($context, $context['weeks'][8]);
        $this->seedStraitsPacificStanding($context['teamSimulation']);
    }
}
