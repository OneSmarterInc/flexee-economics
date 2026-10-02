<?php

namespace App\Domain\Capital\Week6;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final readonly class Week6CapitalEconomicInputs
{
    /**
     * @param  array<string, Week6ProjectCashFlows>  $projects
     * @param  array<string, array{rate: BigDecimal, envelope_musd: BigDecimal}>  $cohortSchedule
     * @param  array<string, BigDecimal>  $haircuts
     * @param  array<string, mixed>  $golden
     * @param  array<string, string>  $sourceHashes
     */
    public function __construct(
        public array $projects,
        public array $cohortSchedule,
        public array $haircuts,
        public array $golden,
        public array $sourceHashes,
        public string $packageVersion,
    ) {}

    public function project(string $key): Week6ProjectCashFlows
    {
        return $this->projects[$key]
            ?? throw new InvalidArgumentException("Week 6 project [{$key}] is not present in the reference package.");
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function discountRateForContext(array $context): BigDecimal
    {
        $rate = $context['discount_rate_percent'] ?? null;

        if ($rate === null && isset($context['classification'], $this->cohortSchedule[(string) $context['classification']])) {
            return $this->cohortSchedule[(string) $context['classification']]['rate'];
        }

        if (! is_string($rate) && ! is_int($rate)) {
            throw new InvalidArgumentException('Week 6 capital economics requires an available discount-rate context.');
        }

        $value = BigDecimal::of((string) $rate);

        return $value->isGreaterThan('1')
            ? $value->dividedBy('100', 12)
            : $value;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function capitalEnvelopeForContext(array $context): BigDecimal
    {
        $envelope = $context['capital_envelope_musd'] ?? null;

        if ($envelope === null && isset($context['classification'], $this->cohortSchedule[(string) $context['classification']])) {
            return $this->cohortSchedule[(string) $context['classification']]['envelope_musd'];
        }

        if (! is_string($envelope) && ! is_int($envelope)) {
            throw new InvalidArgumentException('Week 6 capital economics requires an available capital-envelope context.');
        }

        return BigDecimal::of((string) $envelope);
    }
}
