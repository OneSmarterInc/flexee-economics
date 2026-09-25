<?php

namespace App\Domain\Economics\Week8;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final class Week8EconomicEngine
{
    public const ENGINE_IDENTIFIER = 'week8_opec_scenario';

    public const ENGINE_VERSION = 'week8_opec_scenario_v1';

    /**
     * The package workbook and validator express pump-price conversion through
     * crude dollars per barrel divided by 42 gallons.
     */
    private const GALLONS_PER_BARREL = '42';

    /**
     * @param  array<string, BigDecimal|int|string>|null  $predictionDistribution
     */
    public function calculate(
        Week8EconomicInputs $inputs,
        ?array $predictionDistribution = null,
        ?string $realizedScenarioKey = null,
    ): Week8EconomicResult {
        $this->assertProbabilityDistribution($this->scenarioProbabilities($inputs->scenarios), 'simulated scenario');
        $prediction = $predictionDistribution === null
            ? null
            : $this->normalizeProbabilityDistribution($inputs, $predictionDistribution, 'prediction');

        $scenarioResults = $this->scenarioResults($inputs);
        $simProbabilities = $this->scenarioProbabilities($inputs->scenarios);
        $expectedWti = $this->weightedScenarioValue($inputs->scenarios, $simProbabilities, 'wti_resolved');
        $expectedUpstream = $this->weightedScenarioResult($scenarioResults, $simProbabilities, 'upstream');
        $expectedCrack = $this->weightedScenarioResult($scenarioResults, $simProbabilities, 'crack');
        $realizedScenarioResult = $realizedScenarioKey === null ? null : $this->realizedScenario($scenarioResults, $realizedScenarioKey);
        $predictionExpectedWti = $prediction === null ? null : $this->weightedScenarioValue($inputs->scenarios, $prediction, 'wti_resolved');
        $predictionExpectedUpstream = $prediction === null ? null : $this->weightedScenarioResult($scenarioResults, $prediction, 'upstream');
        $predictionExpectedCrack = $prediction === null ? null : $this->weightedScenarioResult($scenarioResults, $prediction, 'crack');

        return new Week8EconomicResult(
            scenarioResults: $scenarioResults,
            probabilityDistribution: $this->formatDistribution($simProbabilities),
            predictionDistribution: $prediction === null ? null : $this->formatDistribution($prediction),
            expectedWti: $expectedWti,
            expectedUpstreamImpactPerBbl: $expectedUpstream,
            expectedRefiningCrack: $expectedCrack,
            predictionExpectedWti: $predictionExpectedWti,
            predictionExpectedUpstreamImpactPerBbl: $predictionExpectedUpstream,
            predictionExpectedRefiningCrack: $predictionExpectedCrack,
            realizedScenarioResult: $realizedScenarioResult,
            inputSnapshot: $this->inputSnapshot($inputs, $prediction, $realizedScenarioKey),
            outputSnapshot: $this->outputSnapshot($scenarioResults, $simProbabilities, $prediction, $expectedWti, $expectedUpstream, $expectedCrack, $predictionExpectedWti, $predictionExpectedUpstream, $predictionExpectedCrack, $realizedScenarioResult),
            engineIdentifier: self::ENGINE_IDENTIFIER,
            engineVersion: self::ENGINE_VERSION,
        );
    }

    public function scenarioResult(Week8EconomicInputs $inputs, Week8Scenario $scenario): Week8ScenarioResult
    {
        $upstreamImpact = $scenario->deltaWti->multipliedBy($inputs->coefficient('upstream_realization'));
        $refiningCrack = $inputs->baseline('crack_base')
            ->plus($scenario->deltaWti->multipliedBy($inputs->coefficient('refining_crack')));
        $retailVolumePercent = $inputs->coefficient('retail_demand_elasticity')
            ->multipliedBy(
                $inputs->coefficient('retail_passthrough')
                    ->multipliedBy($scenario->deltaWti)
                    ->dividedBy(self::GALLONS_PER_BARREL, 12, RoundingMode::HalfUp)
                    ->dividedBy($inputs->baseline('pump_base'), 12, RoundingMode::HalfUp)
            )
            ->multipliedBy('100');

        return new Week8ScenarioResult(
            scenarioKey: $scenario->key,
            probability: $scenario->probability,
            wtiResolved: $scenario->wtiResolved,
            deltaWti: $scenario->deltaWti,
            upstreamImpactPerBbl: $upstreamImpact,
            refiningCrack: $refiningCrack,
            retailVolumePercent: $retailVolumePercent,
        );
    }

