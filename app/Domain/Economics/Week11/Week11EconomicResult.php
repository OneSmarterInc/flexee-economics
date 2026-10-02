<?php

namespace App\Domain\Economics\Week11;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week11EconomicResult
{
    /**
     * @param  array<string, Week11TakeResult>  $takeResults
     * @param  array<string, BigDecimal>  $workedExample
     * @param  array<string, mixed>  $inputSnapshot
     * @param  array<string, mixed>  $outputSnapshot
     */
    public function __construct(
        public BigDecimal $realizedPrice,
        public BigDecimal $profitOil,
        public BigDecimal $annualMbbl,
        public BigDecimal $annuityFactor,
        public BigDecimal $exitValueMusd,
        public BigDecimal $indifferenceTake,
        public BigDecimal $comparablesMin,
        public BigDecimal $comparablesMax,
        public BigDecimal $demandedTake,
        public bool $demandedTakeInsideComparables,
        public bool $stayingBeatsExitAcrossTakeGrid,
        public bool $stayValueFallsAsTakeRises,
        public bool $sunkInvariant,
        public array $takeResults,
        public array $workedExample,
        public array $inputSnapshot,
        public array $outputSnapshot,
        public string $engineIdentifier,
        public string $engineVersion,
        public string $packageVersion,
    ) {}

    public function takeResult(string $scenario): Week11TakeResult
    {
        return $this->takeResults[$scenario];
    }

    public function profitOilDisplay(): string
    {
        return (string) $this->profitOil->toScale(4, RoundingMode::HalfUp);
    }

    public function annuityFactorDisplay(): string
    {
        return (string) $this->annuityFactor->toScale(6, RoundingMode::HalfUp);
    }

    public function indifferenceTakeDisplay(): string
    {
        return (string) $this->indifferenceTake->toScale(6, RoundingMode::HalfUp);
    }
}
