<?php

namespace App\Domain\Economics\Week8;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final readonly class Week8EconomicInputs
{
    /**
     * @param  array<string, Week8Scenario>  $scenarios
     * @param  array<string, BigDecimal>  $coefficients
     * @param  array<string, BigDecimal>  $baseline
     * @param  array<string, Week8Scenario>  $workedExampleScenarios
     * @param  array<string, mixed>  $golden
     * @param  array<string, string>  $sourceHashes
     */
    public function __construct(
        public array $scenarios,
        public array $coefficients,
        public array $baseline,
        public array $workedExampleScenarios,
        public array $golden,
        public array $sourceHashes,
        public string $packageVersion,
    ) {}

    public function scenario(string $key): Week8Scenario
    {
        return $this->scenarios[$key]
            ?? throw new InvalidArgumentException("Week 8 scenario [{$key}] is not present in the reference package.");
    }

    public function coefficient(string $key): BigDecimal
    {
        return $this->coefficients[$key]
            ?? throw new InvalidArgumentException("Week 8 propagation coefficient [{$key}] is not present in the reference package.");
    }

    public function baseline(string $key): BigDecimal
    {
        return $this->baseline[$key]
            ?? throw new InvalidArgumentException("Week 8 baseline parameter [{$key}] is not present in the reference package.");
    }
}
