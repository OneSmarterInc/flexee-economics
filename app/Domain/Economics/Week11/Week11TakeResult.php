<?php

namespace App\Domain\Economics\Week11;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week11TakeResult
{
    public function __construct(
        public string $scenario,
        public BigDecimal $governmentTake,
        public BigDecimal $companyMarginPerBbl,
        public BigDecimal $pvStayMusd,
        public BigDecimal $stayMinusExitMusd,
    ) {}

    /**
     * @return array<string, string>
     */
    public function snapshot(): array
    {
        return [
            'scenario' => $this->scenario,
            'government_take' => (string) $this->governmentTake->toScale(6, RoundingMode::HalfUp),
            'company_margin_per_bbl' => (string) $this->companyMarginPerBbl->toScale(6, RoundingMode::HalfUp),
            'pv_stay_musd' => (string) $this->pvStayMusd->toScale(6, RoundingMode::HalfUp),
            'stay_minus_exit_musd' => (string) $this->stayMinusExitMusd->toScale(6, RoundingMode::HalfUp),
        ];
    }
}
