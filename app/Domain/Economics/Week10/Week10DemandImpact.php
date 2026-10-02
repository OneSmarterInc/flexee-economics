<?php

namespace App\Domain\Economics\Week10;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week10DemandImpact
{
    public function __construct(
        public string $product,
        public BigDecimal $incomeElasticity,
        public BigDecimal $demandShare,
        public BigDecimal $demandHit,
    ) {}

    /**
     * @return array<string, string>
     */
    public function snapshot(): array
    {
        return [
            'product' => $this->product,
            'income_elasticity' => (string) $this->incomeElasticity->toScale(6, RoundingMode::HalfUp),
            'demand_share' => (string) $this->demandShare->toScale(6, RoundingMode::HalfUp),
            'demand_hit' => (string) $this->demandHit->toScale(6, RoundingMode::HalfUp),
        ];
    }
}
