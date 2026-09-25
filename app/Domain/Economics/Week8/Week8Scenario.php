<?php

namespace App\Domain\Economics\Week8;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week8Scenario
{
    public function __construct(
        public string $key,
        public BigDecimal $probability,
        public BigDecimal $wtiResolved,
        public BigDecimal $deltaWti,
    ) {}

    /**
     * @return array<string, string>
     */
    public function snapshot(): array
    {
        return [
            'key' => $this->key,
            'probability' => (string) $this->probability->toScale(6, RoundingMode::HalfUp),
            'wti_resolved' => (string) $this->wtiResolved->toScale(2, RoundingMode::HalfUp),
            'delta_wti' => (string) $this->deltaWti->toScale(2, RoundingMode::HalfUp),
        ];
    }
}
