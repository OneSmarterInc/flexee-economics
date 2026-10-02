<?php

namespace App\Domain\Economics\Week9;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week9MarketResult
{
    public function __construct(
        public string $marketKey,
        public string $equity,
        public BigDecimal $sites,
        public BigDecimal $netPerFill,
        public BigDecimal $baseGainMusd,
        public BigDecimal $stateAdjustedGainMusd,
        public BigDecimal $costMusd,
        public ?BigDecimal $paybackYears,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function snapshot(): array
    {
        return [
            'market_key' => $this->marketKey,
            'equity' => $this->equity,
            'sites' => (string) $this->sites->toScale(0, RoundingMode::HalfUp),
            'net_per_fill' => (string) $this->netPerFill->toScale(6, RoundingMode::HalfUp),
            'base_gain_musd' => (string) $this->baseGainMusd->toScale(6, RoundingMode::HalfUp),
            'state_adjusted_gain_musd' => (string) $this->stateAdjustedGainMusd->toScale(6, RoundingMode::HalfUp),
            'cost_musd' => (string) $this->costMusd->toScale(6, RoundingMode::HalfUp),
            'payback_years' => $this->paybackYears === null
                ? null
                : (string) $this->paybackYears->toScale(6, RoundingMode::HalfUp),
        ];
    }
}
