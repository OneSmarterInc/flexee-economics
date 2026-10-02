<?php

namespace App\Domain\Economics\Week13;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Week13EconomicEngine
{
    public const ENGINE_IDENTIFIER = 'week13_factor_markets';

    public const ENGINE_VERSION = 'week13_factor_markets_v1';

    public function calculate(Week13EconomicInputs $inputs): Week13EconomicResult
    {
        $norwayGrossCostMusd = $inputs->norwayParameter('wage_bill_musd')
            ->multipliedBy($inputs->norwayParameter('union_demand_pct'));
        $norwayAfterTaxShare = BigDecimal::one()->minus($inputs->norwayParameter('tax_rate'));
        $norwayAfterTaxCostMusd = $norwayGrossCostMusd->multipliedBy($norwayAfterTaxShare);

        $permianMrpK = $inputs->permianParameter('bbl_per_marginal_worker')
            ->multipliedBy($inputs->permianParameter('margin_per_bbl'))
            ->dividedBy('1000', 18, RoundingMode::HalfUp);
        $mrpToWage = $permianMrpK->dividedBy(
            $inputs->permianParameter('market_wage_k'),
            18,
            RoundingMode::HalfUp,
        );

        $turnaroundPeakCostMusd = $inputs->turnaroundParameter('labor_cost_base_musd')
            ->multipliedBy($inputs->turnaroundParameter('peak_multiplier'));
        $delayExpectedCostMusd = $inputs->turnaroundParameter('labor_cost_base_musd')
            ->plus(
                $inputs->turnaroundParameter('outage_probability_if_delayed')
                    ->multipliedBy($inputs->turnaroundParameter('outage_cost_musd')),
            );
        $delaySavingMusd = $turnaroundPeakCostMusd->minus($delayExpectedCostMusd);
        $delaySavingPct = $delaySavingMusd->dividedBy($turnaroundPeakCostMusd, 18, RoundingMode::HalfUp);
        $assetHealthPenaltyPts = $inputs->turnaroundParameter('asset_health_penalty_pts');
        $workedExample = $this->workedExample($inputs);

        return new Week13EconomicResult(
            norwayGrossCostMusd: $norwayGrossCostMusd,
            norwayAfterTaxCostMusd: $norwayAfterTaxCostMusd,
            norwayAfterTaxShare: $norwayAfterTaxShare,
            permianMrpK: $permianMrpK,
            mrpToWage: $mrpToWage,
            turnaroundPeakCostMusd: $turnaroundPeakCostMusd,
            delayExpectedCostMusd: $delayExpectedCostMusd,
            delaySavingMusd: $delaySavingMusd,
            delaySavingPct: $delaySavingPct,
            assetHealthPenaltyPts: $assetHealthPenaltyPts,
            wageBenchmarks: $inputs->wageBenchmarks,
            workedExample: $workedExample,
            orderingAssertions: $this->orderingAssertions(
                $norwayAfterTaxShare,
                $permianMrpK,
                $inputs->permianParameter('market_wage_k'),
                $inputs->turnaroundParameter('labor_cost_base_musd'),
                $turnaroundPeakCostMusd,
                $delayExpectedCostMusd,
                $assetHealthPenaltyPts,
            ),
            inputSnapshot: $this->inputSnapshot($inputs),
            outputSnapshot: $this->outputSnapshot(
                $norwayGrossCostMusd,
                $norwayAfterTaxCostMusd,
                $norwayAfterTaxShare,
                $permianMrpK,
                $mrpToWage,
                $turnaroundPeakCostMusd,
                $delayExpectedCostMusd,
                $delaySavingMusd,
                $delaySavingPct,
                $assetHealthPenaltyPts,
                $workedExample,
            ),
            status: 'calculated',
            engineIdentifier: self::ENGINE_IDENTIFIER,
            engineVersion: self::ENGINE_VERSION,
            packageVersion: $inputs->packageVersion,
        );
    }

