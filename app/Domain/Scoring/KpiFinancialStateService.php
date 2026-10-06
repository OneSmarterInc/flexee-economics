<?php

namespace App\Domain\Scoring;

use App\Domain\Capital\Week6\Week6CapitalReferencePackage;
use App\Domain\Consequences\KpiConsequenceReferencePackage;
use App\Domain\Economics\Week8\Week8InterimEbitdaBridge;
use App\Models\CapitalAllocationEvaluation;
use App\Models\EconomicResolution;
use App\Models\SectionSimulationWeek;
use App\Models\TeamSimulation;
use App\Models\Week10EconomicEvaluation;
use App\Models\Week11EconomicEvaluation;
use App\Models\Week12EconomicEvaluation;
use App\Models\Week13EconomicEvaluation;
use App\Models\Week2EconomicEvaluation;
use App\Models\Week3EconomicEvaluation;
use App\Models\Week5EconomicEvaluation;
use App\Models\Week7EconomicEvaluation;
use App\Models\Week8EconomicEvaluation;
use App\Models\Week9EconomicEvaluation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class KpiFinancialStateService
{
    public function __construct(
        private KpiConsequenceReferencePackage $package,
        private Week6CapitalReferencePackage $week6Package,
        private Week8InterimEbitdaBridge $week8EbitdaBridge,
    ) {}

    public function forTeamWeek(TeamSimulation $teamSimulation, SectionSimulationWeek $runtimeWeek): KpiFinancialStateResult
    {
        $weekNumber = (int) $runtimeWeek->definition->week_number;
        $inputs = $this->runtimeInputs($teamSimulation, $weekNumber);

        return $this->fromInputs($inputs, $weekNumber);
    }

    /**
     * @param  array<string, string|int|float|null>  $inputs
     */
    public function fromInputs(array $inputs, int $throughWeek): KpiFinancialStateResult
    {
        $state = $this->openingState();
        $normalizedInputs = [];
        $appliedRules = [];

        foreach ($this->package->kpiRules() as $rule) {
            if ((int) $rule['week'] > $throughWeek) {
                continue;
            }

            $inputKey = $rule['input_key'];
            $inputValue = BigDecimal::of((string) ($inputs[$inputKey] ?? '0'));
            $coefficient = BigDecimal::of($rule['coefficient']);
            $delta = $inputValue->multipliedBy($coefficient);
            $stateVar = $rule['state_var'];
            $before = $state[$stateVar] ?? '0';
            $after = BigDecimal::of($before)->plus($delta);

            $state[$stateVar] = (string) $after;
            $normalizedInputs[$inputKey] = (string) $inputValue;
            $appliedRules[] = [
                'week' => (int) $rule['week'],
                'input_key' => $inputKey,
                'input_value' => (string) $inputValue,
                'state_var' => $stateVar,
                'operation' => $rule['operation'],
                'coefficient' => $rule['coefficient'],
                'before' => $before,
                'delta' => (string) $delta,
                'after' => (string) $after,
                'rationale' => $rule['rationale'],
            ];
        }

        foreach ($inputs as $key => $value) {
            if (! array_key_exists($key, $normalizedInputs)) {
                $normalizedInputs[$key] = (string) ($value ?? '0');
            }
        }

        return new KpiFinancialStateResult(
            state: $state,
            inputs: $normalizedInputs,
            appliedRules: $appliedRules,
            provenance: [
                'package' => KpiConsequenceReferencePackage::PACKAGE_ROOT,
                'package_version' => $this->package->version(),
                'source_hashes' => $this->package->sourceHashes(),
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    private function openingState(): array
    {
        $state = [];

        foreach ($this->package->openingState() as $row) {
            $state[$row['parameter']] = $row['value'];
        }

        return $state;
    }

    /**
     * @return array<string, string>
     */
    private function runtimeInputs(TeamSimulation $teamSimulation, int $throughWeek): array
    {
        $inputs = [];

        if ($throughWeek >= 2) {
            $evaluation = $this->latestEvaluation(Week2EconomicEvaluation::class, $teamSimulation);
            if ($evaluation instanceof Week2EconomicEvaluation && $evaluation->status === Week2EconomicEvaluation::STATUS_CALCULATED) {
                $inputs['i2_retail_volume_change_pct'] = (string) ($evaluation->urban_high_comp_volume_response_pct ?? '0');
            }
        }

        if ($throughWeek >= 3) {
            $evaluation = $this->latestEvaluation(Week3EconomicEvaluation::class, $teamSimulation);
            if ($evaluation instanceof Week3EconomicEvaluation && $evaluation->status === Week3EconomicEvaluation::STATUS_CALCULATED) {
                $rotterdamAction = data_get($evaluation->getAttribute('decision_snapshot'), 'decision.rotterdam_action', '');
                $inputs['i3_rotterdam_idled'] = ((string) $rotterdamAction === 'idle') ? '1' : '0';
            }
        }

        if ($throughWeek >= 4) {
            $resolution = $this->latestEvaluation(EconomicResolution::class, $teamSimulation);
            if ($resolution instanceof EconomicResolution) {
                $inputs['i4_geneva_leak_per_chain_bbl'] = (string) BigDecimal::of((string) ($resolution->geneva_capture_per_bbl ?? '0'))
                    ->multipliedBy('40')
                    ->dividedBy('250', 6, RoundingMode::HalfUp);
            }
        }

        if ($throughWeek >= 5) {
            $evaluation = $this->latestEvaluation(Week5EconomicEvaluation::class, $teamSimulation);
            if ($evaluation instanceof Week5EconomicEvaluation && $evaluation->status === Week5EconomicEvaluation::STATUS_CALCULATED) {
                $inputs['i5_fx_ebitda_effect_musd'] = (string) BigDecimal::of((string) ($evaluation->norway_benefit_musd ?? '0'))
                    ->plus((string) ($evaluation->rot_net_impact_musd ?? '0'))
                    ->plus((string) ($evaluation->existing_hedge_gain_musd ?? '0'))
                    ->plus((string) ($evaluation->euro_retail_translation_musd ?? '0'));
                $window1Base = data_get($evaluation->output_snapshot, 'nwe_crack_handoff.base_nwe_crack');
                $window1Final = data_get($evaluation->output_snapshot, 'nwe_crack_handoff.final_nwe_crack');
                $window1Shift = $window1Base !== null && $window1Final !== null
                    ? BigDecimal::of((string) $window1Final)->minus((string) $window1Base)
                    : BigDecimal::zero();
                $inputs['i5_nwe_crack_shift'] = (string) $window1Shift;
                $inputs['i5_rotterdam_window1_ebitda_musd'] = (string) $window1Shift->multipliedBy('120.45');
            }
        }

        if ($throughWeek >= 6) {
            $evaluation = $this->latestEvaluation(CapitalAllocationEvaluation::class, $teamSimulation);
            if ($evaluation instanceof CapitalAllocationEvaluation && $evaluation->status === CapitalAllocationEvaluation::STATUS_CALCULATED) {
                $inputs['i6_outlay_musd'] = (string) ($evaluation->capital_required_musd ?? '0');
                $selected = data_get($evaluation->input_snapshot, 'selected_project_keys', data_get($evaluation->output_snapshot, 'selected_project_keys', []));
                $selectedProjectKeys = is_array($selected)
                    ? array_values(array_filter($selected, is_string(...)))
                    : [];
                $inputs['i6_year1_cashflow_musd'] = $this->week6YearOneCashFlow($selectedProjectKeys);
                $inputs['i6_br_upgrade_funded'] = in_array('baton_rouge', $selectedProjectKeys, true) ? '1' : '0';
            }
        }

        if ($throughWeek >= 7) {
            $evaluation = $this->latestEvaluation(Week7EconomicEvaluation::class, $teamSimulation);
            if ($evaluation instanceof Week7EconomicEvaluation && $evaluation->status === Week7EconomicEvaluation::STATUS_CALCULATED) {
                $inputs['i7_retail_match_cost_musd'] = (string) data_get($evaluation->output_snapshot, 'match_cost_musd', '0');
                $inputs['i7_capacity_matched'] = ((string) $evaluation->capacity_decision === 'match') ? '1' : '0';
            }
        }

        if ($throughWeek >= 8) {
            $evaluation = $this->latestEvaluation(Week8EconomicEvaluation::class, $teamSimulation);
            if ($evaluation instanceof Week8EconomicEvaluation && $evaluation->status === Week8EconomicEvaluation::STATUS_CALCULATED) {
                $bridge = $this->week8EbitdaBridge->calculate($evaluation);
                if ($bridge !== null) {
                    $inputs['i8_dwti'] = (string) $bridge->deltaWti;
                    $inputs['i8_ebitda_effect_musd'] = (string) $bridge->ebitdaEffectMusd;
                    $inputs['i8_ebitda_bridge_identifier'] = Week8InterimEbitdaBridge::IDENTIFIER;
                }
                $inputs['i8_window2_shift'] = (string) data_get($evaluation->input_snapshot, 'cohort_adjustment.refining_crack_shift', data_get($evaluation->realization_snapshot, 'cohort_refining_crack_shift', '0'));
            }
        }

        if ($throughWeek >= 9) {
            $evaluation = $this->latestEvaluation(Week9EconomicEvaluation::class, $teamSimulation);
            if ($evaluation instanceof Week9EconomicEvaluation && $evaluation->status === Week9EconomicEvaluation::STATUS_CALCULATED) {
                $inputs['i9_rebrand_cost_musd'] = (string) ($evaluation->partial_cost_musd ?? '0');
                $inputs['i9_rebrand_gain_musd'] = (string) ($evaluation->partial_gain_musd ?? '0');
                $window3Base = data_get($evaluation->output_snapshot, 'nonfuel_margin_handoff.base_nonfuel_margin');
                $window3Final = data_get($evaluation->output_snapshot, 'nonfuel_margin_handoff.final_nonfuel_margin');
                $inputs['i9_window3_nonfuel_shift'] = $window3Base !== null && $window3Final !== null
                    ? (string) BigDecimal::of((string) $window3Final)->minus((string) $window3Base)
                    : '0';
            }
        }

        if ($throughWeek >= 10) {
            $evaluation = $this->latestEvaluation(Week10EconomicEvaluation::class, $teamSimulation);
            if ($evaluation instanceof Week10EconomicEvaluation && $evaluation->status === Week10EconomicEvaluation::STATUS_CALCULATED) {
                $inputs['i10_blended_demand_hit'] = (string) ($evaluation->blended_demand_hit ?? '0');
                $inputs['i10_capex_cancelled_musd'] = (string) data_get($evaluation->inherited_state_snapshot, 'values.cancellable_capex_musd', '0');
                $inputs['i10_binding_count'] = (string) ($evaluation->binding_constraint_count ?? '0');
            }
        }

        if ($throughWeek >= 11) {
            $evaluation = $this->latestEvaluation(Week11EconomicEvaluation::class, $teamSimulation);
            if ($evaluation instanceof Week11EconomicEvaluation && $evaluation->status === Week11EconomicEvaluation::STATUS_CALCULATED) {
                $position = (string) data_get($evaluation->input_snapshot, 'decision_submission.answers.kessana_position', '');
                $currentMargin = BigDecimal::of((string) ($evaluation->margin_current ?? data_get($evaluation->output_snapshot, 'take_results.current.company_margin_per_bbl', '0')));
                $demandedMargin = BigDecimal::of((string) ($evaluation->margin_demanded ?? data_get($evaluation->output_snapshot, 'take_results.demanded.company_margin_per_bbl', '0')));

                $inputs['i11_kessana_margin_change'] = $position === 'walk_away'
                    ? '0'
                    : (string) $demandedMargin->minus($currentMargin);
                $inputs['i11_exited'] = $position === 'walk_away' ? '1' : '0';
            }
        }

        if ($throughWeek >= 12) {
            $evaluation = $this->latestEvaluation(Week12EconomicEvaluation::class, $teamSimulation);
            if ($evaluation instanceof Week12EconomicEvaluation && $evaluation->status === Week12EconomicEvaluation::STATUS_CALCULATED) {
                $inputs['i12_outlay_musd'] = (string) ($evaluation->selected_capital_required_musd ?? '0');
                $inputs['i12_year1_cashflow_musd'] = (string) data_get($evaluation->output_snapshot, 'selected_year1_cashflow_musd', '0');
                $inputs['i12_divested'] = $evaluation->selected_includes_divestment ? '1' : '0';
            }
        }

        if ($throughWeek >= 13) {
            $evaluation = $this->latestEvaluation(Week13EconomicEvaluation::class, $teamSimulation);
            if ($evaluation instanceof Week13EconomicEvaluation && $evaluation->status === Week13EconomicEvaluation::STATUS_CALCULATED) {
                $inputs['i13_union_concession_musd'] = (string) ($evaluation->norway_gross_cost_musd ?? '0');
                $inputs['i13_turnaround_delayed'] = BigDecimal::of((string) ($evaluation->asset_health_penalty_pts ?? '0'))->isGreaterThan('0') ? '1' : '0';
            }
        }

        return $inputs;
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $model
     * @return T|null
     */
    private function latestEvaluation(string $model, TeamSimulation $teamSimulation): mixed
    {
        return $model::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('section_simulation_id', $teamSimulation->section_simulation_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  list<string>  $selectedProjectKeys
     */
    private function week6YearOneCashFlow(array $selectedProjectKeys): string
    {
        if (! $this->week6Package->isAvailable()) {
            return '0';
        }

        $inputs = $this->week6Package->inputs();
        $sum = BigDecimal::zero();

        foreach ($selectedProjectKeys as $projectKey) {
            if (! isset($inputs->projects[$projectKey])) {
                continue;
            }

            $sum = $sum->plus((string) ($inputs->projects[$projectKey]->flows[1] ?? '0'));
        }

        return (string) $sum;
    }
}
