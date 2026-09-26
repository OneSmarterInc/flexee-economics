<?php

namespace App\Domain\Economics\Week5;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Week5EconomicEngine
{
    public const ENGINE_IDENTIFIER = 'week5_currency_exposure';

    public const ENGINE_VERSION = 'week5_currency_exposure_v1';

    public function calculate(Week5EconomicInputs $inputs): Week5EconomicResult
    {
        $eurChange = $this->quoteCurrencyValueChange($inputs->fxRate('EURUSD'));
        $nokUsdValueChange = $this->baseCurrencyValueChange($inputs->fxRate('USDNOK'));
        $sgdUsdValueChange = $this->baseCurrencyValueChange($inputs->fxRate('USDSGD'));
        $norwayBenefit = $inputs->entityFlow('norway_costs')->annualMusdPre
            ->multipliedBy($nokUsdValueChange)
            ->negated();
        $norwayLiftingPost = $inputs->norwayUnitCost('lifting_usd_pre')->multipliedBy(BigDecimal::one()->plus($nokUsdValueChange));
        $euroRetailTranslation = $inputs->entityFlow('euro_retail_receipts')->annualMusdPre->multipliedBy($eurChange);
        $existingHedgeGain = $inputs->hedge('eur_forward_sell')->notionalMusd->multipliedBy($eurChange)->negated();
        $rotNetEur = $inputs->entityFlow('rot_product_revenue')->annualMusdPre
            ->minus($inputs->entityFlow('rot_eur_costs')->annualMusdPre);
        $rotNaturalHedgeRatio = $inputs->entityFlow('rot_eur_costs')->annualMusdPre
            ->dividedBy($inputs->entityFlow('rot_product_revenue')->annualMusdPre, 12, RoundingMode::HalfUp);
        $rotNetImpact = $rotNetEur->multipliedBy($eurChange);
        $rotOverhedgeLoss = $inputs->entityFlow('rot_eur_costs')->annualMusdPre->multipliedBy($eurChange);
        $singImpact = $inputs->entityFlow('sing_net_earnings')->annualMusdPre->multipliedBy($sgdUsdValueChange);

        return new Week5EconomicResult(
            eurChange: $eurChange,
            nokUsdValueChange: $nokUsdValueChange,
            sgdUsdValueChange: $sgdUsdValueChange,
            norwayBenefitMusd: $norwayBenefit,
            norwayLiftingPost: $norwayLiftingPost,
            euroRetailTranslationMusd: $euroRetailTranslation,
            existingHedgeGainMusd: $existingHedgeGain,
            rotNetEurMusd: $rotNetEur,
            rotNaturalHedgeRatio: $rotNaturalHedgeRatio,
            rotNetImpactMusd: $rotNetImpact,
            rotOverhedgeLossMusd: $rotOverhedgeLoss,
            singImpactMusd: $singImpact,
            inputSnapshot: $this->inputSnapshot($inputs),
            outputSnapshot: $this->outputSnapshot(
                $eurChange,
                $nokUsdValueChange,
                $sgdUsdValueChange,
                $norwayBenefit,
                $norwayLiftingPost,
                $euroRetailTranslation,
                $existingHedgeGain,
                $rotNetEur,
                $rotNaturalHedgeRatio,
                $rotNetImpact,
                $rotOverhedgeLoss,
                $singImpact,
            ),
            engineIdentifier: self::ENGINE_IDENTIFIER,
            engineVersion: self::ENGINE_VERSION,
        );
    }

    /**
     * @return array{fx_change: BigDecimal, net_exposure: BigDecimal, net_impact: BigDecimal, wrong_hedge_on_gross_costs: BigDecimal}
     */
    public function workedExample(Week5EconomicInputs $inputs): array
    {
        $fxChange = $inputs->workedExampleParameter('fx_post')
            ->dividedBy($inputs->workedExampleParameter('fx_pre'), 12, RoundingMode::HalfUp)
            ->minus(BigDecimal::one());
        $netExposure = $inputs->workedExampleParameter('eur_revenue')->minus($inputs->workedExampleParameter('eur_costs'));

        return [
            'fx_change' => $fxChange,
            'net_exposure' => $netExposure,
            'net_impact' => $netExposure->multipliedBy($fxChange),
            'wrong_hedge_on_gross_costs' => $inputs->workedExampleParameter('eur_costs')->multipliedBy($fxChange),
        ];
    }

