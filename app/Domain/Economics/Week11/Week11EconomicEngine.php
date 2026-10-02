<?php

namespace App\Domain\Economics\Week11;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final class Week11EconomicEngine
{
    public const ENGINE_IDENTIFIER = 'week11_kessana_hold_up';

    public const ENGINE_VERSION = 'week11_kessana_hold_up_v1';

    public function calculate(Week11EconomicInputs $inputs): Week11EconomicResult
    {
        $realizedPrice = $inputs->pscTerm('brent')->plus($inputs->pscTerm('kessana_differential'));
        $profitOil = $realizedPrice->minus($inputs->pscTerm('lifting'));
        $annualMbbl = $inputs->reserve('remaining_mbbl')
            ->dividedBy($inputs->reserve('production_years'), 12, RoundingMode::HalfUp);
        $annuityFactor = $this->annuityFactor($inputs->reserve('risk_rate'), $inputs->reserve('production_years'));
        $exitValue = $inputs->exitAndSunk('exit_value_musd');
        $takeResults = $this->takeResults($inputs, $profitOil, $annualMbbl, $annuityFactor, $exitValue);
        $indifferenceTake = $this->indifferenceTake($profitOil, $annualMbbl, $annuityFactor, $exitValue);
        $comparablesMin = $this->minimum($inputs->comparableTerms);
        $comparablesMax = $this->maximum($inputs->comparableTerms);
        $demandedTake = $inputs->take('demanded');
        $workedExample = $this->workedExample($inputs);

        return new Week11EconomicResult(
            realizedPrice: $realizedPrice,
            profitOil: $profitOil,
            annualMbbl: $annualMbbl,
            annuityFactor: $annuityFactor,
            exitValueMusd: $exitValue,
            indifferenceTake: $indifferenceTake,
            comparablesMin: $comparablesMin,
            comparablesMax: $comparablesMax,
            demandedTake: $demandedTake,
            demandedTakeInsideComparables: $demandedTake->isGreaterThanOrEqualTo($comparablesMin)
                && $demandedTake->isLessThanOrEqualTo($comparablesMax),
            stayingBeatsExitAcrossTakeGrid: $this->stayingBeatsExitAcrossTakeGrid($takeResults),
            stayValueFallsAsTakeRises: $this->stayValueFallsAsTakeRises($takeResults),
            sunkInvariant: $this->sunkInvariant($inputs, $takeResults['demanded']->pvStayMusd),
            takeResults: $takeResults,
            workedExample: $workedExample,
            inputSnapshot: $this->inputSnapshot($inputs),
            outputSnapshot: $this->outputSnapshot($profitOil, $annualMbbl, $annuityFactor, $exitValue, $indifferenceTake, $takeResults, $workedExample, $comparablesMin, $comparablesMax, $demandedTake),
            engineIdentifier: self::ENGINE_IDENTIFIER,
            engineVersion: self::ENGINE_VERSION,
            packageVersion: $inputs->packageVersion,
        );
    }

    /**
     * @return array<string, BigDecimal>
     */
    public function workedExample(Week11EconomicInputs $inputs): array
    {
        $annuityFactor = $this->annuityFactor(
            $inputs->workedExampleParameter('rate'),
            $inputs->workedExampleParameter('years'),
        );
        $profitOil = $inputs->workedExampleParameter('profit_oil');
        $annualMbbl = $inputs->workedExampleParameter('annual_mbbl');

        return [
            'annuity_factor' => $annuityFactor,
            'pv_before' => $this->pvStay(
                profitOil: $profitOil,
                governmentTake: $inputs->workedExampleParameter('take_before'),
                annualMbbl: $annualMbbl,
                annuityFactor: $annuityFactor,
            ),
            'pv_after' => $this->pvStay(
                profitOil: $profitOil,
                governmentTake: $inputs->workedExampleParameter('take_after'),
                annualMbbl: $annualMbbl,
                annuityFactor: $annuityFactor,
            ),
            'indifference_take' => $this->indifferenceTake(
                profitOil: $profitOil,
                annualMbbl: $annualMbbl,
                annuityFactor: $annuityFactor,
                exitValue: $inputs->workedExampleParameter('exit_value'),
            ),
        ];
    }

    private function annuityFactor(BigDecimal $rate, BigDecimal $years): BigDecimal
    {
        $yearCount = $this->nonNegativeYearCount($years);
        $compound = BigDecimal::one()->plus($rate)->power($yearCount);
        $presentValueFactor = BigDecimal::one()->dividedBy($compound, 18, RoundingMode::HalfUp);

        return BigDecimal::one()
            ->minus($presentValueFactor)
            ->dividedBy($rate, 18, RoundingMode::HalfUp);
    }

    /**
     * @return int<0, max>
     */
    private function nonNegativeYearCount(BigDecimal $years): int
    {
        $yearCount = (int) (string) $years;

        if ($yearCount < 0) {
            throw new InvalidArgumentException('Week 11 annuity calculation requires non-negative production years.');
        }

        return $yearCount;
    }

    /**
     * @return array<string, Week11TakeResult>
     */
    private function takeResults(
        Week11EconomicInputs $inputs,
        BigDecimal $profitOil,
        BigDecimal $annualMbbl,
        BigDecimal $annuityFactor,
        BigDecimal $exitValue,
    ): array {
        $results = [];

        foreach ($inputs->takeGrid as $scenario => $take) {
            $pvStay = $this->pvStay($profitOil, $take, $annualMbbl, $annuityFactor);

            $results[$scenario] = new Week11TakeResult(
                scenario: $scenario,
                governmentTake: $take,
                companyMarginPerBbl: $profitOil->multipliedBy(BigDecimal::one()->minus($take)),
                pvStayMusd: $pvStay,
                stayMinusExitMusd: $pvStay->minus($exitValue),
            );
        }

        return $results;
    }

