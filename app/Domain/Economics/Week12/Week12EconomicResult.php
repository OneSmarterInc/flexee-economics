<?php

namespace App\Domain\Economics\Week12;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week12EconomicResult
{
    /**
     * @param  array<string, array<string, BigDecimal>>  $projectScenarioNpvs
     * @param  array<string, array{min: BigDecimal, max: BigDecimal}>  $projectNpvRanges
     * @param  array<string, BigDecimal>  $workedExample
     * @param  list<Week12PortfolioResult>  $portfolioResults
     * @param  array<string, mixed>  $inputSnapshot
     * @param  array<string, mixed>  $outputSnapshot
     */
    public function __construct(
        public BigDecimal $discretionaryEnvelopeMusd,
        public BigDecimal $envelopeWithDivestmentMusd,
        public BigDecimal $helixRotterdamCostMusd,
        public BigDecimal $adjacentCeilingMusd,
        public BigDecimal $hrPlusWindCostMusd,
        public bool $hrPlusWindNeedsDivestment,
        public int $feasiblePortfolioCount,
        public int $feasibleWithHelixRotterdamCount,
        public int $portfoliosUnlockedByDivestmentCount,
        public array $projectScenarioNpvs,
        public array $projectNpvRanges,
        public array $workedExample,
        public array $portfolioResults,
        public array $inputSnapshot,
        public array $outputSnapshot,
        public string $status,
        public string $engineIdentifier,
        public string $engineVersion,
        public string $packageVersion,
    ) {}

    public function portfolioContaining(string ...$projectKeys): ?Week12PortfolioResult
    {
        sort($projectKeys);

        foreach ($this->portfolioResults as $portfolioResult) {
            $portfolioKeys = $portfolioResult->projectKeys;
            sort($portfolioKeys);

            if ($portfolioKeys === $projectKeys) {
                return $portfolioResult;
            }
        }

        return null;
    }

    public function discretionaryDisplay(): string
    {
        return (string) $this->discretionaryEnvelopeMusd->toScale(4, RoundingMode::HalfUp);
    }
}
