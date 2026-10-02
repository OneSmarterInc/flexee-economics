<?php

namespace App\Domain\Economics\Week13;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week13EconomicResult
{
    /**
     * @param  array<string, array{structure: string, benchmark_wage_k: BigDecimal}>  $wageBenchmarks
     * @param  array<string, BigDecimal>  $workedExample
     * @param  array<string, bool>  $orderingAssertions
     * @param  array<string, mixed>  $inputSnapshot
     * @param  array<string, mixed>  $outputSnapshot
     */
    public function __construct(
        public BigDecimal $norwayGrossCostMusd,
        public BigDecimal $norwayAfterTaxCostMusd,
        public BigDecimal $norwayAfterTaxShare,
        public BigDecimal $permianMrpK,
        public BigDecimal $mrpToWage,
        public BigDecimal $turnaroundPeakCostMusd,
        public BigDecimal $delayExpectedCostMusd,
        public BigDecimal $delaySavingMusd,
        public BigDecimal $delaySavingPct,
        public BigDecimal $assetHealthPenaltyPts,
        public array $wageBenchmarks,
        public array $workedExample,
        public array $orderingAssertions,
        public array $inputSnapshot,
        public array $outputSnapshot,
        public string $status,
        public string $engineIdentifier,
        public string $engineVersion,
        public string $packageVersion,
    ) {}

    public function norwayAfterTaxShareDisplay(): string
    {
        return (string) $this->norwayAfterTaxShare->toScale(6, RoundingMode::HalfUp);
    }

    public function mrpToWageDisplay(): string
    {
        return (string) $this->mrpToWage->toScale(6, RoundingMode::HalfUp);
    }

    public function delaySavingPctDisplay(): string
    {
        return (string) $this->delaySavingPct->toScale(6, RoundingMode::HalfUp);
    }
}
