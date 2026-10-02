<?php

namespace App\Domain\Economics\Week4;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week4GenevaArbitrageResult
{
    public function __construct(
        public BigDecimal $gap,
        public BigDecimal $captureRate,
        public BigDecimal $capturePerBbl,
        public BigDecimal $maxVolumeBblDay,
        public BigDecimal $dailyCaptureAtVolumeCap,
    ) {}

    /**
     * @return array{
     *     market_to_midpoint_gap: string,
     *     capture_rate: string,
     *     capture_per_bbl: string,
     *     max_volume_bbl_day: int,
     *     daily_capture_at_volume_cap: string,
     *     time_basis: string
     * }
     */
    public function toPackageArray(): array
    {
        return [
            'market_to_midpoint_gap' => (string) $this->gap->toScale(2, RoundingMode::Unnecessary),
            'capture_rate' => (string) $this->captureRate,
            'capture_per_bbl' => $this->exactDecimal($this->capturePerBbl),
            'max_volume_bbl_day' => $this->maxVolumeBblDay->toBigInteger()->toInt(),
            'daily_capture_at_volume_cap' => (string) $this->dailyCaptureAtVolumeCap->toScale(3, RoundingMode::Unnecessary),
            'time_basis' => 'unresolved; source only supplies bbl/day volume cap',
        ];
    }

    private function exactDecimal(BigDecimal $value): string
    {
        $decimal = (string) $value;

        if (! str_contains($decimal, '.')) {
            return $decimal;
        }

        return rtrim(rtrim($decimal, '0'), '.');
    }
}
