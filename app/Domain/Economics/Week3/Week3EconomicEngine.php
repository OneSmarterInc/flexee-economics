<?php

namespace App\Domain\Economics\Week3;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Week3EconomicEngine
{
    public const ENGINE_IDENTIFIER = 'week3_shutdown_point';

    public const ENGINE_VERSION = 'week3_shutdown_point_v1';

    public function calculate(Week3EconomicInputs $inputs): Week3EconomicResult
    {
        $refineryResults = [];

        foreach ($inputs->refineries as $key => $refinery) {
            $refineryResults[$key] = $this->refineryResult($key, $refinery);
        }

        $windowResults = $this->windowResults($inputs, $refineryResults['rotterdam']);

        return new Week3EconomicResult(
            refineryResults: $refineryResults,
            windowResults: $windowResults,
            rotterdamIdleDelta: $refineryResults['rotterdam']->idleDelta,
            inputSnapshot: $this->inputSnapshot($inputs),
            outputSnapshot: $this->outputSnapshot($refineryResults, $windowResults),
            packageVersion: $inputs->packageVersion,
            engineIdentifier: self::ENGINE_IDENTIFIER,
            engineVersion: self::ENGINE_VERSION,
        );
    }

    /**
     * @param  array<string, BigDecimal|string>  $refinery
     */
    private function refineryResult(string $key, array $refinery): Week3RefineryResult
    {
        /** @var BigDecimal $crack */
        $crack = $refinery['crack'];
        /** @var BigDecimal $complexity */
        $complexity = $refinery['complexity'];
        /** @var BigDecimal $variableCost */
        $variableCost = $refinery['variable_cost'];
        /** @var BigDecimal $fixedCost */
        $fixedCost = $refinery['fixed_cost'];
        /** @var BigDecimal $avoidableFixed */
        $avoidableFixed = $refinery['avoidable_fixed'];
        /** @var BigDecimal $haldenShare */
        $haldenShare = $refinery['halden_share'];

        $contribution = $crack->plus($complexity)->minus($variableCost);
        $net = $contribution->minus($fixedCost);
        $shutdownCrack = $variableCost->minus($complexity);
        $idleDelta = $avoidableFixed->minus($contribution);

        return new Week3RefineryResult(
            key: $key,
            name: (string) $refinery['name'],
            crack: $crack,
            complexity: $complexity,
            variableCost: $variableCost,
            fixedCost: $fixedCost,
            avoidableFixed: $avoidableFixed,
            haldenShare: $haldenShare,
            contribution: $contribution,
            net: $net,
            shutdownCrack: $shutdownCrack,
            idleDelta: $idleDelta,
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function windowResults(Week3EconomicInputs $inputs, Week3RefineryResult $rotterdam): array
    {
        $baseUtil = $inputs->windowParameter('base_util');
        $baseCrack = $inputs->windowParameter('base_crack');
        $slope = $inputs->windowParameter('slope');
        $floor = $inputs->windowParameter('shutdown_floor');
        $results = [];

        foreach ($inputs->cohortStates as $state => $utilization) {
            $crack = $baseCrack->plus($slope->multipliedBy($baseUtil->minus($utilization)));

            if ($crack->isLessThan($floor)) {
                $crack = $floor;
            }

            $contribution = $crack->plus($rotterdam->complexity)->minus($rotterdam->variableCost);
            $net = $contribution->minus($rotterdam->fixedCost);

            $results[$state] = [
                'utilization' => $this->decimal($utilization),
                'crack' => $this->decimal($crack),
                'rotterdam_contribution' => $this->decimal($contribution),
                'rotterdam_net' => $this->decimal($net),
            ];
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function inputSnapshot(Week3EconomicInputs $inputs): array
    {
        return [
            'reference_package' => [
                'version' => $inputs->packageVersion,
                'source_hashes' => $inputs->sourceHashes,
            ],
            'window1_parameters' => $this->formatDecimals($inputs->windowParameters),
            'cohort_states' => $this->formatDecimals($inputs->cohortStates),
            'restart_cost_musd' => $this->decimal($inputs->restartCostMusd),
        ];
    }

    /**
     * @param  array<string, Week3RefineryResult>  $refineryResults
     * @param  array<string, mixed>  $windowResults
     * @return array<string, mixed>
     */
    private function outputSnapshot(array $refineryResults, array $windowResults): array
    {
        return [
            'engine' => [
                'identifier' => self::ENGINE_IDENTIFIER,
                'version' => self::ENGINE_VERSION,
            ],
            'refineries' => array_map(fn (Week3RefineryResult $result): array => $result->snapshot(), $refineryResults),
            'window1' => $windowResults,
        ];
    }

    /**
     * @param  array<string, BigDecimal>  $values
     * @return array<string, string>
     */
    private function formatDecimals(array $values): array
    {
        return array_map(fn (BigDecimal $value): string => $this->decimal($value), $values);
    }

    private function decimal(BigDecimal $value): string
    {
        return (string) $value->toScale(6, RoundingMode::HalfUp);
    }
}
