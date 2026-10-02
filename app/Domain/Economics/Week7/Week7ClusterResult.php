<?php

namespace App\Domain\Economics\Week7;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week7ClusterResult
{
    public function __construct(
        public string $clusterKey,
        public BigDecimal $atRiskPct,
        public BigDecimal $matchCostMusd,
        public BigDecimal $ignoreCostMusd,
        public string $retailDecision,
    ) {}

    /**
     * @return array<string, string>
     */
    public function snapshot(): array
    {
        return [
            'cluster_key' => $this->clusterKey,
            'at_risk_pct' => $this->decimal($this->atRiskPct),
            'match_cost_musd' => $this->decimal($this->matchCostMusd),
            'ignore_cost_musd' => $this->decimal($this->ignoreCostMusd),
            'retail_decision' => $this->retailDecision,
        ];
    }

    private function decimal(BigDecimal $value): string
    {
        return (string) $value->toScale(6, RoundingMode::HalfUp);
    }
}
