<?php

namespace App\Domain\Economics\Week9;

use Brick\Math\BigDecimal;

final readonly class Week9Market
{
    public function __construct(
        public string $key,
        public string $equity,
        public BigDecimal $sites,
        public BigDecimal $keepUpliftPerFill,
        public BigDecimal $haldenBenefitPerFill,
    ) {}

    public function netPerFill(): BigDecimal
    {
        return $this->haldenBenefitPerFill->minus($this->keepUpliftPerFill);
    }

    /**
     * @return array<string, string>
     */
    public function snapshot(): array
    {
        return [
            'key' => $this->key,
            'equity' => $this->equity,
            'sites' => (string) $this->sites,
            'keep_uplift_per_fill' => (string) $this->keepUpliftPerFill,
            'halden_benefit_per_fill' => (string) $this->haldenBenefitPerFill,
            'net_per_fill' => (string) $this->netPerFill(),
        ];
    }
}
