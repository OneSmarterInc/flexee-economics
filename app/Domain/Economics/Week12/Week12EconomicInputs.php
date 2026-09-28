<?php

namespace App\Domain\Economics\Week12;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final readonly class Week12EconomicInputs
{
    /**
     * @param  array<string, BigDecimal>  $envelope
     * @param  array<string, array{floor: BigDecimal, ceiling: BigDecimal}>  $buckets
     * @param  array<string, array{bucket: string, cost_musd: BigDecimal, npv_base: BigDecimal, carbon_sens: BigDecimal, demand_sens: BigDecimal}>  $projects
     * @param  array<string, BigDecimal>  $carbonScenarios
     * @param  array<string, BigDecimal>  $demandScenarios
     * @param  array<string, BigDecimal>  $workedExample
     * @param  array<string, mixed>  $golden
     * @param  array<string, string>  $sourceHashes
     */
    public function __construct(
        public array $envelope,
        public array $buckets,
        public array $projects,
        public array $carbonScenarios,
        public array $demandScenarios,
        public array $workedExample,
        public array $golden,
        public array $sourceHashes,
        public string $packageVersion,
    ) {}

    public function envelopeParameter(string $key): BigDecimal
    {
        return $this->envelope[$key]
            ?? throw new InvalidArgumentException("Week 12 envelope parameter [{$key}] is not present in the reference package.");
    }

    /**
     * @return array{floor: BigDecimal, ceiling: BigDecimal}
     */
    public function bucket(string $key): array
    {
        return $this->buckets[$key]
            ?? throw new InvalidArgumentException("Week 12 capital bucket [{$key}] is not present in the reference package.");
    }

    /**
     * @return array{bucket: string, cost_musd: BigDecimal, npv_base: BigDecimal, carbon_sens: BigDecimal, demand_sens: BigDecimal}
     */
    public function project(string $key): array
    {
        return $this->projects[$key]
            ?? throw new InvalidArgumentException("Week 12 project [{$key}] is not present in the reference package.");
    }

    public function workedExampleParameter(string $key): BigDecimal
    {
        return $this->workedExample[$key]
            ?? throw new InvalidArgumentException("Week 12 worked-example parameter [{$key}] is not present in the reference package.");
    }
}
