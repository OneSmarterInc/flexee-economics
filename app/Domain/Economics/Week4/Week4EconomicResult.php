<?php

namespace App\Domain\Economics\Week4;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week4EconomicResult
{
    public function __construct(
        public BigDecimal $transferPrice,
        public BigDecimal $deliveredMarginalCost,
        public BigDecimal $integratedMargin,
        public BigDecimal $upstreamMargin,
        public BigDecimal $refiningMargin,
        public BigDecimal $upstreamVsTarget,
        public BigDecimal $refiningVsTarget,
    ) {}

    /**
     * @return array{
     *     transfer_price: string,
     *     upstream_margin: string,
     *     refining_margin: string,
     *     upstream_vs_target: string,
     *     refining_vs_target: string,
     *     integrated_margin: string
     * }
     */
    public function toPackageSegmentArray(): array
    {
        return [
            'transfer_price' => $this->money($this->transferPrice),
            'upstream_margin' => $this->money($this->upstreamMargin),
            'refining_margin' => $this->money($this->refiningMargin),
            'upstream_vs_target' => $this->money($this->upstreamVsTarget),
            'refining_vs_target' => $this->money($this->refiningVsTarget),
            'integrated_margin' => $this->money($this->integratedMargin),
        ];
    }

    public function money(BigDecimal $value): string
    {
        return (string) $value->toScale(2, RoundingMode::Unnecessary);
    }
}