    private function quoteCurrencyValueChange(Week5FxRate $rate): BigDecimal
    {
        return $rate->post->dividedBy($rate->pre, 12, RoundingMode::HalfUp)->minus(BigDecimal::one());
    }

    private function baseCurrencyValueChange(Week5FxRate $rate): BigDecimal
    {
        return $rate->pre->dividedBy($rate->post, 12, RoundingMode::HalfUp)->minus(BigDecimal::one());
    }

    /**
     * @return array<string, mixed>
     */
    private function inputSnapshot(Week5EconomicInputs $inputs): array
    {
        return [
            'reference_package' => [
                'version' => $inputs->packageVersion,
                'source_hashes' => $inputs->sourceHashes,
            ],
            'fx_rates' => array_map(fn (Week5FxRate $rate): array => $rate->snapshot(), $inputs->fxRates),
            'entity_flows' => array_map(fn (Week5EntityFlow $flow): array => $flow->snapshot(), $inputs->entityFlows),
            'norway_unit_costs' => $this->formatDecimals($inputs->norwayUnitCosts, 6),
            'existing_hedges' => array_map(fn (Week5Hedge $hedge): array => $hedge->snapshot(), $inputs->existingHedges),
            'forward_rates' => $this->formatDecimals($inputs->forwardRates, 6),
            'collar_premiums' => $this->formatDecimals($inputs->collarPremiums, 6),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outputSnapshot(
        BigDecimal $eurChange,
        BigDecimal $nokUsdValueChange,
        BigDecimal $sgdUsdValueChange,
        BigDecimal $norwayBenefit,
        BigDecimal $norwayLiftingPost,
        BigDecimal $euroRetailTranslation,
        BigDecimal $existingHedgeGain,
        BigDecimal $rotNetEur,
        BigDecimal $rotNaturalHedgeRatio,
        BigDecimal $rotNetImpact,
        BigDecimal $rotOverhedgeLoss,
        BigDecimal $singImpact,
    ): array {
        return [
            'engine' => [
                'identifier' => self::ENGINE_IDENTIFIER,
                'version' => self::ENGINE_VERSION,
            ],
            'eur_change' => $this->formatDecimal($eurChange, 6),
            'nok_usd_value_change' => $this->formatDecimal($nokUsdValueChange, 6),
            'sgd_usd_value_change' => $this->formatDecimal($sgdUsdValueChange, 6),
            'norway_benefit_musd' => $this->formatDecimal($norwayBenefit, 6),
            'norway_lifting_post' => $this->formatDecimal($norwayLiftingPost, 6),
            'euro_retail_translation_musd' => $this->formatDecimal($euroRetailTranslation, 6),
            'existing_hedge_gain_musd' => $this->formatDecimal($existingHedgeGain, 6),
            'rot_net_eur_musd' => $this->formatDecimal($rotNetEur, 6),
            'rot_natural_hedge_ratio' => $this->formatDecimal($rotNaturalHedgeRatio, 6),
            'rot_net_impact_musd' => $this->formatDecimal($rotNetImpact, 6),
            'rot_overhedge_loss_musd' => $this->formatDecimal($rotOverhedgeLoss, 6),
            'sing_impact_musd' => $this->formatDecimal($singImpact, 6),
        ];
    }

    /**
     * @param  array<string, BigDecimal>  $values
     * @param  int<0, max>  $scale
     * @return array<string, string>
     */
    private function formatDecimals(array $values, int $scale): array
    {
        return array_map(fn (BigDecimal $value): string => $this->formatDecimal($value, $scale), $values);
    }

    /**
     * @param  int<0, max>  $scale
     */
    private function formatDecimal(BigDecimal $value, int $scale): string
    {
        return (string) $value->toScale($scale, RoundingMode::HalfUp);
    }
}
