<?php

namespace App\Domain\Economics\Week3;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final readonly class Week3EconomicInputs
{
    /**
     * @param  array<string, array<string, BigDecimal|string>>  $refineries
     * @param  array<string, BigDecimal>  $windowParameters
     * @param  array<string, BigDecimal>  $cohortStates
     * @param  array<string, mixed>  $golden
     * @param  array<string, mixed>  $sourceHashes
     */
    public function __construct(
        public array $refineries,
        public BigDecimal $restartCostMusd,
        public array $windowParameters,
        public array $cohortStates,
        public array $golden,
        public array $sourceHashes,
        public string $packageVersion,
    ) {}

    /**
     * @return array<string, BigDecimal|string>
     */
    public function refinery(string $key): array
    {
        return $this->refineries[$key]
            ?? throw new InvalidArgumentException("Week 3 refinery [{$key}] is not available.");
    }

    public function windowParameter(string $key): BigDecimal
    {
        return $this->windowParameters[$key]
            ?? throw new InvalidArgumentException("Week 3 Window 1 parameter [{$key}] is not available.");
    }
}
