<?php

namespace App\Domain\Economics\Week4;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Week4EconomicEngine
{
    public const ENGINE_IDENTIFIER = 'week4_transfer_pricing';

    public const ENGINE_VERSION = 'week4_transfer_pricing_v1';

    public function deliveredMarginalCost(Week4EconomicInputs $inputs): BigDecimal
    {
        return $inputs->cashLiftingCost
            ->plus($inputs->gatheringCost)
            ->plus($inputs->transportPermianToBatonRouge);
    }

    public function realizedWellheadPrice(Week4EconomicInputs $inputs): BigDecimal
    {
        return $inputs->wti->minus($inputs->permianWellheadDiscount);
    }

    public function productSlateValue(Week4EconomicInputs $inputs): BigDecimal
    {
        return $this->realizedWellheadPrice($inputs)
            ->plus($inputs->gulfCoastCrack)
            ->plus($inputs->batonRougeComplexityPremium);
    }

    public function integratedMargin(Week4EconomicInputs $inputs): BigDecimal
    {
        return $this->productSlateValue($inputs)
            ->minus($this->deliveredMarginalCost($inputs))
            ->minus($inputs->batonRougeOpex);
    }

    public function transferPrices(Week4EconomicInputs $inputs): Week4TransferPrices
    {
        $market = $this->realizedWellheadPrice($inputs)
            ->plus($inputs->transportPermianToBatonRouge);

        $marginalCost = $this->deliveredMarginalCost($inputs)
            ->plus($inputs->shortRunCapitalCharge);

        $lazyMidpoint = $market
            ->plus($marginalCost)
            ->dividedBy('2', 6, RoundingMode::Unnecessary);

        return new Week4TransferPrices(
            market: $market,
            marginalCost: $marginalCost,
            lazyMidpoint: $lazyMidpoint,
        );
    }

    public function calculate(Week4EconomicInputs $inputs, BigDecimal|int|string $transferPrice): Week4EconomicResult
    {
        $transferPrice = BigDecimal::of((string) $transferPrice);
        $deliveredMarginalCost = $this->deliveredMarginalCost($inputs);
        $integratedMargin = $this->integratedMargin($inputs);
        $upstreamMargin = $transferPrice->minus($deliveredMarginalCost);
        $refiningMargin = $integratedMargin->minus($upstreamMargin);

        return new Week4EconomicResult(
            transferPrice: $transferPrice,
            deliveredMarginalCost: $deliveredMarginalCost,
            integratedMargin: $integratedMargin,
            upstreamMargin: $upstreamMargin,
            refiningMargin: $refiningMargin,
            upstreamVsTarget: $upstreamMargin->minus($inputs->upstreamTargetMargin),
            refiningVsTarget: $refiningMargin->minus($inputs->refiningTargetMargin),
        );
    }

    /**
     * @return array{market: Week4EconomicResult, marginal_cost: Week4EconomicResult, lazy_midpoint: Week4EconomicResult}
     */
    public function calculateReferenceAnchors(Week4EconomicInputs $inputs): array
    {
        $prices = $this->transferPrices($inputs);

        return [
            'market' => $this->calculate($inputs, $prices->market),
            'marginal_cost' => $this->calculate($inputs, $prices->marginalCost),
            'lazy_midpoint' => $this->calculate($inputs, $prices->lazyMidpoint),
        ];
    }

    public function genevaArbitrage(
        Week4EconomicInputs $inputs,
        BigDecimal|int|string $externalPrice,
        BigDecimal|int|string $internalPrice,
    ): Week4GenevaArbitrageResult {
        $externalPrice = BigDecimal::of((string) $externalPrice);
        $internalPrice = BigDecimal::of((string) $internalPrice);
        $gap = $externalPrice->minus($internalPrice);
        $capturePerBbl = $gap->multipliedBy($inputs->genevaCaptureRate);

        return new Week4GenevaArbitrageResult(
            gap: $gap,
            captureRate: $inputs->genevaCaptureRate,
            capturePerBbl: $capturePerBbl,
            maxVolumeBblDay: $inputs->genevaMaxVolumeBblDay,
            dailyCaptureAtVolumeCap: $capturePerBbl->multipliedBy($inputs->genevaMaxVolumeBblDay),
        );
    }

    public function genevaArbitrageAtMidpoint(Week4EconomicInputs $inputs): Week4GenevaArbitrageResult
    {
        $prices = $this->transferPrices($inputs);

        return $this->genevaArbitrage($inputs, $prices->market, $prices->lazyMidpoint);
    }
}
