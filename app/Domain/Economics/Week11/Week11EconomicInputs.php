<?php

namespace App\Domain\Economics\Week11;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final readonly class Week11EconomicInputs
{
    /**
     * @param  array<string, BigDecimal>  $pscTerms
     * @param  array<string, BigDecimal>  $reserves
     * @param  array<string, BigDecimal>  $exitAndSunk
     * @param  array<string, BigDecimal>  $takeGrid
     * @param  array<string, BigDecimal>  $comparableTerms
     * @param  array<string, BigDecimal>  $workedExample
     * @param  array<string, mixed>  $golden
     * @param  array<string, string>  $sourceHashes
     */
    public function __construct(
        public array $pscTerms,
        public array $reserves,
        public array $exitAndSunk,
        public array $takeGrid,
        public array $comparableTerms,
        public array $workedExample,
        public array $golden,
        public array $sourceHashes,
        public string $packageVersion,
    ) {}

    public function pscTerm(string $key): BigDecimal
    {
        return $this->pscTerms[$key]
            ?? throw new InvalidArgumentException("Week 11 PSC term [{$key}] is not present in the reference package.");
    }

    public function reserve(string $key): BigDecimal
    {
        return $this->reserves[$key]
            ?? throw new InvalidArgumentException("Week 11 reserve parameter [{$key}] is not present in the reference package.");
    }

    public function exitAndSunk(string $key): BigDecimal
    {
        return $this->exitAndSunk[$key]
            ?? throw new InvalidArgumentException("Week 11 exit/sunk parameter [{$key}] is not present in the reference package.");
    }

    public function take(string $scenario): BigDecimal
    {
        return $this->takeGrid[$scenario]
            ?? throw new InvalidArgumentException("Week 11 take scenario [{$scenario}] is not present in the reference package.");
    }

    public function workedExampleParameter(string $key): BigDecimal
    {
        return $this->workedExample[$key]
            ?? throw new InvalidArgumentException("Week 11 worked-example parameter [{$key}] is not present in the reference package.");
    }
}
