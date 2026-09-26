<?php

namespace App\Domain\Economics\Week9;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week9EconomicResult
{
    /**
     * @param  array<string, Week9MarketResult>  $marketResults
     * @param  list<string>  $partialRebrandMarkets
     * @param  array<string, mixed>  $inputSnapshot
     * @param  array<string, mixed>  $outputSnapshot
     */
    public function __construct(
        public array $marketResults,
        public array $partialRebrandMarkets,
        public string $nonfuelStateKey,
        public BigDecimal $costPerSite,
        public BigDecimal $partialGainMusd,
        public BigDecimal $partialCostMusd,
        public BigDecimal $partialPaybackYears,
        public BigDecimal $fullNetGainMusd,
        public BigDecimal $priceWarPartialPaybackYears,
        public array $inputSnapshot,
        public array $outputSnapshot,
        public string $engineIdentifier,
        public string $engineVersion,
    ) {}

    public function costPerSiteDisplay(): string
    {
        return (string) $this->costPerSite->toScale(4, RoundingMode::HalfUp);
    }

    public function partialGainDisplay(): string
    {
        return (string) $this->partialGainMusd->toScale(4, RoundingMode::HalfUp);
    }

    public function partialCostDisplay(): string
    {
        return (string) $this->partialCostMusd->toScale(4, RoundingMode::HalfUp);
    }

    public function partialPaybackDisplay(): string
    {
        return (string) $this->partialPaybackYears->toScale(4, RoundingMode::HalfUp);
    }

    public function fullNetGainDisplay(): string
    {
        return (string) $this->fullNetGainMusd->toScale(4, RoundingMode::HalfUp);
    }

    public function priceWarPartialPaybackDisplay(): string
    {
        return (string) $this->priceWarPartialPaybackYears->toScale(4, RoundingMode::HalfUp);
    }
}