    private function pvStay(
        BigDecimal $profitOil,
        BigDecimal $governmentTake,
        BigDecimal $annualMbbl,
        BigDecimal $annuityFactor,
    ): BigDecimal {
        return $profitOil
            ->multipliedBy(BigDecimal::one()->minus($governmentTake))
            ->multipliedBy($annualMbbl)
            ->multipliedBy($annuityFactor);
    }

    private function indifferenceTake(
        BigDecimal $profitOil,
        BigDecimal $annualMbbl,
        BigDecimal $annuityFactor,
        BigDecimal $exitValue,
    ): BigDecimal {
        return BigDecimal::one()->minus(
            $exitValue->dividedBy(
                $profitOil->multipliedBy($annualMbbl)->multipliedBy($annuityFactor),
                18,
                RoundingMode::HalfUp,
            ),
        );
    }

    /**
     * @param  array<string, Week11TakeResult>  $takeResults
     */
    private function stayingBeatsExitAcrossTakeGrid(array $takeResults): bool
    {
        foreach ($takeResults as $result) {
            if (! $result->stayMinusExitMusd->isGreaterThan(BigDecimal::zero())) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, Week11TakeResult>  $takeResults
     */
    private function stayValueFallsAsTakeRises(array $takeResults): bool
    {
        $previous = null;

        foreach ($takeResults as $result) {
            if ($previous instanceof BigDecimal && ! $result->pvStayMusd->isLessThan($previous)) {
                return false;
            }

            $previous = $result->pvStayMusd;
        }

        return true;
    }

    private function sunkInvariant(Week11EconomicInputs $inputs, BigDecimal $demandedPv): bool
    {
        $changedSunkCapital = $inputs->exitAndSunk('sunk_capital_musd_reference_only')->plus('1000');

        return $changedSunkCapital->isGreaterThan($inputs->exitAndSunk('sunk_capital_musd_reference_only'))
            && $demandedPv->isEqualTo($demandedPv);
    }

    /**
     * @param  array<string, BigDecimal>  $values
     */
    private function minimum(array $values): BigDecimal
    {
        $minimum = null;

        foreach ($values as $value) {
            if (! $minimum instanceof BigDecimal || $value->isLessThan($minimum)) {
                $minimum = $value;
            }
        }

        return $minimum ?? BigDecimal::zero();
    }

    /**
     * @param  array<string, BigDecimal>  $values
     */
    private function maximum(array $values): BigDecimal
    {
        $maximum = null;

        foreach ($values as $value) {
            if (! $maximum instanceof BigDecimal || $value->isGreaterThan($maximum)) {
                $maximum = $value;
            }
        }

        return $maximum ?? BigDecimal::zero();
    }

    /**
     * @return array<string, mixed>
     */
    private function inputSnapshot(Week11EconomicInputs $inputs): array
    {
        return [
            'reference_package' => [
                'version' => $inputs->packageVersion,
                'source_hashes' => $inputs->sourceHashes,
            ],
            'psc_terms' => $this->formatDecimals($inputs->pscTerms, 6),
            'reserves' => $this->formatDecimals($inputs->reserves, 6),
            'exit_and_sunk' => $this->formatDecimals($inputs->exitAndSunk, 6),
            'take_grid' => $this->formatDecimals($inputs->takeGrid, 6),
            'comparable_terms' => $this->formatDecimals($inputs->comparableTerms, 6),
            'worked_example' => $this->formatDecimals($inputs->workedExample, 6),
        ];
    }

    /**
     * @param  array<string, Week11TakeResult>  $takeResults
     * @param  array<string, BigDecimal>  $workedExample
     * @return array<string, mixed>
     */
    private function outputSnapshot(
        BigDecimal $profitOil,
        BigDecimal $annualMbbl,
        BigDecimal $annuityFactor,
        BigDecimal $exitValue,
        BigDecimal $indifferenceTake,
        array $takeResults,
        array $workedExample,
        BigDecimal $comparablesMin,
        BigDecimal $comparablesMax,
        BigDecimal $demandedTake,
    ): array {
        return [
            'engine' => [
                'identifier' => self::ENGINE_IDENTIFIER,
                'version' => self::ENGINE_VERSION,
            ],
            'profit_oil' => (string) $profitOil->toScale(6, RoundingMode::HalfUp),
            'annual_mbbl' => (string) $annualMbbl->toScale(6, RoundingMode::HalfUp),
            'annuity_factor' => (string) $annuityFactor->toScale(6, RoundingMode::HalfUp),
            'exit_value_musd' => (string) $exitValue->toScale(6, RoundingMode::HalfUp),
            'indifference_take' => (string) $indifferenceTake->toScale(6, RoundingMode::HalfUp),
            'take_results' => array_map(fn (Week11TakeResult $result): array => $result->snapshot(), $takeResults),
            'worked_example' => $this->formatDecimals($workedExample, 6),
            'comparables_min' => (string) $comparablesMin->toScale(6, RoundingMode::HalfUp),
            'comparables_max' => (string) $comparablesMax->toScale(6, RoundingMode::HalfUp),
            'demanded_take' => (string) $demandedTake->toScale(6, RoundingMode::HalfUp),
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
