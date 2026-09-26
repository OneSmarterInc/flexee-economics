<?php

namespace App\Domain\Economics\Week5;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final readonly class Week5EconomicInputs
{
    /**
     * @param  array<string, Week5FxRate>  $fxRates
     * @param  array<string, Week5EntityFlow>  $entityFlows
     * @param  array<string, BigDecimal>  $norwayUnitCosts
     * @param  array<string, Week5Hedge>  $existingHedges
     * @param  array<string, BigDecimal>  $forwardRates
     * @param  array<string, BigDecimal>  $collarPremiums
     * @param  array<string, BigDecimal>  $workedExampleParameters
     * @param  array<string, mixed>  $golden
     * @param  array<string, string>  $sourceHashes
     */
    public function __construct(
        public array $fxRates,
        public array $entityFlows,
        public array $norwayUnitCosts,
        public array $existingHedges,
        public array $forwardRates,
        public array $collarPremiums,
        public array $workedExampleParameters,
        public array $golden,
        public array $sourceHashes,
        public string $packageVersion,
    ) {}

    public function fxRate(string $pair): Week5FxRate
    {
        return $this->fxRates[$pair]
            ?? throw new InvalidArgumentException("Week 5 FX rate [{$pair}] is not present in the reference package.");
    }

    public function entityFlow(string $flowId): Week5EntityFlow
    {
        return $this->entityFlows[$flowId]
            ?? throw new InvalidArgumentException("Week 5 entity flow [{$flowId}] is not present in the reference package.");
    }

    public function norwayUnitCost(string $parameter): BigDecimal
    {
        return $this->norwayUnitCosts[$parameter]
            ?? throw new InvalidArgumentException("Week 5 Norway unit-cost parameter [{$parameter}] is not present in the reference package.");
    }

    public function hedge(string $hedgeId): Week5Hedge
    {
        return $this->existingHedges[$hedgeId]
            ?? throw new InvalidArgumentException("Week 5 hedge [{$hedgeId}] is not present in the reference package.");
    }

    public function workedExampleParameter(string $parameter): BigDecimal
    {
        return $this->workedExampleParameters[$parameter]
            ?? throw new InvalidArgumentException("Week 5 worked-example parameter [{$parameter}] is not present in the reference package.");
    }
}
