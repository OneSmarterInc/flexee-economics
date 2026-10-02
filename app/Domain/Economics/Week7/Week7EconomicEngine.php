<?php

namespace App\Domain\Economics\Week7;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Week7EconomicEngine
{
    public const ENGINE_IDENTIFIER = 'week7_competitive_response';

    public const ENGINE_VERSION = 'week7_competitive_response_v1';

    public function calculate(Week7EconomicInputs $inputs): Week7EconomicResult
    {
        $clusterResults = $this->clusterResults($inputs);
        $rivalBuildProbability = $inputs->rivalParameter('rival_build_probability');
        $evHold = $this->expectedValue($rivalBuildProbability, $inputs->capacityPayoffs['hold_builds'], $inputs->capacityPayoffs['hold_bluffs']);
        $evMatch = $this->expectedValue($rivalBuildProbability, $inputs->capacityPayoffs['match_builds'], $inputs->capacityPayoffs['match_bluffs']);
        $breakeven = $this->breakevenBuildProbability($inputs);
        $windowResults = $this->windowResults($inputs);

        return new Week7EconomicResult(
            clusterResults: $clusterResults,
            evHoldMusd: $evHold,
            evMatchMusd: $evMatch,
            breakevenBuildProbability: $breakeven,
            capacityDecision: $evHold->isGreaterThanOrEqualTo($evMatch) ? 'hold' : 'match',
            windowResults: $windowResults,
            workedExample: $this->workedExample($inputs),
            inputSnapshot: $this->inputSnapshot($inputs),
            outputSnapshot: $this->outputSnapshot($clusterResults, $evHold, $evMatch, $breakeven, $windowResults),
            packageVersion: $inputs->packageVersion,
            engineIdentifier: self::ENGINE_IDENTIFIER,
            engineVersion: self::ENGINE_VERSION,
        );
    }

    /**
     * @return array<string, Week7ClusterResult>
     */
    private function clusterResults(Week7EconomicInputs $inputs): array
    {
        $results = [];
        $streetCut = $inputs->rivalParameter('street_cut');
        $pumpBase = $inputs->rivalParameter('pump_base');
        $fuelMargin = $inputs->rivalParameter('fuel_margin');
        $perGalValue = $fuelMargin->plus($inputs->rivalParameter('nonfuel_per_fill')->dividedBy($inputs->rivalParameter('gallons_per_fill'), 12, RoundingMode::HalfUp));

        foreach ($inputs->clusters as $cluster) {
            $atRiskPct = $cluster->elasticity->multipliedBy($streetCut)->dividedBy($pumpBase, 12, RoundingMode::HalfUp)->multipliedBy('100');
            $matchCost = $streetCut->multipliedBy($cluster->annualVolumeMgal);
            $ignoreCost = $atRiskPct->abs()
                ->dividedBy('100', 12, RoundingMode::HalfUp)
                ->multipliedBy($perGalValue)
                ->multipliedBy($cluster->annualVolumeMgal);

            $results[$cluster->key] = new Week7ClusterResult(
                clusterKey: $cluster->key,
                atRiskPct: $atRiskPct,
                matchCostMusd: $matchCost,
                ignoreCostMusd: $ignoreCost,
                retailDecision: $ignoreCost->isLessThanOrEqualTo($matchCost) ? 'ignore' : 'match',
            );
        }

        return $results;
    }

    private function expectedValue(BigDecimal $buildProbability, BigDecimal $builds, BigDecimal $bluffs): BigDecimal
    {
        return $buildProbability->multipliedBy($builds)->plus(BigDecimal::one()->minus($buildProbability)->multipliedBy($bluffs));
    }