    /**
     * @return array<string, BigDecimal>
     */
    public function workedExample(Week13EconomicInputs $inputs): array
    {
        $mrp = $inputs->workedExampleParameter('units_per_worker')
            ->multipliedBy($inputs->workedExampleParameter('price_per_unit'));

        return [
            'mrp' => $mrp,
            'mrp_minus_wage' => $mrp->minus($inputs->workedExampleParameter('wage')),
            'after_tax_wage_increase' => $inputs->workedExampleParameter('wage_increase')
                ->multipliedBy(BigDecimal::one()->minus($inputs->workedExampleParameter('tax_rate'))),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function orderingAssertions(
        BigDecimal $norwayAfterTaxShare,
        BigDecimal $permianMrpK,
        BigDecimal $marketWageK,
        BigDecimal $turnaroundBaseCostMusd,
        BigDecimal $turnaroundPeakCostMusd,
        BigDecimal $delayExpectedCostMusd,
        BigDecimal $assetHealthPenaltyPts,
    ): array {
        $peakPremium = $turnaroundPeakCostMusd
            ->minus($turnaroundBaseCostMusd)
            ->dividedBy($turnaroundBaseCostMusd, 18, RoundingMode::HalfUp);
        $turnaroundCostGap = $turnaroundPeakCostMusd
            ->minus($delayExpectedCostMusd);

        if ($turnaroundCostGap->isLessThan(BigDecimal::zero())) {
            $turnaroundCostGap = $turnaroundCostGap->negated();
        }

        return [
            'norway_concession_costs_twenty_two_pct_of_face' => $norwayAfterTaxShare->isEqualTo('0.22'),
            'permian_mrp_covers_market_wage' => $permianMrpK->isGreaterThan($marketWageK),
            'contractor_peak_is_thirty_five_pct_above_base' => $peakPremium->isEqualTo('0.35'),
            'turnaround_expected_costs_within_five_pct' => $turnaroundCostGap
                ->dividedBy($turnaroundPeakCostMusd, 18, RoundingMode::HalfUp)
                ->isLessThanOrEqualTo('0.05'),
            'delaying_carries_asset_health_penalty' => $assetHealthPenaltyPts->isGreaterThan(BigDecimal::zero()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inputSnapshot(Week13EconomicInputs $inputs): array
    {
        return [
            'reference_package' => [
                'version' => $inputs->packageVersion,
                'source_hashes' => $inputs->sourceHashes,
            ],
            'norway_union' => $this->formatDecimals($inputs->norwayUnion, 6),
            'permian_labor' => $this->formatDecimals($inputs->permianLabor, 6),
            'turnaround' => $this->formatDecimals($inputs->turnaround, 6),
            'wage_benchmarks' => array_map(fn (array $benchmark): array => [
                'structure' => $benchmark['structure'],
                'benchmark_wage_k' => (string) $benchmark['benchmark_wage_k']->toScale(6, RoundingMode::HalfUp),
            ], $inputs->wageBenchmarks),
            'worked_example' => $this->formatDecimals($inputs->workedExample, 6),
        ];
    }

    /**
     * @param  array<string, BigDecimal>  $workedExample
     * @return array<string, mixed>
     */
    private function outputSnapshot(
        BigDecimal $norwayGrossCostMusd,
        BigDecimal $norwayAfterTaxCostMusd,
        BigDecimal $norwayAfterTaxShare,
        BigDecimal $permianMrpK,
        BigDecimal $mrpToWage,
        BigDecimal $turnaroundPeakCostMusd,
        BigDecimal $delayExpectedCostMusd,
        BigDecimal $delaySavingMusd,
        BigDecimal $delaySavingPct,
        BigDecimal $assetHealthPenaltyPts,
        array $workedExample,
    ): array {
        return [
            'engine' => [
                'identifier' => self::ENGINE_IDENTIFIER,
                'version' => self::ENGINE_VERSION,
            ],
            'norway_gross_cost_musd' => (string) $norwayGrossCostMusd->toScale(6, RoundingMode::HalfUp),
            'norway_after_tax_cost_musd' => (string) $norwayAfterTaxCostMusd->toScale(6, RoundingMode::HalfUp),
            'norway_after_tax_share' => (string) $norwayAfterTaxShare->toScale(6, RoundingMode::HalfUp),
            'permian_mrp_k' => (string) $permianMrpK->toScale(6, RoundingMode::HalfUp),
            'mrp_to_wage' => (string) $mrpToWage->toScale(6, RoundingMode::HalfUp),
            'turnaround_peak_cost_musd' => (string) $turnaroundPeakCostMusd->toScale(6, RoundingMode::HalfUp),
            'delay_expected_cost_musd' => (string) $delayExpectedCostMusd->toScale(6, RoundingMode::HalfUp),
            'delay_saving_musd' => (string) $delaySavingMusd->toScale(6, RoundingMode::HalfUp),
            'delay_saving_pct' => (string) $delaySavingPct->toScale(6, RoundingMode::HalfUp),
            'asset_health_penalty_pts' => (string) $assetHealthPenaltyPts->toScale(6, RoundingMode::HalfUp),
            'worked_example' => $this->formatDecimals($workedExample, 6),
        ];
    }

    /**
     * @param  array<string, BigDecimal>  $values
     * @param  int<0, max>  $scale
     * @return array<string, string>
     */
    private function formatDecimals(array $values, int $scale): array
    {
        return array_map(
            fn (BigDecimal $value): string => (string) $value->toScale($scale, RoundingMode::HalfUp),
            $values,
        );
    }
}
