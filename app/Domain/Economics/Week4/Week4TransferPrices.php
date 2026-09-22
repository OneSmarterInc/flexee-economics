<?php

namespace App\Domain\Economics\Week4;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week4TransferPrices
{
    public function __construct(
        public BigDecimal $market,
        public BigDecimal $marginalCost,
        public BigDecimal $lazyMidpoint,
    ) {}

    /**
     * @return array{market: string, marginal_cost: string, lazy_midpoint: string}
     */
    public function toPackageArray(): array
    {
        return [
            'market' => $this->formatMoney($this->market),
            'marginal_cost' => $this->formatMoney($this->marginalCost),
            'lazy_midpoint' => $this->formatMoney($this->lazyMidpoint),
        ];
    }

    private function formatMoney(BigDecimal $value): string
    {
        return (string) $value->toScale(2, RoundingMode::Unnecessary);
    }
}
