<?php

namespace App\Domain\Economics\Week7;

use Brick\Math\BigDecimal;

final readonly class Week7EconomicResult
{
    /**
     * @param  array<string, Week7ClusterResult>  $clusterResults
     * @param  array<string, mixed>  $windowResults
     * @param  array<string, mixed>  $workedExample
     * @param  array<string, mixed>  $inputSnapshot
     * @param  array<string, mixed>  $outputSnapshot
     */
    public function __construct(
        public array $clusterResults,
        public BigDecimal $evHoldMusd,
        public BigDecimal $evMatchMusd,
        public BigDecimal $breakevenBuildProbability,
        public string $capacityDecision,
        public array $windowResults,
        public array $workedExample,
        public array $inputSnapshot,
        public array $outputSnapshot,
        public string $packageVersion,
        public string $engineIdentifier,
        public string $engineVersion,
    ) {}
}
