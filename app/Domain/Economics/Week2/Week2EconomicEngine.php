<?php

namespace App\Domain\Economics\Week2;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final class Week2EconomicEngine
{
    public const ENGINE_IDENTIFIER = 'week2_elasticity_estimation';

    public const ENGINE_VERSION = 'week2_elasticity_estimation_v1';

    public function calculate(Week2EconomicInputs $inputs): Week2EconomicResult
    {
        $clusterResults = $this->clusterResults($inputs);
        $cordellWeightedEstimate = $this->weightedEstimate($clusterResults, 'Cordell');
        $europeWeightedEstimate = $this->weightedEstimate($clusterResults, 'Europe');
        $cordellWeightedPassthrough = $this->weightedPassthrough($inputs, 'Cordell');
        $workedExample = $this->workedExample($inputs);

        return new Week2EconomicResult(
            clusterResults: $clusterResults,
            cordellWeightedEstimate: $cordellWeightedEstimate,
            europeWeightedEstimate: $europeWeightedEstimate,
            cordellWeightedPassthrough: $cordellWeightedPassthrough,
            workedExample: $workedExample,
            inputSnapshot: $this->inputSnapshot($inputs),
            outputSnapshot: $this->outputSnapshot($clusterResults, $cordellWeightedEstimate, $europeWeightedEstimate, $cordellWeightedPassthrough, $workedExample),
            status: 'calculated',
            engineIdentifier: self::ENGINE_IDENTIFIER,
            engineVersion: self::ENGINE_VERSION,
            packageVersion: $inputs->packageVersion,
        );
    }

    /**
     * @return array<string, Week2ClusterResult>
     */
    private function clusterResults(Week2EconomicInputs $inputs): array
    {
        $rackCut = $inputs->pricingParameter('rack_cut');
        $pumpBase = $inputs->pricingParameter('pump_base');
        $results = [];

        foreach ($inputs->clusters as $cluster) {
            $estimatedElasticity = $this->slope($cluster->observations);
            $volumeResponse = $this->volumeResponsePct($estimatedElasticity, $rackCut, $cluster->passthrough, $pumpBase);

            $results[$cluster->key] = new Week2ClusterResult(
                clusterKey: $cluster->key,
                market: $cluster->market,
                estimatedElasticity: $estimatedElasticity,
                designElasticity: $cluster->designElasticity,
                passthrough: $cluster->passthrough,
                volumeShare: $cluster->volumeShare,
                volumeResponsePct: $volumeResponse,
            );
        }

        return $results;
    }

    /**
     * @param  list<array{week: int, ln_price: BigDecimal, ln_volume: BigDecimal}>  $observations
     */
    private function slope(array $observations): BigDecimal
    {
        $count = count($observations);

        if ($count === 0) {
            throw new InvalidArgumentException('Week 2 elasticity estimation requires at least one price-volume observation.');
        }

        $meanX = BigDecimal::zero();
        $meanY = BigDecimal::zero();

        foreach ($observations as $observation) {
            $meanX = $meanX->plus($observation['ln_price']);
            $meanY = $meanY->plus($observation['ln_volume']);
        }

        $meanX = $meanX->dividedBy($count, 18, RoundingMode::HalfUp);
        $meanY = $meanY->dividedBy($count, 18, RoundingMode::HalfUp);
        $numerator = BigDecimal::zero();
        $denominator = BigDecimal::zero();

        foreach ($observations as $observation) {
            $xDelta = $observation['ln_price']->minus($meanX);
            $yDelta = $observation['ln_volume']->minus($meanY);
            $numerator = $numerator->plus($xDelta->multipliedBy($yDelta));
            $denominator = $denominator->plus($xDelta->multipliedBy($xDelta));
        }

        if ($denominator->isZero()) {
            throw new InvalidArgumentException('Week 2 elasticity estimation cannot calculate a slope with zero price variance.');
        }

        return $numerator->dividedBy($denominator, 18, RoundingMode::HalfUp);
    }

    private function volumeResponsePct(BigDecimal $elasticity, BigDecimal $rackCut, BigDecimal $passthrough, BigDecimal $pumpBase): BigDecimal
    {
        return $elasticity->negated()
            ->multipliedBy($rackCut)
            ->multipliedBy($passthrough)
            ->dividedBy($pumpBase, 18, RoundingMode::HalfUp)
            ->multipliedBy('100');
    }

    /**
     * @param  array<string, Week2ClusterResult>  $clusterResults
     */
    private function weightedEstimate(array $clusterResults, string $market): BigDecimal
    {
        $weighted = BigDecimal::zero();

        foreach ($clusterResults as $result) {
            if ($result->market === $market) {
                $weighted = $weighted->plus($result->estimatedElasticity->multipliedBy($result->volumeShare));
            }
        }

        return $weighted;
    }

    private function weightedPassthrough(Week2EconomicInputs $inputs, string $market): BigDecimal
    {
        $weighted = BigDecimal::zero();

        foreach ($inputs->clusters as $cluster) {
            if ($cluster->market === $market) {
                $weighted = $weighted->plus($cluster->passthrough->multipliedBy($cluster->volumeShare));
            }
        }

        return $weighted;
    }

    /**
     * @return array<string, BigDecimal>
     */
    private function workedExample(Week2EconomicInputs $inputs): array
    {
        $elasticity = $this->slope($inputs->workedExampleObservations);

        return [
            'worked_elasticity' => $elasticity,
            'worked_vol_response_pct' => $this->volumeResponsePct(
                $elasticity,
                $inputs->workedExampleParameter('rack_cut'),
                $inputs->workedExampleParameter('passthrough'),
                $inputs->workedExampleParameter('pump_base'),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inputSnapshot(Week2EconomicInputs $inputs): array
    {
        return [
            'reference_package' => [
                'version' => $inputs->packageVersion,
                'source_hashes' => $inputs->sourceHashes,
            ],
            'clusters' => array_map(fn (Week2Cluster $cluster): array => $cluster->snapshot(), $inputs->clusters),
            'pricing_parameters' => $this->formatDecimals($inputs->pricingParameters),
            'fuel_nonfuel' => array_map(fn (array $values): array => $this->formatDecimals($values), $inputs->fuelNonfuel),
            'worked_example_parameters' => $this->formatDecimals($inputs->workedExampleParameters),
            'worked_example_observation_count' => count($inputs->workedExampleObservations),
        ];
    }

    /**
     * @param  array<string, Week2ClusterResult>  $clusterResults
     * @param  array<string, BigDecimal>  $workedExample
     * @return array<string, mixed>
     */
    private function outputSnapshot(
        array $clusterResults,
        BigDecimal $cordellWeightedEstimate,
        BigDecimal $europeWeightedEstimate,
        BigDecimal $cordellWeightedPassthrough,
        array $workedExample,
    ): array {
        return [
            'engine' => [
                'identifier' => self::ENGINE_IDENTIFIER,
                'version' => self::ENGINE_VERSION,
            ],
            'cluster_results' => array_map(fn (Week2ClusterResult $result): array => $result->snapshot(), $clusterResults),
            'market_rollups' => [
                'cordell_weighted_est' => $this->decimal($cordellWeightedEstimate),
                'europe_weighted_est' => $this->decimal($europeWeightedEstimate),
                'cordell_weighted_passthrough' => $this->decimal($cordellWeightedPassthrough),
            ],
            'worked_example' => $this->formatDecimals($workedExample),
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
