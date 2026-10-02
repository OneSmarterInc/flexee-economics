<?php

namespace App\Domain\Economics\Week9;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final readonly class Week9EconomicInputs
{
    /**
     * @param  array<string, Week9Market>  $markets
     * @param  array<string, BigDecimal>  $nonfuelStates
     * @param  array<string, BigDecimal>  $rebrandParameters
     * @param  array<string, BigDecimal>  $workedExampleParameters
     * @param  array<string, mixed>  $golden
     * @param  array<string, string>  $sourceHashes
     */
    public function __construct(
        public array $markets,
        public array $nonfuelStates,
        public array $rebrandParameters,
        public array $workedExampleParameters,
        public array $golden,
        public array $sourceHashes,
        public string $packageVersion,
    ) {}

    public function market(string $key): Week9Market
    {
        return $this->markets[$key]
            ?? throw new InvalidArgumentException("Week 9 market [{$key}] is not present in the reference package.");
    }

    public function nonfuelState(string $key): BigDecimal
    {
        return $this->nonfuelStates[$key]
            ?? throw new InvalidArgumentException("Week 9 non-fuel state [{$key}] is not present in the reference package.");
    }

    public function rebrandParameter(string $key): BigDecimal
    {
        return $this->rebrandParameters[$key]
            ?? throw new InvalidArgumentException("Week 9 rebrand parameter [{$key}] is not present in the reference package.");
    }

    public function workedExampleParameter(string $key): BigDecimal
    {
        return $this->workedExampleParameters[$key]
            ?? throw new InvalidArgumentException("Week 9 worked-example parameter [{$key}] is not present in the reference package.");
    }
}
