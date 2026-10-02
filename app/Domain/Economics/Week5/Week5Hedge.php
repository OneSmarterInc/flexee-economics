<?php

namespace App\Domain\Economics\Week5;

use Brick\Math\BigDecimal;

final readonly class Week5Hedge
{
    public function __construct(
        public string $hedgeId,
        public string $pair,
        public string $direction,
        public BigDecimal $notionalMusd,
        public BigDecimal $maturityMonths,
    ) {}

    /**
     * @return array<string, string>
     */
    public function snapshot(): array
    {
        return [
            'hedge_id' => $this->hedgeId,
            'pair' => $this->pair,
            'direction' => $this->direction,
            'notional_musd' => (string) $this->notionalMusd,
            'maturity_months' => (string) $this->maturityMonths,
        ];
    }
}
