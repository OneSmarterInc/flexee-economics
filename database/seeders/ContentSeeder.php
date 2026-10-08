<?php

namespace Database\Seeders;

use App\Domain\Capital\Week6\Week6CapitalEconomicsEngine;
use App\Domain\CohortFeedback\Window1CohortResponseFunctionCatalog;
use App\Domain\CohortFeedback\Window2CohortResponseFunctionCatalog;
use App\Domain\CohortFeedback\Window3CohortResponseFunctionCatalog;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageRegistrationService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Content\Week4\Week4ContentPackageRegistrationService;
use App\Domain\Economics\Week1\Week1EconomicEngine;
use App\Domain\Economics\Week10\Week10ConvergenceEconomicEngine;
use App\Domain\Economics\Week11\Week11EconomicEngine;
use App\Domain\Economics\Week12\Week12EconomicEngine;
use App\Domain\Economics\Week13\Week13EconomicEngine;
use App\Domain\Economics\Week2\Week2EconomicEngine;
use App\Domain\Economics\Week3\Week3EconomicEngine;
use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Economics\Week5\Week5EconomicEngine;
use App\Domain\Economics\Week7\Week7EconomicEngine;
use App\Domain\Economics\Week8\Week8EconomicEngine;
use App\Domain\Economics\Week9\Week9EconomicEngine;
use App\Domain\Scoring\KpiDefinitionCatalog;
use App\Enums\DecisionFieldType;
use App\Enums\SimulationVersionStatus;
use App\Models\CapitalProject;
use App\Models\Course;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DiscountRateSchedule;
use App\Models\Institution;
use App\Models\MemoDefinition;
use App\Models\Seat;
use App\Models\Section;
use App\Models\Simulation;
use App\Models\SimulationVariant;
use App\Models\SimulationVersion;
use App\Models\SimulationWeek;
use App\Models\Tenant;
use App\Models\WeekContentVersion;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        app(KpiDefinitionCatalog::class)->publishHaldenV1();
        Window1CohortResponseFunctionCatalog::fromRepository()->register();
        Window2CohortResponseFunctionCatalog::fromRepository()->register();
        Window3CohortResponseFunctionCatalog::fromRepository()->register();

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'halden-university-demo'],
            ['name' => 'Halden University Demo', 'status' => 'active'],
        );

        $institution = Institution::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'halden-university-demo',
        ], [
            'name' => 'Halden University Demo',
        ]);

        $course = Course::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'institution_id' => $institution->id,
            'code' => 'ECON-501',
            'term' => '2026 Fall',
        ], [
            'name' => 'Managerial Economics',
        ]);

        foreach (['Section A', 'Section B', 'Seven-Week Pilot'] as $sectionName) {
            Section::query()->firstOrCreate([
                'tenant_id' => $tenant->id,
                'course_id' => $course->id,
                'name' => $sectionName,
            ], [
                'status' => 'active',
            ]);
        }

        collect([
            ['code' => 'evp', 'name' => 'EVP', 'sort_order' => 1],
            ['code' => 'upstream-head', 'name' => 'Upstream Segment Head', 'sort_order' => 2],
            ['code' => 'refining-head', 'name' => 'Refining Segment Head', 'sort_order' => 3],
            ['code' => 'trading-head', 'name' => 'Trading Segment Head', 'sort_order' => 4],
            ['code' => 'retail-head', 'name' => 'Retail Segment Head', 'sort_order' => 5],
        ])->each(fn (array $seat) => Seat::query()->firstOrCreate(['code' => $seat['code']], $seat));

        $simulation = Simulation::query()->firstOrCreate([
            'slug' => 'halden-energy',
        ], [
            'name' => 'Halden Energy',
            'description' => 'Reusable Halden Energy simulation definition.',
            'status' => 'active',
            'metadata' => ['seeded_for' => 'batch-2-demo'],
        ]);

        $variant = SimulationVariant::query()->firstOrCreate([
            'simulation_id' => $simulation->id,
            'slug' => 'fourteen-week',
        ], [
            'name' => 'Fourteen-week flagship',
            'duration_weeks' => 14,
            'metadata' => ['cadence' => 'weekly'],
        ]);

        $version = SimulationVersion::query()->firstOrCreate([
            'simulation_variant_id' => $variant->id,
            'version' => '2026-demo',
        ], [
            'simulation_id' => $simulation->id,
            'status' => SimulationVersionStatus::Published,
            'config_hash' => 'halden-demo-structural-v1',
            'configuration' => [
                'source' => 'structural-demo',
                'economics' => 'not-included',
            ],
            'notes' => 'Batch 2 seeded structure only.',
            'published_at' => now(),
        ]);

        $weekTitles = [
            1 => 'Asset register',
            2 => 'Elasticity estimation',
            3 => 'Shutdown point',
            4 => 'Transfer pricing',
            5 => 'Currency',
            6 => 'Capital allocation',
            7 => 'Competitive response',
            8 => 'OPEC',
            9 => 'Retail branding',
            10 => 'Recession',
            11 => 'Kessana hold-up',
            12 => 'Transition portfolio',
            13 => 'Factor markets',
            14 => 'Board defense',
        ];

        foreach ($weekTitles as $weekNumber => $title) {
            $week = SimulationWeek::query()->firstOrCreate([
                'simulation_version_id' => $version->id,
                'week_number' => $weekNumber,
            ], [
                'simulation_id' => $simulation->id,
                'simulation_variant_id' => $variant->id,
                'slug' => 'week-'.$weekNumber,
                'title' => $title,
                'pattern' => 'weekly-briefing',
                'status' => 'active',
                'content_metadata' => ['placeholder' => true],
            ]);

            WeekContentVersion::query()->firstOrCreate([
                'simulation_week_id' => $week->id,
                'version' => 'placeholder-v1',
            ], [
                'simulation_version_id' => $version->id,
                'status' => 'placeholder',
                'metadata' => ['placeholder' => true],
            ]);

            if ($weekNumber === 1) {
                $this->ensureWeek1Definitions($version, $week);
            }

            if ($weekNumber === 4) {
                app(Week4ContentPackageRegistrationService::class)->ensureActivated($week);
                $this->ensureWeek4Definitions($version, $week);
            }

            if (in_array($weekNumber, app(AuthoritativeContentPackageManifest::class)->registrableWeeks(), true)) {
                app(SimulationContentActivationService::class)->activate(
                    app(AuthoritativeContentPackageRegistrationService::class)->register($week),
                );
            }

            $this->ensureDemoSubmissionDefinitions($version, $week);
        }

        $sevenWeekVariant = SimulationVariant::query()->firstOrCreate([
            'simulation_id' => $simulation->id,
            'slug' => 'seven-week',
        ], [
            'name' => 'Seven-Week Variant',
            'duration_weeks' => 7,
            'metadata' => [
                'cadence' => 'weekly',
                'authoritative_sequence' => [1, 4, 6, 8, 10, 12, 14],
                'role_rotation' => [
                    'first_seat_weeks' => [1, 4, 6, 8],
                    'second_seat_weeks' => [10, 12, 14],
                ],
            ],
        ]);

        $sevenWeekVersion = SimulationVersion::query()->firstOrCreate([
            'simulation_variant_id' => $sevenWeekVariant->id,
            'version' => '2026-seven-week-pilot',
        ], [
            'simulation_id' => $simulation->id,
            'status' => SimulationVersionStatus::Published,
            'config_hash' => 'halden-seven-week-pilot-v1',
            'configuration' => [
                'source' => 'halden-energy-seven-week-variant',
                'authoritative_week_sequence' => [1, 4, 6, 8, 10, 12, 14],
                'cohort_windows' => ['week4_to_week6_discount_rate'],
                'excluded_windows' => ['window1', 'window2', 'window3'],
                'role_rotation' => [
                    'first_seat_weeks' => [1, 4, 6, 8],
                    'second_seat_weeks' => [10, 12, 14],
                    'rotation_before_week' => 10,
                ],
                'pilot_configuration' => true,
            ],
            'notes' => 'Seven-week pilot configuration using the authoritative compressed sequence.',
            'published_at' => now(),
        ]);

        $sevenWeekTitles = [
            1 => 'Asset register and cost structure',
            4 => 'Transfer pricing',
            6 => 'Capital allocation and folded currency',
            8 => 'OPEC scenario',
            10 => 'Recession convergence and folded factor markets',
            12 => 'Transition portfolio',
            14 => 'Board defense',
        ];

        foreach ($sevenWeekTitles as $weekNumber => $title) {
            $week = SimulationWeek::query()->firstOrCreate([
                'simulation_version_id' => $sevenWeekVersion->id,
                'week_number' => $weekNumber,
            ], [
                'simulation_id' => $simulation->id,
                'simulation_variant_id' => $sevenWeekVariant->id,
                'slug' => 'seven-week-'.$weekNumber,
                'title' => $title,
                'pattern' => $weekNumber === 14 ? 'board-defense' : 'weekly-briefing',
                'status' => 'active',
                'content_metadata' => [
                    'seven_week_variant' => true,
                    'authoritative_sequence' => [1, 4, 6, 8, 10, 12, 14],
                ],
            ]);

            WeekContentVersion::query()->firstOrCreate([
                'simulation_week_id' => $week->id,
                'version' => 'seven-week-pilot-v1',
            ], [
                'simulation_version_id' => $sevenWeekVersion->id,
                'status' => 'active',
                'metadata' => ['seven_week_variant' => true],
            ]);

            if ($weekNumber === 4) {
                app(Week4ContentPackageRegistrationService::class)->ensureActivated($week);
                $this->ensureWeek4Definitions($sevenWeekVersion, $week);
            }

            if (in_array($weekNumber, app(AuthoritativeContentPackageManifest::class)->registrableWeeks(), true)) {
                app(SimulationContentActivationService::class)->activate(
                    app(AuthoritativeContentPackageRegistrationService::class)->register($week, 'seven-week-pilot-v1'),
                );
            }

            $this->ensureDemoSubmissionDefinitions($sevenWeekVersion, $week);
        }

        $this->pruneDeprecatedInheritedStateFields();
        $this->ensureDemoCapitalProjects();
        $this->ensureDemoDiscountRateSchedule();
    }

    private function ensureDemoSubmissionDefinitions(SimulationVersion $version, SimulationWeek $week): void
    {
        match ($week->week_number) {
            1 => $this->ensureWeek1Definitions($version, $week),
            2 => $this->ensureWeek2Definitions($version, $week),
            3 => $this->ensureWeek3Definitions($version, $week),
            5 => $this->ensureWeek5Definitions($version, $week),
            6 => $this->ensureWeek6Definitions($version, $week),
            7 => $this->ensureWeek7Definitions($version, $week),
            8 => $this->ensureWeek8Definitions($version, $week),
            9 => $this->ensureWeek9Definitions($version, $week),
            10 => $this->ensureWeek10Definitions($version, $week),
            11 => $this->ensureWeek11Definitions($version, $week),
            12 => $this->ensureWeek12Definitions($version, $week),
            13 => $this->ensureWeek13Definitions($version, $week),
            default => null,
        };
    }

    private function ensureWeek2Definitions(SimulationVersion $version, SimulationWeek $week): void
    {
        $decision = $this->decisionDefinition($version, $week, 'week2_elasticity_estimation', 'Week 2 elasticity estimation', Week2EconomicEngine::ENGINE_VERSION, Week2EconomicEngine::ENGINE_IDENTIFIER);
        $this->field($decision, 'est_urban_high_comp', 'Urban high-competition elasticity estimate', DecisionFieldType::Decimal->value, 1, ['min' => -1, 'max' => 0]);
        $this->field($decision, 'est_suburban_mid_comp', 'Suburban mid-competition elasticity estimate', DecisionFieldType::Decimal->value, 2, ['min' => -1, 'max' => 0]);
        $this->field($decision, 'est_rural_low_comp', 'Rural low-competition elasticity estimate', DecisionFieldType::Decimal->value, 3, ['min' => -1, 'max' => 0]);
        $this->field($decision, 'est_interstate', 'Interstate elasticity estimate', DecisionFieldType::Decimal->value, 4, ['min' => -1, 'max' => 0]);
        $this->field($decision, 'est_netherlands_urban', 'Netherlands urban elasticity estimate', DecisionFieldType::Decimal->value, 5, ['min' => -1, 'max' => 0]);
        $this->field($decision, 'est_belgium_mixed', 'Belgium mixed elasticity estimate', DecisionFieldType::Decimal->value, 6, ['min' => -1, 'max' => 0]);
        $this->field($decision, 'est_germany_border', 'Germany border elasticity estimate', DecisionFieldType::Decimal->value, 7, ['min' => -1, 'max' => 0]);
        $this->field($decision, 'predicted_volume_response_urban_high_comp', 'Predicted urban high-competition volume response', DecisionFieldType::Decimal->value, 8, ['min' => 0, 'max' => 10]);
        $this->field($decision, 'predicted_volume_response_rural_low_comp', 'Predicted rural low-competition volume response', DecisionFieldType::Decimal->value, 9, ['min' => 0, 'max' => 10]);
        $this->field($decision, 'pricing_strategy', 'Pricing strategy', DecisionFieldType::ShortText->value, 10, ['max_length' => 255], false);
        $this->memoDefinition($version, $week, 'week2_elasticity_estimation_memo', 'Week 2 elasticity memo', Week2EconomicEngine::ENGINE_VERSION);
    }

    private function ensureWeek1Definitions(SimulationVersion $version, SimulationWeek $week): void
    {
        $decision = $this->decisionDefinition($version, $week, 'week1_asset_register', 'Week 1 asset register decisions', Week1EconomicEngine::ENGINE_VERSION, Week1EconomicEngine::ENGINE_IDENTIFIER);
        $this->field($decision, 'permian_rig_count', 'Permian rig count', DecisionFieldType::Integer->value, 1, ['min' => 0]);
        $this->field($decision, 'rotterdam_review_posture', 'Rotterdam review posture', DecisionFieldType::Radio->value, 2, [], true, [
            ['value' => 'accelerate_review', 'label' => 'Accelerate review'],
            ['value' => 'hold_review', 'label' => 'Hold current posture'],
            ['value' => 'slow_review', 'label' => 'Slow review'],
        ]);
        $this->field($decision, 'first_meeting_choice', 'First meeting choice', DecisionFieldType::Radio->value, 3, [], true, [
            ['value' => 'delacroix', 'label' => 'COO Delacroix'],
            ['value' => 'vestergaard', 'label' => 'CFO Vestergaard'],
            ['value' => 'other', 'label' => 'Other stakeholder'],
        ]);
        $this->memoDefinition($version, $week, 'week1_asset_register_memo', 'Week 1 asset register memo', Week1EconomicEngine::ENGINE_VERSION);
    }

    private function ensureWeek4Definitions(SimulationVersion $version, SimulationWeek $week): void
    {
        $decisionDefinition = DecisionFormDefinition::query()->firstOrCreate([
            'simulation_week_id' => $week->id,
            'key' => 'week4_transfer_pricing',
            'version' => Week4EconomicEngine::ENGINE_VERSION,
        ], [
            'simulation_version_id' => $version->id,
            'name' => 'Week 4 transfer pricing',
            'is_required' => true,
            'metadata' => [
                'economic_engine' => Week4EconomicEngine::ENGINE_IDENTIFIER,
                'development_demo_only' => true,
            ],
        ]);

        DecisionFieldDefinition::query()->firstOrCreate([
            'decision_form_definition_id' => $decisionDefinition->id,
            'field_key' => 'transfer_price',
        ], [
            'label' => 'Transfer price',
            'field_type' => 'currency',
            'is_required' => true,
            'display_order' => 1,
            'unit' => '$/bbl',
            'validation' => ['min' => 0, 'max' => 250],
        ]);

        MemoDefinition::query()->firstOrCreate([
            'simulation_week_id' => $week->id,
            'key' => 'week4_transfer_pricing_memo',
            'version' => Week4EconomicEngine::ENGINE_VERSION,
        ], [
            'simulation_version_id' => $version->id,
            'title' => 'Week 4 transfer pricing memo',
            'instructions' => 'Explain the transfer-pricing decision, segment tradeoffs, and expected operational consequences.',
            'is_required' => true,
            'character_limit' => 4000,
            'submission_format' => 'text',
            'metadata' => [
                'economic_engine' => Week4EconomicEngine::ENGINE_IDENTIFIER,
                'development_demo_only' => true,
            ],
        ]);
    }

    private function ensureWeek5Definitions(SimulationVersion $version, SimulationWeek $week): void
    {
        $decision = $this->decisionDefinition($version, $week, 'week5_currency_exposure', 'Week 5 currency exposure decision', Week5EconomicEngine::ENGINE_VERSION, Week5EconomicEngine::ENGINE_IDENTIFIER);
        $this->field($decision, 'hedging_policy', 'Hedging policy', DecisionFieldType::ShortText->value, 1, ['max_length' => 200], false);
        $this->memoDefinition($version, $week, 'week5_currency_exposure_memo', 'Week 5 currency exposure memo', Week5EconomicEngine::ENGINE_VERSION);
    }

    private function ensureWeek3Definitions(SimulationVersion $version, SimulationWeek $week): void
    {
        $decision = $this->decisionDefinition($version, $week, 'week3_shutdown_point', 'Week 3 shutdown point', Week3EconomicEngine::ENGINE_VERSION, Week3EconomicEngine::ENGINE_IDENTIFIER);
        $this->field($decision, 'european_utilization', 'European run-rate utilization', DecisionFieldType::Decimal->value, 1, ['min' => 0, 'max' => 1]);
        $this->field($decision, 'rotterdam_posture', 'Rotterdam posture', DecisionFieldType::Radio->value, 2, [], true, [
            ['value' => 'run', 'label' => 'Run Rotterdam'],
            ['value' => 'idle', 'label' => 'Idle Rotterdam'],
            ['value' => 'restart_later', 'label' => 'Restart later'],
        ]);
        $this->memoDefinition($version, $week, 'week3_shutdown_point_memo', 'Week 3 shutdown memo', Week3EconomicEngine::ENGINE_VERSION);
    }

    private function ensureWeek7Definitions(SimulationVersion $version, SimulationWeek $week): void
    {
        $decision = $this->decisionDefinition($version, $week, 'week7_competitive_response', 'Week 7 competitive response', Week7EconomicEngine::ENGINE_VERSION, Week7EconomicEngine::ENGINE_IDENTIFIER);
        $this->field($decision, 'retail_pricing_aggression', 'Retail pricing aggression', DecisionFieldType::Decimal->value, 1, ['min' => 0, 'max' => 1]);
        $this->field($decision, 'capacity_response', 'Capacity response', DecisionFieldType::Radio->value, 2, [], true, [
            ['value' => 'hold', 'label' => 'Hold'],
            ['value' => 'match', 'label' => 'Match'],
        ]);
        $this->memoDefinition($version, $week, 'week7_competitive_response_memo', 'Week 7 competitive response memo', Week7EconomicEngine::ENGINE_VERSION);
    }

    private function ensureWeek6Definitions(SimulationVersion $version, SimulationWeek $week): void
    {
        $this->memoDefinition($version, $week, 'week6_capital_allocation_memo', 'Week 6 capital allocation memo', Week6CapitalEconomicsEngine::ENGINE_VERSION);
    }

    private function ensureWeek8Definitions(SimulationVersion $version, SimulationWeek $week): void
    {
        $decision = $this->decisionDefinition($version, $week, 'week8_opec_prediction', 'Week 8 OPEC scenario prediction', Week8EconomicEngine::ENGINE_VERSION, Week8EconomicEngine::ENGINE_IDENTIFIER);
        $this->field($decision, 'probability_holds_full', 'Probability: full hold', DecisionFieldType::Decimal->value, 1, ['min' => 0, 'max' => 1]);
        $this->field($decision, 'probability_holds_partial', 'Probability: partial hold', DecisionFieldType::Decimal->value, 2, ['min' => 0, 'max' => 1]);
        $this->field($decision, 'probability_fails', 'Probability: fails', DecisionFieldType::Decimal->value, 3, ['min' => 0, 'max' => 1]);
        $this->field($decision, 'realized_scenario_key', 'Realized scenario', DecisionFieldType::Radio->value, 4, [], false, [
            ['value' => 'holds_full', 'label' => 'Full hold'],
            ['value' => 'holds_partial', 'label' => 'Partial hold'],
            ['value' => 'fails', 'label' => 'Fails'],
        ]);
        $this->memoDefinition($version, $week, 'week8_opec_scenario_memo', 'Week 8 OPEC scenario memo', Week8EconomicEngine::ENGINE_VERSION);
    }

    private function ensureWeek9Definitions(SimulationVersion $version, SimulationWeek $week): void
    {
        $decision = $this->decisionDefinition($version, $week, 'week9_cordell_rebrand', 'Week 9 Cordell rebrand decision', Week9EconomicEngine::ENGINE_VERSION, Week9EconomicEngine::ENGINE_IDENTIFIER);
        $this->field($decision, 'rebrand_LA_MS_core', 'Rebrand LA/MS core', DecisionFieldType::Boolean->value, 1, [], false);
        $this->field($decision, 'rebrand_gulf_secondary', 'Rebrand Gulf secondary', DecisionFieldType::Boolean->value, 2, [], false);
        $this->field($decision, 'rebrand_southeast_edge', 'Rebrand Southeast edge', DecisionFieldType::Boolean->value, 3, [], false);
        $this->field($decision, 'nonfuel_state_key', 'Non-fuel margin state', DecisionFieldType::Radio->value, 4, [], false, [
            ['value' => 'base', 'label' => 'Base'],
            ['value' => 'price_war', 'label' => 'Price war'],
            ['value' => 'disciplined', 'label' => 'Disciplined'],
        ]);
        $this->memoDefinition($version, $week, 'week9_cordell_rebrand_memo', 'Week 9 Cordell rebrand memo', Week9EconomicEngine::ENGINE_VERSION);
    }

    private function ensureWeek10Definitions(SimulationVersion $version, SimulationWeek $week): void
    {
        $decision = $this->decisionDefinition($version, $week, 'week10_convergence_plan', 'Week 10 convergence plan', Week10ConvergenceEconomicEngine::ENGINE_VERSION, Week10ConvergenceEconomicEngine::ENGINE_IDENTIFIER);
        $this->field($decision, 'operating_posture', 'Operating posture', DecisionFieldType::ShortText->value, 1, ['max_length' => 200]);
        $this->memoDefinition($version, $week, 'week10_convergence_memo', 'Week 10 convergence memo', Week10ConvergenceEconomicEngine::ENGINE_VERSION);
    }

    private function ensureWeek11Definitions(SimulationVersion $version, SimulationWeek $week): void
    {
        $decision = $this->decisionDefinition($version, $week, 'week11_kessana_position', 'Week 11 Kessana position', Week11EconomicEngine::ENGINE_VERSION, Week11EconomicEngine::ENGINE_IDENTIFIER);
        $this->field($decision, 'kessana_position', 'Kessana negotiating position', DecisionFieldType::Radio->value, 1, [], true, [
            ['value' => 'accept_demanded_take', 'label' => 'Accept demanded take'],
            ['value' => 'negotiate_midpoint', 'label' => 'Negotiate midpoint'],
            ['value' => 'walk_away', 'label' => 'Walk away'],
        ]);
        $this->memoDefinition($version, $week, 'week11_kessana_memo', 'Week 11 Kessana memo', Week11EconomicEngine::ENGINE_VERSION);
    }

    private function ensureWeek12Definitions(SimulationVersion $version, SimulationWeek $week): void
    {
        $decision = $this->decisionDefinition($version, $week, 'week12_transition_portfolio', 'Week 12 transition portfolio', Week12EconomicEngine::ENGINE_VERSION, Week12EconomicEngine::ENGINE_IDENTIFIER);
        $this->field($decision, 'selected_project_keys', 'Selected portfolio project keys', DecisionFieldType::ShortText->value, 1, ['max_length' => 255]);
        $this->memoDefinition($version, $week, 'week12_transition_portfolio_memo', 'Week 12 transition portfolio memo', Week12EconomicEngine::ENGINE_VERSION);
    }

    private function ensureWeek13Definitions(SimulationVersion $version, SimulationWeek $week): void
    {
        $decision = $this->decisionDefinition($version, $week, 'week13_factor_markets', 'Week 13 factor markets', Week13EconomicEngine::ENGINE_VERSION, Week13EconomicEngine::ENGINE_IDENTIFIER);
        $this->field($decision, 'factor_market_strategy', 'Factor market strategy', DecisionFieldType::ShortText->value, 1, ['max_length' => 255]);
        $this->memoDefinition($version, $week, 'week13_factor_markets_memo', 'Week 13 factor markets memo', Week13EconomicEngine::ENGINE_VERSION);
    }

    private function decisionDefinition(
        SimulationVersion $version,
        SimulationWeek $week,
        string $key,
        string $name,
        string $definitionVersion,
        ?string $engine = null,
    ): DecisionFormDefinition {
        return DecisionFormDefinition::query()->firstOrCreate([
            'simulation_week_id' => $week->id,
            'key' => $key,
            'version' => $definitionVersion,
        ], [
            'simulation_version_id' => $version->id,
            'name' => $name,
            'is_required' => true,
            'metadata' => array_filter([
                'economic_engine' => $engine,
                'development_demo_only' => true,
            ]),
        ]);
    }

    private function memoDefinition(SimulationVersion $version, SimulationWeek $week, string $key, string $title, string $definitionVersion): MemoDefinition
    {
        return MemoDefinition::query()->firstOrCreate([
            'simulation_week_id' => $week->id,
            'key' => $key,
            'version' => $definitionVersion,
        ], [
            'simulation_version_id' => $version->id,
            'title' => $title,
            'instructions' => 'Explain your recommendation and the tradeoffs considered.',
            'is_required' => true,
            'character_limit' => 4000,
            'submission_format' => 'text',
            'metadata' => ['development_demo_only' => true],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validation
     * @param  list<array{value: string, label: string}>  $options
     */
    private function field(
        DecisionFormDefinition $decision,
        string $fieldKey,
        string $label,
        string $fieldType,
        int $order,
        array $validation = [],
        bool $required = true,
        array $options = [],
    ): void {
        DecisionFieldDefinition::query()->firstOrCreate([
            'decision_form_definition_id' => $decision->id,
            'field_key' => $fieldKey,
        ], [
            'label' => $label,
            'field_type' => $fieldType,
            'is_required' => $required,
            'display_order' => $order,
            'options' => $options,
            'validation' => $validation,
        ]);
    }

    private function pruneDeprecatedInheritedStateFields(): void
    {
        $deprecatedByWeek = [
            4 => ['br_reported_margin_strong'],
            8 => ['cash_cushion_musd'],
            10 => [
                'br_reported_margin_strong',
                'cancellable_capex_musd',
                'crude_hedge_coverage',
                'cash_cushion_musd',
            ],
        ];

        foreach ($deprecatedByWeek as $weekNumber => $fieldKeys) {
            DecisionFieldDefinition::query()
                ->whereIn('field_key', $fieldKeys)
                ->whereHas('formDefinition.simulationWeek', fn ($query) => $query->where('week_number', $weekNumber))
                ->delete();
        }
    }

    private function ensureDemoCapitalProjects(): void
    {
        foreach ([
            ['key' => 'baton_rouge', 'name' => 'Baton Rouge Upgrade', 'category' => 'refining', 'risk_class' => 'refining_upgrade'],
            ['key' => 'rotterdam', 'name' => 'Rotterdam Upgrade', 'category' => 'refining', 'risk_class' => 'refining_upgrade'],
            ['key' => 'helix', 'name' => 'Project Helix', 'category' => 'transition', 'risk_class' => 'adjacent_transition'],
        ] as $project) {
            CapitalProject::query()->firstOrCreate([
                'key' => $project['key'],
                'version' => 'week6_reference_package_v1',
            ], [
                'name' => $project['name'],
                'category' => $project['category'],
                'risk_class' => $project['risk_class'],
                'cash_flow_reference' => 'halden-week6-data-package/data/project_cashflows.csv#'.$project['key'],
                'required_inputs' => ['requires_week6_reference_package' => true],
                'metadata' => ['package_root' => 'halden-week6-data-package'],
                'is_active' => true,
            ]);
        }
    }

    private function ensureDemoDiscountRateSchedule(): void
    {
        DiscountRateSchedule::query()
            ->where('key', 'week4_to_week6_discount_rate')
            ->where('version', 'demo_week4_to_week6_v1')
            ->update(['is_active' => false]);

        DiscountRateSchedule::query()->updateOrCreate(
            [
                'key' => 'week4_to_week6_discount_rate',
                'version' => 'kpi_consequence_v1_0_1',
            ],
            [
                'name' => 'Week 4 cohort discount-rate consequence',
                'description' => 'Authoritative section-level Week 4 transfer-price cohort classification into Week 6 capital context.',
                'source_week_number' => 4,
                'target_week_number' => 6,
                'classification_rules' => [
                    'classification_source' => 'SAKSHI-AUDIT-NOTE-2026-10-04',
                    'marginal_cost_anchor' => '18.70',
                    'market_based_anchor' => '73.70',
                    'tolerance' => '0.10',
                    'section_threshold' => '0.70',
                    'visibility' => 'hidden_until_week4_lock_week6_reveal',
                ],
                'classification_outcomes' => [
                    'disciplined' => ['discount_rate_percent' => '6.5', 'capital_envelope_musd' => '1520'],
                    'base' => ['discount_rate_percent' => '8.5', 'capital_envelope_musd' => '1150'],
                    'lax' => ['discount_rate_percent' => '11.0', 'capital_envelope_musd' => '950'],
                ],
                'is_active' => true,
            ],
        );
    }
}
