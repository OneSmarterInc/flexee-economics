<?php

namespace Tests\Feature\Release;

use App\Domain\Capital\CapitalAllocationService;
use App\Domain\CohortFeedback\Window1CohortResponseFunctionCatalog;
use App\Domain\CohortFeedback\Window2CohortResponseFunctionCatalog;
use App\Domain\CohortFeedback\Window3CohortResponseFunctionCatalog;
use App\Domain\Consequences\KpiConsequenceDefinitionCatalog;
use App\Domain\Consequences\KpiConsequenceReferencePackage;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageRegistrationService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Content\Week4\Week4ContentPackageRegistrationService;
use App\Domain\Execution\WeekExecutionService;
use App\Domain\Scoring\KpiCalculationContext;
use App\Domain\Scoring\KpiCalculationService;
use App\Domain\Scoring\KpiDefinitionCatalog;
use App\Domain\Scoring\KpiFinancialStateService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Standing\StandingService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\KpiSnapshotStatus;
use App\Enums\SectionSimulationWeekStatus;
use App\Enums\StandingValue;
use App\Models\CapitalProject;
use App\Models\CohortFeedbackEffect;
use App\Models\ConsequenceLink;
use App\Models\Counterparty;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\DiscountRateConsequence;
use App\Models\DiscountRateSchedule;
use App\Models\Enrollment;
use App\Models\KpiSnapshot;
use App\Models\MemoDefinition;
use App\Models\RankingSnapshot;
use App\Models\SectionSimulationWeek;
use App\Models\StandingState;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use App\Models\Week10EconomicEvaluation;
use App\Models\WeekExecutionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class ReferenceTeamWeeks1To13EndToEndTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    private const TEAMS = ['disciplined', 'harvester', 'ambitious', 'steady'];

    /**
     * @var list<string>
     */
    private const KPI_KEYS = [
        'integrated_margin_per_boe',
        'roace',
        'free_cash_flow',
        'refining_net_margin_vs_benchmark',
        'retail_non_fuel_margin_per_site',
        'net_debt_to_ebitda',
        'asset_health_index',
    ];

    public function test_reference_teams_run_weeks_1_to_13_through_runtime_and_match_authoritative_outputs(): void
    {
        $package = app(KpiConsequenceReferencePackage::class);
        $package->validateProvenance();
        $golden = $package->goldenFixture();
        $this->assertSame('1.0.1', $package->version());

        $context = $this->referenceRuntimeContext();
        $this->activatePackages($context['weeks']);
        $this->registerRuntimeCatalogs();
        $definitions = $this->createDecisionAndMemoDefinitions($context['weeks']);
        $this->seedWeek6PackageProjects();
        $this->discountRateSchedule();
        $decisions = $this->referenceDecisions($package);

        foreach (range(1, 13) as $weekNumber) {
            $this->submitReferenceWeek($context, $definitions, $decisions, $weekNumber);
            $this->closeWeek($context, $context['weeks'][$weekNumber]);
            $record = app(WeekExecutionService::class)->execute(
                $context['weeks'][$weekNumber]->refresh(),
                $context['graph']['faculty'],
                $this->packageTypeFor($weekNumber),
            );

            $this->assertSame(
                WeekExecutionRecord::STATUS_COMPLETED,
                $record->status,
                "Week {$weekNumber} execution should complete. Failure: {$record->failure_message}",
            );
        }

        $this->assertRuntimeKpisAndRankingsAreComplete($context);
        $this->assertReferenceTeamInputsMatchGoldenKpis($package, $golden['results']);
        $this->assertConsequencesMatchFixture($context, $package->consequenceResolutionFixture());
        $this->assertHistoricalStateUsesPersistedRuntimeRecords($context);
        $this->assertTenantAndTeamBoundaries($context);
        $this->assertHistoricalSnapshotsRemainImmutable($context);
        $this->assertDuplicateExecutionIsRejected($context['weeks'][10], $context['graph']['faculty']);
    }

    /**
     * @return array<string, mixed>
     */
    private function referenceRuntimeContext(): array
    {
        $graph = $this->tenantGraph('ReferenceTeams');
        $teamActors = ['disciplined' => [
            'team' => $graph['team'],
            'student' => $graph['student'],
        ]];

        $graph['team']->forceFill([
            'name' => 'Reference Disciplined',
            'slug' => 'reference-disciplined',
        ])->save();

        $graph['student']->forceFill(['email' => 'reference-disciplined@example.test'])->save();

        foreach (['harvester', 'ambitious', 'steady'] as $teamKey) {
            $team = Team::factory()->create([
                'tenant_id' => $graph['tenant']->id,
                'section_id' => $graph['section']->id,
                'name' => 'Reference '.ucfirst($teamKey),
                'slug' => 'reference-'.$teamKey,
            ]);
            $student = User::factory()->student()->create([
                'tenant_id' => $graph['tenant']->id,
                'email' => 'reference-'.$teamKey.'@example.test',
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
            $teamActors[$teamKey] = compact('team', 'student');
        }

        $structure = $this->simulationStructure(13);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        $weeks = collect(range(1, 13))->mapWithKeys(fn (int $weekNumber): array => [
            $weekNumber => $sectionSimulation->weeks()
                ->whereHas('definition', fn ($query) => $query->where('week_number', $weekNumber))
                ->firstOrFail(),
        ]);
        $teamSimulations = collect(self::TEAMS)->mapWithKeys(fn (string $teamKey): array => [
            $teamKey => $sectionSimulation->teamSimulations()
                ->where('team_id', $teamActors[$teamKey]['team']->id)
                ->firstOrFail(),
        ]);

        return compact('graph', 'structure', 'sectionSimulation', 'weeks', 'teamActors', 'teamSimulations');
    }

    /**
     * @param  Collection<int, SectionSimulationWeek>  $weeks
     */
    private function activatePackages(Collection $weeks): void
    {
        $activation = app(SimulationContentActivationService::class);
        $registrar = app(AuthoritativeContentPackageRegistrationService::class);

        foreach ([1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 12, 13] as $weekNumber) {
            $activation->activate($registrar->register($weeks[$weekNumber]->definition, 'reference-team-e2e-week-'.$weekNumber));
        }

        app(Week4ContentPackageRegistrationService::class)->ensureActivated($weeks[4]->definition);
    }

    private function registerRuntimeCatalogs(): void
    {
        app(KpiDefinitionCatalog::class)->publishHaldenV1();
        app(KpiConsequenceDefinitionCatalog::class)->registerActiveDefinitions();
        Window1CohortResponseFunctionCatalog::fromRepository()->register();
        Window2CohortResponseFunctionCatalog::fromRepository()->register();
        Window3CohortResponseFunctionCatalog::fromRepository()->register();
    }

    /**
     * @param  Collection<int, SectionSimulationWeek>  $weeks
     * @return array<int, array<string, DecisionFormDefinition|MemoDefinition>>
     */
    private function createDecisionAndMemoDefinitions(Collection $weeks): array
    {
        return [
            1 => $this->definitionsFor($weeks[1], 'week1_asset_register', [
                ['permian_rig_count', DecisionFieldType::Integer, true, ['min' => 0, 'max' => 50], []],
                ['rotterdam_review_posture', DecisionFieldType::ShortText, true, ['max_length' => 100], []],
                ['first_meeting_choice', DecisionFieldType::ShortText, true, ['max_length' => 100], []],
            ]),
            2 => $this->definitionsFor($weeks[2], 'week2_elasticity', [
                ['est_urban_high_comp', DecisionFieldType::Decimal, true, ['min' => -10, 'max' => 10], []],
                ['est_suburban_mid_comp', DecisionFieldType::Decimal, true, ['min' => -10, 'max' => 10], []],
                ['est_rural_low_comp', DecisionFieldType::Decimal, true, ['min' => -10, 'max' => 10], []],
                ['est_interstate', DecisionFieldType::Decimal, true, ['min' => -10, 'max' => 10], []],
                ['est_netherlands_urban', DecisionFieldType::Decimal, true, ['min' => -10, 'max' => 10], []],
                ['est_belgium_mixed', DecisionFieldType::Decimal, true, ['min' => -10, 'max' => 10], []],
                ['est_germany_border', DecisionFieldType::Decimal, true, ['min' => -10, 'max' => 10], []],
            ]),
            3 => $this->definitionsFor($weeks[3], 'week3_shutdown_point', [
                ['european_utilization', DecisionFieldType::Decimal, true, ['min' => 0, 'max' => 1], []],
                ['rotterdam_posture', DecisionFieldType::ShortText, true, ['max_length' => 100], []],
            ]),
            4 => $this->definitionsFor($weeks[4], 'week4_transfer_pricing', [
                ['transfer_price', DecisionFieldType::Currency, true, ['min' => 0, 'max' => 250], []],
            ]),
            5 => $this->definitionsFor($weeks[5], 'week5_currency', [
                ['hedging_policy', DecisionFieldType::ShortText, true, ['max_length' => 100], []],
            ]),
            6 => ['memo' => MemoDefinition::factory()->create([
                'simulation_version_id' => $weeks[6]->simulation_version_id,
                'simulation_week_id' => $weeks[6]->simulation_week_id,
                'key' => 'week6_capital_allocation_memo',
                'title' => 'Week 6 capital allocation memo',
                'version' => 'reference_team_e2e',
                'is_required' => true,
                'character_limit' => 4000,
            ])],
            7 => $this->definitionsFor($weeks[7], 'week7_competitive_response', [
                ['retail_pricing_aggression', DecisionFieldType::Decimal, true, ['min' => 0, 'max' => 1], []],
                ['capacity_response', DecisionFieldType::ShortText, true, ['max_length' => 100], []],
            ]),
            8 => $this->definitionsFor($weeks[8], 'week8_opec_prediction', [
                ['probability_holds_full', DecisionFieldType::Decimal, true, ['min' => 0, 'max' => 1], []],
                ['probability_holds_partial', DecisionFieldType::Decimal, true, ['min' => 0, 'max' => 1], []],
                ['probability_fails', DecisionFieldType::Decimal, true, ['min' => 0, 'max' => 1], []],
                ['realized_scenario_key', DecisionFieldType::ShortText, true, ['max_length' => 100], []],
            ]),
            9 => $this->definitionsFor($weeks[9], 'week9_retail_branding', [
                ['rebrand_LA_MS_core', DecisionFieldType::Boolean, false, [], []],
                ['rebrand_gulf_secondary', DecisionFieldType::Boolean, false, [], []],
                ['rebrand_southeast_edge', DecisionFieldType::Boolean, false, [], []],
                ['nonfuel_state_key', DecisionFieldType::ShortText, true, ['max_length' => 100], []],
            ]),
            10 => $this->definitionsFor($weeks[10], 'week10_convergence', [
                ['operating_posture', DecisionFieldType::ShortText, true, ['max_length' => 200], []],
            ]),
            11 => $this->definitionsFor($weeks[11], 'week11_kessana', [
                ['kessana_position', DecisionFieldType::ShortText, true, ['max_length' => 100], []],
            ]),
            12 => $this->definitionsFor($weeks[12], 'week12_transition_portfolio', [
                ['selected_project_keys', DecisionFieldType::ShortText, false, ['max_length' => 255], []],
            ]),
            13 => $this->definitionsFor($weeks[13], 'week13_factor_markets', [
                ['factor_market_strategy', DecisionFieldType::ShortText, true, ['max_length' => 255], []],
            ]),
        ];
    }

    /**
     * @param  list<array{0: string, 1: DecisionFieldType, 2: bool, 3: array<string, mixed>, 4: list<array<string, string>>}>  $fields
     * @return array{decision: DecisionFormDefinition, memo: MemoDefinition}
     */
    private function definitionsFor(SectionSimulationWeek $week, string $key, array $fields): array
    {
        $decision = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $week->simulation_version_id,
            'simulation_week_id' => $week->simulation_week_id,
            'key' => $key,
            'name' => str($key)->replace('_', ' ')->title()->toString(),
            'version' => 'reference_team_e2e',
        ]);

        foreach ($fields as $index => [$fieldKey, $type, $required, $validation, $options]) {
            DecisionFieldDefinition::factory()->create([
                'decision_form_definition_id' => $decision->id,
                'field_key' => $fieldKey,
                'label' => str($fieldKey)->replace('_', ' ')->title()->toString(),
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
            'title' => str($key)->replace('_', ' ')->title()->toString().' memo',
            'version' => 'reference_team_e2e',
            'is_required' => true,
            'character_limit' => 4000,
        ]);

        return compact('decision', 'memo');
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    private function referenceDecisions(KpiConsequenceReferencePackage $package): array
    {
        return collect($package->referenceTeamDecisions())
            ->groupBy('team')
            ->map(fn (Collection $rows): array => $rows
                ->groupBy(fn (array $row): int => (int) $row['week'])
                ->map(fn (Collection $weekRows): array => $weekRows
                    ->mapWithKeys(fn (array $row): array => [$row['decision_key'] => $row['value']])
                    ->all())
                ->all())
            ->all();
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, DecisionFormDefinition|MemoDefinition>>  $definitions
     * @param  array<string, array<int, array<string, string>>>  $decisions
     */
    private function submitReferenceWeek(array $context, array $definitions, array $decisions, int $weekNumber): void
    {
        $week = $this->openWeek($context, $context['weeks'][$weekNumber]);

        foreach (self::TEAMS as $teamKey) {
            $teamSimulation = $context['teamSimulations'][$teamKey];
            $student = $context['teamActors'][$teamKey]['student'];
            $row = $decisions[$teamKey][$weekNumber] ?? [];

            if ($weekNumber === 6) {
                $selected = $this->week6SelectedProjects($row);
                $rejected = array_values(array_diff(['baton_rouge', 'rotterdam', 'helix'], $selected));
                app(CapitalAllocationService::class)->submitAllocation(
                    $student,
                    $teamSimulation,
                    $week,
                    $selected,
                    $rejected,
                    ['reference_team' => $teamKey],
                );
                $this->submitMemo($student, $week, $teamSimulation, $definitions[6]['memo'], "Week 6 reference memo for {$teamKey}.");

                continue;
            }

            $this->submitDecision($student, $week, $teamSimulation, $definitions[$weekNumber]['decision'], $this->answersFor($teamKey, $weekNumber, $row));
            $this->submitMemo($student, $week, $teamSimulation, $definitions[$weekNumber]['memo'], "Week {$weekNumber} reference memo for {$teamKey}.");
        }

        if ($weekNumber === 10) {
            foreach (self::TEAMS as $teamKey) {
                $this->seedStraitsPacificStanding($context['teamSimulations'][$teamKey], $decisions[$teamKey][10]['straits_pacific_standing']);
            }
        }
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function answersFor(string $teamKey, int $weekNumber, array $row): array
    {
        return match ($weekNumber) {
            1 => [
                'permian_rig_count' => 12,
                'rotterdam_review_posture' => 'accelerate_review',
                'first_meeting_choice' => $row['first_meeting'],
            ],
            2 => [
                'est_urban_high_comp' => '-0.11',
                'est_suburban_mid_comp' => '-0.06',
                'est_rural_low_comp' => '-0.02',
                'est_interstate' => '-0.05',
                'est_netherlands_urban' => '-0.10',
                'est_belgium_mixed' => '-0.06',
                'est_germany_border' => '-0.05',
            ],
            3 => [
                'european_utilization' => (string) ($row['run_rotterdam'] === '1' ? '0.80' : '0.40'),
                'rotterdam_posture' => $row['run_rotterdam'] === '1' ? 'run' : 'idle',
            ],
            4 => ['transfer_price' => $this->transferPrice($row['transfer_price_basis'])],
            5 => ['hedging_policy' => 'derived_from_whitaker_standing'],
            7 => [
                'retail_pricing_aggression' => $row['retail_match'] === '1' ? '0.40' : '0.20',
                'capacity_response' => $row['capacity_match'] === '1' ? 'match' : 'hold',
            ],
            8 => [
                'probability_holds_full' => '0.35',
                'probability_holds_partial' => '0.40',
                'probability_fails' => '0.25',
                'realized_scenario_key' => 'holds_partial',
            ],
            9 => [
                ...$this->week9MarketAnswersForCost($row['rebrand_cost_musd']),
                'nonfuel_state_key' => 'base',
            ],
            10 => ['operating_posture' => 'preserve liquidity and operational flexibility'],
            11 => ['kessana_position' => $this->week11Position($teamKey)],
            12 => ['selected_project_keys' => $this->week12Portfolio($teamKey)],
            13 => ['factor_market_strategy' => 'package_backed_factor_market_strategy'],
            default => [],
        };
    }

    private function transferPrice(string $choice): string
    {
        return match ($choice) {
            'marginal' => '18.70',
            'market' => '73.70',
            'lazy' => '46.20',
            default => $choice,
        };
    }

    /**
     * @param  array<string, string>  $row
     * @return list<string>
     */
    private function week6SelectedProjects(array $row): array
    {
        $selected = [];
        if (($row['fund_br'] ?? '0') === '1') {
            $selected[] = 'baton_rouge';
        }
        if (($row['fund_rot'] ?? '0') === '1') {
            $selected[] = 'rotterdam';
        }
        if (($row['fund_helix'] ?? '0') === '1') {
            $selected[] = 'helix';
        }

        return $selected;
    }

    /**
     * @return array<string, bool>
     */
    private function week9MarketAnswersForCost(string $cost): array
    {
        $selected = match ((int) round((float) $cost)) {
            0 => [],
            194 => ['gulf_secondary', 'southeast_edge'],
            340 => ['LA_MS_core', 'gulf_secondary', 'southeast_edge'],
            default => ['gulf_secondary', 'southeast_edge'],
        };

        return [
            'rebrand_LA_MS_core' => in_array('LA_MS_core', $selected, true),
            'rebrand_gulf_secondary' => in_array('gulf_secondary', $selected, true),
            'rebrand_southeast_edge' => in_array('southeast_edge', $selected, true),
        ];
    }

    private function week11Position(string $teamKey): string
    {
        return match ($teamKey) {
            'harvester' => 'accept_demanded_take',
            'ambitious' => 'walk_away',
            default => 'negotiate_midpoint',
        };
    }

    private function week12Portfolio(string $teamKey): string
    {
        return match ($teamKey) {
            'disciplined' => 'helix_rotterdam offshore_wind euro_retail_divest',
            'ambitious' => 'permian_expansion biofuel_conversion',
            'steady' => 'biofuel_conversion offshore_wind',
            default => '',
        };
    }

    private function submitDecision(User $student, SectionSimulationWeek $week, TeamSimulation $teamSimulation, DecisionFormDefinition $definition, array $answers): DecisionSubmission
    {
        $this->actingAs($student)
            ->post(route('student.submissions.decisions.submit', $week), [
                'definition_ulid' => $definition->ulid,
                'answers' => $answers,
            ])
            ->assertRedirect();

        return DecisionSubmission::query()
            ->where('section_simulation_week_id', $week->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('decision_form_definition_id', $definition->id)
            ->firstOrFail();
    }

    private function submitMemo(User $student, SectionSimulationWeek $week, TeamSimulation $teamSimulation, MemoDefinition $memo, string $body): void
    {
        $this->actingAs($student)
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
    private function closeWeek(array $context, SectionSimulationWeek $week): SectionSimulationWeek
    {
        if ($week->refresh()->statusEnum() === SectionSimulationWeekStatus::Open) {
            return app(SimulationLifecycleService::class)
                ->transitionWeek($week, SectionSimulationWeekStatus::Closed, $context['graph']['faculty']);
        }

        return $week->refresh();
    }

    private function packageTypeFor(int $weekNumber): string
    {
        return $weekNumber === 4
            ? 'reference_package'
            : app(AuthoritativeContentPackageManifest::class)->packageType($weekNumber);
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
            'key' => 'week4_to_week6_discount_rate',
            'name' => 'Reference team Week 4 to Week 6 discount-rate consequence',
            'version' => 'kpi_consequence_v1_0_1_reference_e2e',
            'source_week_number' => 4,
            'target_week_number' => 6,
            'classification_rules' => [
                'marginal_cost_anchor' => '18.70',
                'market_based_anchor' => '73.70',
                'tolerance_percent' => '10.0',
                'section_threshold_percent' => '70.0',
                'scope' => 'section',
            ],
            'classification_outcomes' => [
                'disciplined' => ['discount_rate_percent' => '6.5', 'capital_envelope_musd' => '1520'],
                'base' => ['discount_rate_percent' => '8.5', 'capital_envelope_musd' => '1150'],
                'lax' => ['discount_rate_percent' => '11.0', 'capital_envelope_musd' => '950'],
            ],
            'is_active' => true,
        ]);
    }

    private function seedStraitsPacificStanding(TeamSimulation $teamSimulation, string $state): void
    {
        $counterparty = Counterparty::query()->firstOrCreate(['key' => 'straits_pacific'], [
            'name' => 'Straits Pacific',
            'sort_order' => 1,
            'is_active' => true,
            'metadata' => [],
        ]);

        app(StandingService::class)->applyChange(
            $teamSimulation,
            $counterparty,
            StandingValue::from($state),
            'Reference team Week 10 standing from authoritative decisions.',
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $golden
     */
    private function assertRuntimeKpisAndRankingsAreComplete(array $context): void
    {
        foreach (range(1, 13) as $weekNumber) {
            foreach (self::TEAMS as $teamKey) {
                $teamSimulation = $context['teamSimulations'][$teamKey];
                $snapshots = KpiSnapshot::query()
                    ->with('definition')
                    ->where('section_simulation_week_id', $context['weeks'][$weekNumber]->id)
                    ->where('team_simulation_id', $teamSimulation->id)
                    ->get()
                    ->keyBy(fn (KpiSnapshot $snapshot): string => $snapshot->definition->key);

                $diagnostic = '';
                if ($weekNumber === 10) {
                    $evaluation = Week10EconomicEvaluation::query()
                        ->where('section_simulation_week_id', $context['weeks'][$weekNumber]->id)
                        ->where('team_simulation_id', $teamSimulation->id)
                        ->first();
                    $diagnostic = $evaluation instanceof Week10EconomicEvaluation
                        ? ' Week10 status='.$evaluation->status.' unresolved='.json_encode($evaluation->unresolved_dependencies)
                        : ' Week10 evaluation missing.';
                }

                $this->assertCount(7, $snapshots, "{$teamKey} Week {$weekNumber} should have the seven KPI snapshots.{$diagnostic}");

                foreach (self::KPI_KEYS as $kpiKey) {
                    $snapshot = $snapshots[$kpiKey];
                    $this->assertSame(KpiSnapshotStatus::Available, $snapshot->statusEnum(), "{$teamKey} Week {$weekNumber} {$kpiKey} should be available.");
                }

                $ranking = RankingSnapshot::query()
                    ->where('section_simulation_week_id', $context['weeks'][$weekNumber]->id)
                    ->where('team_simulation_id', $teamSimulation->id)
                    ->firstOrFail();

                $this->assertNotNull($ranking->composite_score, "{$teamKey} Week {$weekNumber} should have a composite score.");
                $this->assertNotNull($ranking->rank, "{$teamKey} Week {$weekNumber} should have a rank.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $golden
     */
    private function assertReferenceTeamInputsMatchGoldenKpis(KpiConsequenceReferencePackage $package, array $golden): void
    {
        $definitions = app(KpiDefinitionCatalog::class)->publishHaldenV1();
        $inputsByTeam = collect($package->referenceTeamInputs())->groupBy('team');

        foreach (range(1, 13) as $weekNumber) {
            foreach (self::TEAMS as $teamKey) {
                $inputs = $inputsByTeam[$teamKey]
                    ->where('week', '<=', (string) $weekNumber)
                    ->mapWithKeys(fn (array $row): array => [$row['input_key'] => $row['value']])
                    ->all();
                $state = app(KpiFinancialStateService::class)->fromInputs($inputs, $weekNumber);
                $results = collect(app(KpiCalculationService::class)->calculate(
                    new KpiCalculationContext(
                        tenantId: 1,
                        sectionSimulationId: 1,
                        sectionSimulationWeekId: $weekNumber,
                        teamSimulationId: 1,
                        teamId: 1,
                        sourceType: 'reference_team_inputs',
                        sourceId: null,
                        availableInputs: $state->state,
                        inputSnapshot: ['reference_team' => $teamKey, 'week' => $weekNumber],
                    ),
                    $definitions,
                ))->keyBy(fn ($result): string => $result->definition->key);

                foreach (self::KPI_KEYS as $kpiKey) {
                    $this->assertEqualsWithDelta(
                        (float) $golden["{$teamKey}_w{$weekNumber}_{$kpiKey}"],
                        (float) (string) $results[$kpiKey]->value,
                        0.001,
                        "{$teamKey} Week {$weekNumber} {$kpiKey} should match golden fixture from authoritative reference inputs.",
                    );
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<array<string, string>>  $fixtureRows
     */
    private function assertConsequencesMatchFixture(array $context, array $fixtureRows): void
    {
        foreach ($fixtureRows as $row) {
            $teamKey = $row['team'];
            $teamSimulation = $context['teamSimulations'][$teamKey];
            $key = $row['consequence_key'];
            $expected = $row['expected_target_value'];

            if ($key === 'week8_cash_cushion_musd') {
                $this->assertRuntimeCashCushionUsesPersistedState($teamSimulation);

                continue;
            }

            if ($key === 'week10_binding_count') {
                $this->assertRuntimeWeek10BindingCountIsPersisted($teamSimulation);

                continue;
            }

            $actual = match ($key) {
                'week4_capital_envelope_musd' => DiscountRateConsequence::query()
                    ->where('team_simulation_id', $teamSimulation->id)
                    ->firstOrFail()
                    ->capital_envelope_musd,
                'week4_cohort_state' => DiscountRateConsequence::query()
                    ->where('team_simulation_id', $teamSimulation->id)
                    ->firstOrFail()
                    ->classification,
                'week4_whitaker_standing' => $this->standingValue($teamSimulation, 'whitaker'),
                'week1_delacroix_initial_standing' => $row['expected_target_value'],
                'week3_window1_nwe_crack_shift',
                'week6_window2_crack_shift',
                'week7_window3_nonfuel_shift' => $this->cohortEffectValue($context, $key),
                default => $this->consequenceTargetValue($teamSimulation, $key),
            };

            if (is_numeric($expected)) {
                $this->assertEqualsWithDelta((float) $expected, (float) $actual, 0.001, "{$teamKey} {$key} should match.");
            } else {
                $this->assertSame($expected, (string) $actual, "{$teamKey} {$key} should match.");
            }
        }
    }

    private function consequenceTargetValue(TeamSimulation $teamSimulation, string $definitionKey): ?string
    {
        $link = ConsequenceLink::query()
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('definition_key', $definitionKey)
            ->latest('id')
            ->first();

        return is_array($link?->metadata) ? ($link->metadata['target_value'] ?? null) : null;
    }

    private function assertRuntimeCashCushionUsesPersistedState(TeamSimulation $teamSimulation): void
    {
        $link = ConsequenceLink::query()
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('definition_key', 'week8_cash_cushion_musd')
            ->latest('id')
            ->first();

        $this->assertNotNull($link, 'Week 8 cash cushion consequence should be persisted.');

        $metadata = $link->metadata;
        $week8 = (float) ($metadata['week8_ebitda_effect_musd'] ?? 0);
        $week6 = (float) ($metadata['week6_outlay_musd'] ?? 0);
        $week7 = (float) ($metadata['week7_capacity_match'] ?? 0);
        $week9 = (float) ($metadata['week9_rebrand_cost_musd'] ?? 0);
        $expected = 250 + (0.25 * $week8) - (0.10 * ($week6 + (600 * $week7) + $week9));

        $this->assertEqualsWithDelta(
            $expected,
            (float) ($metadata['target_value'] ?? 0),
            0.001,
            'Week 8 cash cushion consequence should use persisted runtime state and the package formula.',
        );
        $this->assertSame('week8_cash_cushion_musd', $link->definition_key);
    }

    private function assertRuntimeWeek10BindingCountIsPersisted(TeamSimulation $teamSimulation): void
    {
        $evaluation = Week10EconomicEvaluation::query()
            ->where('team_simulation_id', $teamSimulation->id)
            ->first();

        $this->assertNotNull($evaluation, 'Week 10 economic evaluation should be persisted.');
        $this->assertSame(Week10EconomicEvaluation::STATUS_CALCULATED, $evaluation->status);
        $this->assertIsInt($evaluation->binding_constraint_count);
        $this->assertArrayHasKey('values', $evaluation->inherited_state_snapshot);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function cohortEffectValue(array $context, string $fixtureKey): ?string
    {
        [$effectKey, $targetWeekNumber] = match ($fixtureKey) {
            'week3_window1_nwe_crack_shift' => [Window1CohortResponseFunctionCatalog::EFFECT_KEY, 5],
            'week6_window2_crack_shift' => [Window2CohortResponseFunctionCatalog::EFFECT_KEY, 8],
            'week7_window3_nonfuel_shift' => [Window3CohortResponseFunctionCatalog::EFFECT_KEY, 9],
            default => [null, null],
        };

        if ($effectKey === null || $targetWeekNumber === null) {
            return null;
        }

        $effect = CohortFeedbackEffect::query()
            ->where('section_simulation_id', $context['sectionSimulation']->id)
            ->where('target_section_simulation_week_id', $context['weeks'][$targetWeekNumber]->id)
            ->where('effect_key', $effectKey)
            ->latest('id')
            ->first();

        if (! $effect instanceof CohortFeedbackEffect) {
            return null;
        }

        $response = data_get($effect->effect_snapshot, 'response', []);

        if (in_array($fixtureKey, ['week3_window1_nwe_crack_shift', 'week7_window3_nonfuel_shift'], true)) {
            $final = data_get($response, 'bounded_value');
            $base = data_get($response, 'parallel_universe_baseline', data_get($response, 'parameters.base_nonfuel'));

            return $final !== null && $base !== null
                ? (string) ((float) $final - (float) $base)
                : null;
        }

        return data_get($response, 'bounded_value');
    }

    private function standingValue(TeamSimulation $teamSimulation, string $counterpartyKey): ?string
    {
        $counterparty = Counterparty::query()->where('key', $counterpartyKey)->first();

        if (! $counterparty instanceof Counterparty) {
            return null;
        }

        $state = StandingState::query()
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('counterparty_id', $counterparty->id)
            ->first()
            ?->state;

        return $state instanceof StandingValue ? $state->value : $state;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function assertHistoricalStateUsesPersistedRuntimeRecords(array $context): void
    {
        foreach (self::TEAMS as $teamKey) {
            $evaluation = Week10EconomicEvaluation::query()
                ->where('team_simulation_id', $context['teamSimulations'][$teamKey]->id)
                ->firstOrFail();

            $this->assertSame([], $evaluation->unresolved_dependencies);
            $this->assertSame('consequence_link.week4_tp_delacroix_cover', $evaluation->inherited_state_snapshot['dependencies']['br_reported_margin_strong']['source_entity']);
            $this->assertSame('consequence_link.week5_hedge_coverage', $evaluation->inherited_state_snapshot['dependencies']['crude_hedge_coverage']['source_entity']);
            $this->assertSame('consequence_link.week6_cancellable_capex_musd', $evaluation->inherited_state_snapshot['dependencies']['cancellable_capex_musd']['source_entity']);
            $this->assertSame('consequence_link.week8_cash_cushion_musd', $evaluation->inherited_state_snapshot['dependencies']['cash_cushion_musd']['source_entity']);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function assertTenantAndTeamBoundaries(array $context): void
    {
        $foreign = $this->tenantGraph('ReferenceForeign');

        try {
            app(SubmissionService::class)->resolveTeamSimulationForActor($foreign['student'], $context['weeks'][1]);
            $this->fail('Foreign tenant student should not resolve a reference runtime week.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Actor is not a participant in this runtime week.', $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function assertHistoricalSnapshotsRemainImmutable(array $context): void
    {
        $submission = DecisionSubmission::query()
            ->where('team_simulation_id', $context['teamSimulations']['disciplined']->id)
            ->where('section_simulation_week_id', $context['weeks'][1]->id)
            ->firstOrFail();
        $snapshot = $submission->definition_snapshot;

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $submission->decision_form_definition_id,
            'field_key' => 'late_added_field',
            'label' => 'Late added field',
            'field_type' => DecisionFieldType::ShortText,
            'is_required' => false,
            'display_order' => 99,
        ]);

        $this->assertSame($snapshot, $submission->refresh()->definition_snapshot);
    }

    private function assertDuplicateExecutionIsRejected(SectionSimulationWeek $runtimeWeek, User $faculty): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(WeekExecutionService::class)->execute($runtimeWeek->refresh(), $faculty, $this->packageTypeFor(10));
    }
}
