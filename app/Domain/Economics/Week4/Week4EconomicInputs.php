<?php

namespace App\Domain\Economics\Week4;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final readonly class Week4EconomicInputs
{
    public function __construct(
        public BigDecimal $cashLiftingCost,
        public BigDecimal $gatheringCost,
        public BigDecimal $transportPermianToBatonRouge,
        public BigDecimal $permianWellheadDiscount,
        public BigDecimal $shortRunCapitalCharge,
        public BigDecimal $wti,
        public BigDecimal $gulfCoastCrack,
        public BigDecimal $batonRougeComplexityPremium,
        public BigDecimal $batonRougeOpex,
        public BigDecimal $genevaCaptureRate,
        public BigDecimal $genevaMaxVolumeBblDay,
        public BigDecimal $upstreamTargetMargin,
        public BigDecimal $refiningTargetMargin,
    ) {}

    /**
     * @param  array<string, string|int|float>  $costConstants
     * @param  array<string, string|int|float>  $liftingCostsByVintage
     * @param  array<string, string|int|float>  $segmentTargets
     */
    public static function fromReferenceData(
        array $costConstants,
        array $liftingCostsByVintage,
        array $segmentTargets,
        string $marginalVintage = '2024',
    ): self {
        return new self(
            cashLiftingCost: self::requiredDecimal($liftingCostsByVintage, $marginalVintage),
            gatheringCost: self::requiredDecimal($costConstants, 'gathering_cost'),
            transportPermianToBatonRouge: self::requiredDecimal($costConstants, 'transport_permian_to_br'),
            permianWellheadDiscount: self::requiredDecimal($costConstants, 'permian_wellhead_discount'),
            shortRunCapitalCharge: self::requiredDecimal($costConstants, 'sr_capital_charge'),
            wti: self::requiredDecimal($costConstants, 'wti'),
            gulfCoastCrack: self::requiredDecimal($costConstants, 'gc_crack_321'),
            batonRougeComplexityPremium: self::requiredDecimal($costConstants, 'br_complexity_premium'),
            batonRougeOpex: self::requiredDecimal($costConstants, 'br_opex'),
            genevaCaptureRate: self::requiredDecimal($costConstants, 'geneva_capture_rate'),
            genevaMaxVolumeBblDay: self::requiredDecimal($costConstants, 'geneva_max_volume'),
            upstreamTargetMargin: self::requiredDecimal($segmentTargets, 'upstream'),
            refiningTargetMargin: self::requiredDecimal($segmentTargets, 'refining'),
        );
    }

    /**
     * @param  array<string, string|int|float>  $values
     */
    private static function requiredDecimal(array $values, string $key): BigDecimal
    {
        if (! array_key_exists($key, $values)) {
            throw new InvalidArgumentException("Missing Week 4 economic input [{$key}].");
        }

        return BigDecimal::of((string) $values[$key]);
    }
}
