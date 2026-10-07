<?php

namespace App\Domain\Economics\Week8;

use App\Models\Week8EconomicEvaluation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week8InterimEbitdaBridge
{
    public const IDENTIFIER = 'interim_week8_ebitda_bridge_v0';

    private const BASE_WTI = '81.00';

    private const INTEGRATED_MARGIN_FACTOR = '0.65';

    private const ANNUALIZED_VOLUME_FACTOR = '91.25';

    public function calculate(Week8EconomicEvaluation $evaluation): ?Week8InterimEbitdaBridgeResult
    {
        if ($evaluation->status !== Week8EconomicEvaluation::STATUS_CALCULATED) {
            return null;
        }

        $deltaWti = $this->deltaWti($evaluation);

        if (! $deltaWti instanceof BigDecimal) {
            return null;
        }

        $effect = $deltaWti
            ->multipliedBy(self::INTEGRATED_MARGIN_FACTOR)
            ->multipliedBy(self::ANNUALIZED_VOLUME_FACTOR);

        return new Week8InterimEbitdaBridgeResult(
            deltaWti: $deltaWti,
            ebitdaEffectMusd: $effect,
            provenance: [
                'bridge_identifier' => self::IDENTIFIER,
                'bridge_status' => 'interim',
                'formula' => 'delta_wti * 0.65 * 91.25',
                'delta_wti_source' => $this->deltaWtiSource($evaluation),
                'base_wti' => self::BASE_WTI,
                'integrated_margin_factor' => self::INTEGRATED_MARGIN_FACTOR,
                'annualized_volume_factor' => self::ANNUALIZED_VOLUME_FACTOR,
                'delta_wti' => $this->decimal($deltaWti, 4),
                'ebitda_effect_musd' => $this->decimal($effect, 4),
                'week8_economic_evaluation_id' => $evaluation->id,
                'week8_engine_identifier' => $evaluation->engine_identifier,
                'week8_engine_version' => $evaluation->engine_version,
                'week8_package_version' => $evaluation->package_version,
                'realized_scenario_key' => $evaluation->realized_scenario_key,
                'realized_wti' => $evaluation->realized_wti,
                'future_authoritative_mapping_pending' => true,
                'excluded_terms' => ['hedge_coverage', 'production_posture', 'position_aware_exposure'],
            ],
        );
    }

    private function deltaWti(Week8EconomicEvaluation $evaluation): ?BigDecimal
    {
        $realization = $evaluation->getAttribute('realization_snapshot');

        if (is_array($realization) && array_key_exists('delta_wti', $realization) && $realization['delta_wti'] !== null) {
            return BigDecimal::of((string) $realization['delta_wti']);
        }

        if ($evaluation->realized_wti !== null) {
            return BigDecimal::of((string) $evaluation->realized_wti)->minus(self::BASE_WTI);
        }

        return null;
    }

    private function deltaWtiSource(Week8EconomicEvaluation $evaluation): string
    {
        $realization = $evaluation->getAttribute('realization_snapshot');

        if (is_array($realization) && array_key_exists('delta_wti', $realization) && $realization['delta_wti'] !== null) {
            return 'realization_snapshot.delta_wti';
        }

        if ($evaluation->realized_wti !== null) {
            return 'realized_wti_minus_81.00';
        }

        return 'missing';
    }

    /**
     * @param  int<0, max>  $scale
     */
    private function decimal(BigDecimal $value, int $scale): string
    {
        return (string) $value->toScale($scale, RoundingMode::HalfUp);
    }
}
