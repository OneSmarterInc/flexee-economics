<?php

namespace App\Domain\Economics\Week2;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week2Cluster
{
    /**
     * @param  list<array{week: int, ln_price: BigDecimal, ln_volume: BigDecimal}>  $observations
     */
    public function __construct(
        public string $key,
        public string $market,
        public BigDecimal $designElasticity,
        public BigDecimal $passthrough,
        public BigDecimal $volumeShare,
        public array $observations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'key' => $this->key,
            'market' => $this->market,
            'design_elasticity' => $this->decimal($this->designElasticity),
            'passthrough' => $this->decimal($this->passthrough),
            'volume_share' => $this->decimal($this->volumeShare),
            'observation_count' => count($this->observations),
        ];
    }

    private function decimal(BigDecimal $value): string
    {
        return (string) $value->toScale(6, RoundingMode::HalfUp);
    }
}
