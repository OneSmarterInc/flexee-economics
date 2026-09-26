<?php

namespace App\Domain\Economics\Week10;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week10RefineryImpact
{
    /**
     * @param  array<string, BigDecimal>  $productContributions
     */
    public function __construct(
        public string $refinery,
        public BigDecimal $demandHit,
        public array $productContributions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'refinery' => $this->refinery,
            'demand_hit' => (string) $this->demandHit->toScale(6, RoundingMode::HalfUp),
            'product_contributions' => array_map(
                fn (BigDecimal $value): string => (string) $value->toScale(6, RoundingMode::HalfUp),
                $this->productContributions,
            ),
        ];
    }
}