    /**
     * @return array{expected_upstream_impact_per_bbl: BigDecimal, expected_refining_crack: BigDecimal}
     */
    public function workedExample(Week8EconomicInputs $inputs): array
    {
        $this->assertProbabilityDistribution($this->scenarioProbabilities($inputs->workedExampleScenarios), 'worked example');
        $scenarioResults = [];

        foreach ($inputs->workedExampleScenarios as $scenario) {
            $scenarioResults[$scenario->key] = $this->scenarioResult($inputs, $scenario);
        }

        $probabilities = $this->scenarioProbabilities($inputs->workedExampleScenarios);

        return [
            'expected_upstream_impact_per_bbl' => $this->weightedScenarioResult($scenarioResults, $probabilities, 'upstream'),
            'expected_refining_crack' => $this->weightedScenarioResult($scenarioResults, $probabilities, 'crack'),
        ];
    }

    /**
     * @return array<string, Week8ScenarioResult>
     */
    private function scenarioResults(Week8EconomicInputs $inputs): array
    {
        $results = [];

        foreach ($inputs->scenarios as $scenario) {
            $results[$scenario->key] = $this->scenarioResult($inputs, $scenario);
        }

        return $results;
    }

    /**
     * @param  array<string, Week8Scenario>  $scenarios
     * @return array<string, BigDecimal>
     */
    private function scenarioProbabilities(array $scenarios): array
    {
        $probabilities = [];

        foreach ($scenarios as $key => $scenario) {
            $probabilities[$key] = $scenario->probability;
        }

        return $probabilities;
    }

    /**
     * @param  array<string, BigDecimal|int|string>  $distribution
     * @return array<string, BigDecimal>
     */
    private function normalizeProbabilityDistribution(Week8EconomicInputs $inputs, array $distribution, string $label): array
    {
        $probabilities = [];

        foreach (array_keys($inputs->scenarios) as $scenarioKey) {
            if (! array_key_exists($scenarioKey, $distribution)) {
                throw new InvalidArgumentException("Week 8 {$label} distribution is missing scenario [{$scenarioKey}].");
            }

            $probabilities[$scenarioKey] = BigDecimal::of((string) $distribution[$scenarioKey]);
        }

        $this->assertProbabilityDistribution($probabilities, $label);

        return $probabilities;
    }

    /**
     * @param  array<string, BigDecimal>  $probabilities
     */
    private function assertProbabilityDistribution(array $probabilities, string $label): void
    {
        $sum = BigDecimal::zero();

        foreach ($probabilities as $scenario => $probability) {
            if ($probability->isLessThan(BigDecimal::zero())) {
                throw new InvalidArgumentException("Week 8 {$label} probability for [{$scenario}] cannot be negative.");
            }

            $sum = $sum->plus($probability);
        }

        if (! $sum->toScale(6, RoundingMode::HalfUp)->isEqualTo('1.000000')) {
            throw new InvalidArgumentException("Week 8 {$label} probabilities must sum to 1.000000.");
        }
    }

    /**
     * @param  array<string, Week8Scenario>  $scenarios
     * @param  array<string, BigDecimal>  $probabilities
     */
    private function weightedScenarioValue(array $scenarios, array $probabilities, string $field): BigDecimal
    {
        $sum = BigDecimal::zero();

        foreach ($scenarios as $key => $scenario) {
            $value = match ($field) {
                'wti_resolved' => $scenario->wtiResolved,
                default => throw new InvalidArgumentException("Unsupported Week 8 weighted scenario field [{$field}]."),
            };

            $sum = $sum->plus($value->multipliedBy($probabilities[$key]));
        }

        return $sum;
    }

