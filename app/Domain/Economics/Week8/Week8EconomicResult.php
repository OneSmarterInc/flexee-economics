<?php

namespace App\Domain\Economics\Week8;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week8EconomicResult
{
    /**
     * @param  array<string, Week8ScenarioResult>  $scenarioResults
     * @param  array<string, string>  $probabilityDistribution
     * @param  array<string, string>|null  $predictionDistribution
     * @param  array<string, mixed>  $inputSnapshot
     * @param  array<string, mixed>  $outputSnapshot
     */
    public function __construct(
        public array $scenarioResults,
        public array $probabilityDistribution,
        public ?array $predictionDistribution,
        public BigDecimal $expectedWti,
        public BigDecimal $expectedUpstreamImpactPerBbl,
        public BigDecimal $expectedRefiningCrack,
        public ?BigDecimal $predictionExpectedWti,
        public ?BigDecimal $predictionExpectedUpstreamImpactPerBbl,
        public ?BigDecimal $predictionExpectedRefiningCrack,
        public ?Week8ScenarioResult $realizedScenarioResult,
        public array $inputSnapshot,
        public array $outputSnapshot,
        public string $engineIdentifier,
        public string $engineVersion,
    ) {}

    public function expectedWtiMoney(): string
    {
        return (string) $this->expectedWti->toScale(2, RoundingMode::HalfUp);
    }

    public function expectedUpstreamMoney(): string
    {
        return (string) $this->expectedUpstreamImpactPerBbl->toScale(2, RoundingMode::HalfUp);
    }

    public function expectedCrackMoney(): string
    {
        return (string) $this->expectedRefiningCrack->toScale(2, RoundingMode::HalfUp);
    }
}