    private function breakevenBuildProbability(Week7EconomicInputs $inputs): BigDecimal
    {
        $matchBluffs = $inputs->capacityPayoffs['match_bluffs'];
        $holdBluffs = $inputs->capacityPayoffs['hold_bluffs'];
        $holdBuilds = $inputs->capacityPayoffs['hold_builds'];
        $matchBuilds = $inputs->capacityPayoffs['match_builds'];

        return $matchBluffs->minus($holdBluffs)
            ->dividedBy($matchBluffs->minus($holdBluffs)->plus($holdBuilds)->minus($matchBuilds), 12, RoundingMode::HalfUp);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function windowResults(Week7EconomicInputs $inputs): array
    {
        $base = $inputs->windowParameter('base_nonfuel');
        $slope = $inputs->windowParameter('slope');
        $pivot = $inputs->windowParameter('pivot');
        $min = $base->multipliedBy('0.85');
        $max = $base->multipliedBy('1.15');
        $results = [];

        foreach ($inputs->cohortStates as $state => $aggression) {
            $margin = $base->plus($slope->multipliedBy($pivot->minus($aggression)));

            if ($margin->isLessThan($min)) {
                $margin = $min;
            }

            if ($margin->isGreaterThan($max)) {
                $margin = $max;
            }

            $changePct = $margin->minus($base)->dividedBy($base, 12, RoundingMode::HalfUp)->multipliedBy('100');
            $results[$state] = [
                'aggression' => $this->decimal($aggression),
                'nonfuel_margin' => $this->decimal($margin),
                'change_pct' => $this->decimal($changePct),
            ];
        }

        return $results;
    }

    /**
     * @return array<string, BigDecimal>
     */
    private function workedExample(Week7EconomicInputs $inputs): array
    {
        $cut = $inputs->workedExample['cut'];
        $pump = $inputs->workedExample['pump'];
        $margin = $inputs->workedExample['margin'];
        $volume = $inputs->workedExample['volume'];
        $elastic = $inputs->workedExample['station_elasticity_elastic'];
        $inelastic = $inputs->workedExample['station_elasticity_inelastic'];

        return [
            'ignore_cost_elastic' => $elastic->abs()->multipliedBy($cut)->dividedBy($pump, 12, RoundingMode::HalfUp)->multipliedBy($margin)->multipliedBy($volume),
            'ignore_cost_inelastic' => $inelastic->abs()->multipliedBy($cut)->dividedBy($pump, 12, RoundingMode::HalfUp)->multipliedBy($margin)->multipliedBy($volume),
            'match_cost' => $cut->multipliedBy($volume),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inputSnapshot(Week7EconomicInputs $inputs): array
    {
        return [
            'reference_package' => [
                'version' => $inputs->packageVersion,
                'source_hashes' => $inputs->sourceHashes,
            ],
            'clusters' => array_map(fn (Week7Cluster $cluster): array => $cluster->snapshot(), $inputs->clusters),
            'rival_move' => $this->formatDecimals($inputs->rivalMove),
            'capacity_payoffs' => $this->formatDecimals($inputs->capacityPayoffs),
            'window3_parameters' => $this->formatDecimals($inputs->windowParameters),
            'cohort_states' => $this->formatDecimals($inputs->cohortStates),
        ];
    }

    /**
     * @param  array<string, Week7ClusterResult>  $clusterResults
     * @param  array<string, mixed>  $windowResults
     * @return array<string, mixed>
     */
    private function outputSnapshot(array $clusterResults, BigDecimal $evHold, BigDecimal $evMatch, BigDecimal $breakeven, array $windowResults): array
    {
        return [
            'engine' => [
                'identifier' => self::ENGINE_IDENTIFIER,
                'version' => self::ENGINE_VERSION,
            ],
            'cluster_results' => array_map(fn (Week7ClusterResult $result): array => $result->snapshot(), $clusterResults),
            'capacity_game' => [
                'ev_hold_musd' => $this->decimal($evHold),
                'ev_match_musd' => $this->decimal($evMatch),
                'breakeven_build_probability' => $this->decimal($breakeven),
                'decision' => $evHold->isGreaterThanOrEqualTo($evMatch) ? 'hold' : 'match',
            ],
            'window3' => $windowResults,
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
