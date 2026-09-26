<?php

namespace App\Domain\Economics\Week5;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week5EconomicResult
{
    /**
     * @param  array<string, mixed>  $inputSnapshot
     * @param  array<string, mixed>  $outputSnapshot
     */
    public function __construct(
        public BigDecimal $eurChange,
        public BigDecimal $nokUsdValueChange,
        public BigDecimal $sgdUsdValueChange,
        public BigDecimal $norwayBenefitMusd,
        public BigDecimal $norwayLiftingPost,
        public BigDecimal $euroRetailTranslationMusd,
        public BigDecimal $existingHedgeGainMusd,
        public BigDecimal $rotNetEurMusd,
        public BigDecimal $rotNaturalHedgeRatio,
        public BigDecimal $rotNetImpactMusd,
        public BigDecimal $rotOverhedgeLossMusd,
        public BigDecimal $singImpactMusd,
        public array $inputSnapshot,
        public array $outputSnapshot,
        public string $engineIdentifier,
        public string $engineVersion,
    ) {}

    /**
     * @param  int<0, max>  $scale
     */
    public function display(BigDecimal $value, int $scale = 4): string
    {
        return (string) $value->toScale($scale, RoundingMode::HalfUp);
    }
}
