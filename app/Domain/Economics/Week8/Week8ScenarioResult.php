<?php

namespace App\Domain\Economics\Week8;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week8ScenarioResult
{
    public function __construct(
        public string $scenarioKey,
        public BigDecimal $probability,
        public BigDecimal $wtiResolved,
        public BigDecimal $deltaWti,
        public BigDecimal $upstreamImpactPerBbl,
        public BigDecimal $refiningCrack,
        public BigDecimal $retailVolumePercent,
        public ?BigDecimal $opecRefiningCrack = null,
        public ?BigDecimal $cohortRefiningCrackShift = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function snapshot(): array
    {
        return [
            'scenario_key' => $this->scenarioKey,
            'probability' => (string) $this->probability->toScale(6, RoundingMode::HalfUp),
            'wti_resolved' => (string) $this->wtiResolved->toScale(2, RoundingMode::HalfUp),
            'delta_wti' => (string) $this->deltaWti->toScale(2, RoundingMode::HalfUp),
            'upstream_impact_per_bbl' => (string) $this->upstreamImpactPerBbl->toScale(2, RoundingMode::HalfUp),
            'refining_crack' => (string) $this->refiningCrack->toScale(2, RoundingMode::HalfUp),
            'opec_refining_crack' => (string) ($this->opecRefiningCrack ?? $this->refiningCrack)->toScale(2, RoundingMode::HalfUp),
            'cohort_refining_crack_shift' => (string) ($this->cohortRefiningCrackShift ?? BigDecimal::zero())->toScale(2, RoundingMode::HalfUp),
            'retail_volume_percent' => (string) $this->retailVolumePercent->toScale(3, RoundingMode::HalfEven),
        ];
    }
}