    /**
     * @param  array<string, Week8ScenarioResult>  $scenarioResults
     * @param  array<string, BigDecimal>  $probabilities
     */
    private function weightedScenarioResult(array $scenarioResults, array $probabilities, string $field): BigDecimal
    {
        $sum = BigDecimal::zero();

        foreach ($scenarioResults as $key => $result) {
            $value = match ($field) {
                'upstream' => $result->upstreamImpactPerBbl,
                'crack' => $result->refiningCrack,
                default => throw new InvalidArgumentException("Unsupported Week 8 weighted result field [{$field}]."),
            };

            $sum = $sum->plus($value->multipliedBy($probabilities[$key]));
        }

        return $sum;
    }

    /**
     * @param  array<string, Week8ScenarioResult>  $scenarioResults
     */
    private function realizedScenario(array $scenarioResults, string $realizedScenarioKey): Week8ScenarioResult
    {
        return $scenarioResults[$realizedScenarioKey]
            ?? throw new InvalidArgumentException("Week 8 realized scenario [{$realizedScenarioKey}] is not present in the reference package.");
    }

    /**
     * @param  array<string, BigDecimal>  $distribution
     * @return array<string, string>
     */
    private function formatDistribution(array $distribution): array
    {
        return array_map(
            fn (BigDecimal $probability): string => (string) $probability->toScale(6, RoundingMode::HalfUp),
            $distribution,
        );
    }

    /**
     * @param  array<string, BigDecimal>|null  $prediction
     * @return array<string, mixed>
     */
    private function inputSnapshot(Week8EconomicInputs $inputs, ?array $prediction, ?string $realizedScenarioKey): array
    {
        return [
            'reference_package' => [
                'version' => $inputs->packageVersion,
                'source_hashes' => $inputs->sourceHashes,
            ],
            'scenarios' => array_map(fn (Week8Scenario $scenario): array => $scenario->snapshot(), $inputs->scenarios),
            'baseline' => $this->formatDecimals($inputs->baseline, 6),
            'coefficients' => $this->formatDecimals($inputs->coefficients, 6),
            'prediction_distribution' => $prediction === null ? null : $this->formatDistribution($prediction),
            'realized_scenario_key' => $realizedScenarioKey,
        ];
    }

    /**
     * @param  array<string, Week8ScenarioResult>  $scenarioResults
     * @param  array<string, BigDecimal>  $probabilities
     * @param  array<string, BigDecimal>|null  $prediction
     * @return array<string, mixed>
     */
    private function outputSnapshot(
        array $scenarioResults,
        array $probabilities,
        ?array $prediction,
        BigDecimal $expectedWti,
        BigDecimal $expectedUpstream,
        BigDecimal $expectedCrack,
        ?BigDecimal $predictionExpectedWti,
        ?BigDecimal $predictionExpectedUpstream,
        ?BigDecimal $predictionExpectedCrack,
        ?Week8ScenarioResult $realizedScenarioResult,
    ): array {
        return [
            'engine' => [
                'identifier' => self::ENGINE_IDENTIFIER,
                'version' => self::ENGINE_VERSION,
            ],
            'scenario_results' => array_map(fn (Week8ScenarioResult $result): array => $result->snapshot(), $scenarioResults),
            'probability_distribution' => $this->formatDistribution($probabilities),
            'expected' => [
                'wti' => (string) $expectedWti->toScale(2, RoundingMode::HalfUp),
                'upstream_impact_per_bbl' => (string) $expectedUpstream->toScale(2, RoundingMode::HalfUp),
                'refining_crack' => (string) $expectedCrack->toScale(2, RoundingMode::HalfUp),
            ],
            'prediction' => $prediction === null ? null : [
                'probability_distribution' => $this->formatDistribution($prediction),
                'expected_wti' => (string) $predictionExpectedWti?->toScale(2, RoundingMode::HalfUp),
                'expected_upstream_impact_per_bbl' => (string) $predictionExpectedUpstream?->toScale(2, RoundingMode::HalfUp),
                'expected_refining_crack' => (string) $predictionExpectedCrack?->toScale(2, RoundingMode::HalfUp),
            ],
            'realization' => $realizedScenarioResult?->snapshot(),
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
