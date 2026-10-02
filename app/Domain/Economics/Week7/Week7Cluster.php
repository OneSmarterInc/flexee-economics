<?php

namespace App\Domain\Economics\Week7;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week7Cluster
{
    public function __construct(
        public string $key,
        public BigDecimal $elasticity,
        public BigDecimal $passthrough,
        public BigDecimal $annualVolumeMgal,
        public bool $core,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'key' => $this->key,
            'elasticity' => $this->decimal($this->elasticity),
            'passthrough' => $this->decimal($this->passthrough),
            'annual_volume_mgal' => $this->decimal($this->annualVolumeMgal),
            'core' => $this->core,
        ];
    }

    private function decimal(BigDecimal $value): string
    {
        return (string) $value->toScale(6, RoundingMode::HalfUp);
    }
}
