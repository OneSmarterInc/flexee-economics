<?php

namespace App\Domain\Economics\Week7;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final readonly class Week7EconomicInputs
{
    /**
     * @param  array<string, Week7Cluster>  $clusters
     * @param  array<string, BigDecimal>  $rivalMove
     * @param  array<string, BigDecimal>  $capacityPayoffs
     * @param  array<string, BigDecimal>  $windowParameters
     * @param  array<string, BigDecimal>  $cohortStates
     * @param  array<string, BigDecimal>  $workedExample
     * @param  array<string, mixed>  $golden
     * @param  array<string, mixed>  $sourceHashes
     */
    public function __construct(
        public array $clusters,
        public array $rivalMove,
        public array $capacityPayoffs,
        public array $windowParameters,
        public array $cohortStates,
        public array $workedExample,
        public array $golden,
        public array $sourceHashes,
        public string $packageVersion,
    ) {}

    public function rivalParameter(string $key): BigDecimal
    {
        return $this->rivalMove[$key]
            ?? throw new InvalidArgumentException("Week 7 rival-move parameter [{$key}] is not available.");
    }

    public function windowParameter(string $key): BigDecimal
    {
        return $this->windowParameters[$key]
            ?? throw new InvalidArgumentException("Week 7 Window 3 parameter [{$key}] is not available.");
    }
}
