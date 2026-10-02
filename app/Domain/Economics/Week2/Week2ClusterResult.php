<?php

namespace App\Domain\Economics\Week2;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week2ClusterResult
{
    public function __construct(
        public string $clusterKey,
        public string $market,
        public BigDecimal $estimatedElasticity,
        public BigDecimal $designElasticity,
        public BigDecimal $passthrough,
        public BigDecimal $volumeShare,
        public BigDecimal $volumeResponsePct,
    ) {}

    /**
     * @return array<string, string>
     */
    public function snapshot(): array
    {
        return [
            'cluster_key' => $this->clusterKey,
            'market' => $this->market,
            'estimated_elasticity' => $this->decimal($this->estimatedElasticity),
            'design_elasticity' => $this->decimal($this->designElasticity),
            'passthrough' => $this->decimal($this->passthrough),
            'volume_share' => $this->decimal($this->volumeShare),
            'volume_response_pct' => $this->decimal($this->volumeResponsePct),
        ];
    }

    private function decimal(BigDecimal $value): string
    {
        return (string) $value->toScale(6, RoundingMode::HalfUp);
    }
}
