<?php

namespace App\Domain\Economics\Week2;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final readonly class Week2EconomicInputs
{
    /**
     * @param  array<string, Week2Cluster>  $clusters
     * @param  array<string, BigDecimal>  $pricingParameters
     * @param  array<string, array{fuel_margin_per_gal: BigDecimal, nonfuel_per_fill: BigDecimal}>  $fuelNonfuel
     * @param  list<array{week: int, ln_price: BigDecimal, ln_volume: BigDecimal}>  $workedExampleObservations
     * @param  array<string, BigDecimal>  $workedExampleParameters
     * @param  array<string, mixed>  $golden
     * @param  array<string, string>  $sourceHashes
     */
    public function __construct(
        public array $clusters,
        public array $pricingParameters,
        public array $fuelNonfuel,
        public array $workedExampleObservations,
        public array $workedExampleParameters,
        public array $golden,
        public array $sourceHashes,
        public string $packageVersion,
    ) {}

    public function pricingParameter(string $key): BigDecimal
    {
        return $this->pricingParameters[$key]
            ?? throw new InvalidArgumentException("Week 2 pricing parameter [{$key}] is not present in the reference package.");
    }

    public function workedExampleParameter(string $key): BigDecimal
    {
        return $this->workedExampleParameters[$key]
            ?? throw new InvalidArgumentException("Week 2 worked-example parameter [{$key}] is not present in the reference package.");
    }
}
