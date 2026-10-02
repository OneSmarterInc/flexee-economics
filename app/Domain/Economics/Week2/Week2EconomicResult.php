<?php

namespace App\Domain\Economics\Week2;

use Brick\Math\BigDecimal;

final readonly class Week2EconomicResult
{
    /**
     * @param  array<string, Week2ClusterResult>  $clusterResults
     * @param  array<string, BigDecimal>  $workedExample
     * @param  array<string, mixed>  $inputSnapshot
     * @param  array<string, mixed>  $outputSnapshot
     */
    public function __construct(
        public array $clusterResults,
        public BigDecimal $cordellWeightedEstimate,
        public BigDecimal $europeWeightedEstimate,
        public BigDecimal $cordellWeightedPassthrough,
        public array $workedExample,
        public array $inputSnapshot,
        public array $outputSnapshot,
        public string $status,
        public string $engineIdentifier,
        public string $engineVersion,
        public string $packageVersion,
    ) {}
}
