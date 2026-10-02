<?php

namespace App\Domain\Economics\Week9;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final class Week9EconomicEngine
{
    public const ENGINE_IDENTIFIER = 'week9_cordell_rebrand';

    public const ENGINE_VERSION = 'week9_cordell_rebrand_v1';

    private const BASE_STATE = 'base';

    private const PRICE_WAR_STATE = 'price_war';

    /**
     * @param  list<string>|null  $partialRebrandMarkets
     */
    public function calculate(
        Week9EconomicInputs $inputs,
        string $nonfuelStateKey = self::BASE_STATE,
        ?array $partialRebrandMarkets = null,
    ): Week9EconomicResult {
        $stateMargin = $inputs->nonfuelState($nonfuelStateKey);
        $baseMargin = $inputs->nonfuelState(self::BASE_STATE);
        $costPerSite = $this->costPerSite($inputs);
        $partialRebrandMarkets ??= $this->defaultPartialRebrandMarkets($inputs);
        $marketResults = $this->marketResults($inputs, $stateMargin, $baseMargin, $costPerSite);
        $partialGain = $this->sumMarketValues($marketResults, $partialRebrandMarkets, 'state_adjusted_gain_musd');
        $partialCost = $this->sumMarketValues($marketResults, $partialRebrandMarkets, 'cost_musd');
        $fullGain = $this->sumMarketValues($marketResults, array_keys($marketResults), 'state_adjusted_gain_musd');
        $priceWarResults = $this->marketResults(
            $inputs,
            $inputs->nonfuelState(self::PRICE_WAR_STATE),
            $baseMargin,
            $costPerSite,
        );
        $priceWarPartialGain = $this->sumMarketValues($priceWarResults, $partialRebrandMarkets, 'state_adjusted_gain_musd');

        return new Week9EconomicResult(
            marketResults: $marketResults,
            partialRebrandMarkets: $partialRebrandMarkets,
            nonfuelStateKey: $nonfuelStateKey,
            costPerSite: $costPerSite,
            partialGainMusd: $partialGain,
            partialCostMusd: $partialCost,
            partialPaybackYears: $this->payback($partialCost, $partialGain),
            fullNetGainMusd: $fullGain,
            priceWarPartialPaybackYears: $this->payback($partialCost, $priceWarPartialGain),
            inputSnapshot: $this->inputSnapshot($inputs, $nonfuelStateKey, $partialRebrandMarkets),
            outputSnapshot: $this->outputSnapshot($marketResults, $partialRebrandMarkets, $partialGain, $partialCost, $fullGain, $priceWarPartialGain, $costPerSite),
            engineIdentifier: self::ENGINE_IDENTIFIER,
            engineVersion: self::ENGINE_VERSION,
        );
    }

    /**
     * @return array{market_a_gain_musd: BigDecimal, market_b_gain_musd: BigDecimal, market_b_cost_musd: BigDecimal, market_b_payback_years: BigDecimal}
     */
    public function workedExample(Week9EconomicInputs $inputs): array
    {
        $fills = $inputs->workedExampleParameter('fills_per_site_year');
        $sites = $inputs->workedExampleParameter('sites_each');
        $costPerSite = $inputs->workedExampleParameter('cost_per_site');
        $marketBGain = $this->annualGainMusd($inputs->workedExampleParameter('b_net_per_fill'), $sites, $fills);
        $marketBCost = $costPerSite->multipliedBy($sites)->dividedBy('1000000', 12, RoundingMode::HalfUp);

        return [
            'market_a_gain_musd' => $this->annualGainMusd($inputs->workedExampleParameter('a_net_per_fill'), $sites, $fills),
            'market_b_gain_musd' => $marketBGain,
            'market_b_cost_musd' => $marketBCost,
            'market_b_payback_years' => $this->payback($marketBCost, $marketBGain),
        ];
    }

    private function costPerSite(Week9EconomicInputs $inputs): BigDecimal
    {
        return $inputs->rebrandParameter('total_cost_musd')
            ->multipliedBy('1000000')
            ->dividedBy($inputs->rebrandParameter('total_sites'), 12, RoundingMode::HalfUp);
    }

    /**
     * @return list<string>
     */
    private function defaultPartialRebrandMarkets(Week9EconomicInputs $inputs): array
    {
        $markets = [];

        foreach ($inputs->markets as $market) {
            if ($market->netPerFill()->isGreaterThan(BigDecimal::zero())) {
                $markets[] = $market->key;
            }
        }

        return $markets;
    }

    /**
     * @return array<string, Week9MarketResult>
     */
    private function marketResults(
        Week9EconomicInputs $inputs,
        BigDecimal $stateMargin,
        BigDecimal $baseMargin,
        BigDecimal $costPerSite,
    ): array {
        $results = [];
        $stateMultiplier = $stateMargin->dividedBy($baseMargin, 12, RoundingMode::HalfUp);

        foreach ($inputs->markets as $market) {
            $baseGain = $this->annualGainMusd(
                $market->netPerFill(),
                $market->sites,
                $inputs->rebrandParameter('fills_per_site_year'),
            );
            $stateAdjustedGain = $baseGain->multipliedBy($stateMultiplier);
            $cost = $costPerSite->multipliedBy($market->sites)->dividedBy('1000000', 12, RoundingMode::HalfUp);

            $results[$market->key] = new Week9MarketResult(
                marketKey: $market->key,
                equity: $market->equity,
                sites: $market->sites,
                netPerFill: $market->netPerFill(),
                baseGainMusd: $baseGain,
                stateAdjustedGainMusd: $stateAdjustedGain,
                costMusd: $cost,
                paybackYears: $stateAdjustedGain->isGreaterThan(BigDecimal::zero())
                    ? $this->payback($cost, $stateAdjustedGain)
                    : null,
            );
        }

        return $results;
    }

    private function annualGainMusd(BigDecimal $netPerFill, BigDecimal $sites, BigDecimal $fillsPerSiteYear): BigDecimal
    {
        return $netPerFill
            ->multipliedBy($sites)
            ->multipliedBy($fillsPerSiteYear)
            ->dividedBy('1000000', 12, RoundingMode::HalfUp);
    }

    /**
     * @param  array<string, Week9MarketResult>  $marketResults
     * @param  list<string>  $markets
     */
    private function sumMarketValues(array $marketResults, array $markets, string $field): BigDecimal
    {
        $sum = BigDecimal::zero();

        foreach ($markets as $marketKey) {
            $result = $marketResults[$marketKey]
                ?? throw new InvalidArgumentException("Week 9 market result [{$marketKey}] is not available.");

            $sum = $sum->plus(match ($field) {
                'state_adjusted_gain_musd' => $result->stateAdjustedGainMusd,
                'cost_musd' => $result->costMusd,
                default => throw new InvalidArgumentException("Unsupported Week 9 market result field [{$field}]."),
            });
        }

        return $sum;
    }

    private function payback(BigDecimal $costMusd, BigDecimal $gainMusd): BigDecimal
    {
        if (! $gainMusd->isGreaterThan(BigDecimal::zero())) {
            throw new InvalidArgumentException('Week 9 payback requires a positive annual gain.');
        }

        return $costMusd->dividedBy($gainMusd, 12, RoundingMode::HalfUp);
    }

    /**
     * @param  list<string>  $partialRebrandMarkets
     * @return array<string, mixed>
     */
    private function inputSnapshot(Week9EconomicInputs $inputs, string $nonfuelStateKey, array $partialRebrandMarkets): array
    {
        return [
            'reference_package' => [
                'version' => $inputs->packageVersion,
                'source_hashes' => $inputs->sourceHashes,
            ],
            'markets' => array_map(fn (Week9Market $market): array => $market->snapshot(), $inputs->markets),
            'nonfuel_states' => $this->formatDecimals($inputs->nonfuelStates, 6),
            'rebrand_parameters' => $this->formatDecimals($inputs->rebrandParameters, 6),
            'selected_nonfuel_state' => $nonfuelStateKey,
            'partial_rebrand_markets' => $partialRebrandMarkets,
        ];
    }

    /**
     * @param  array<string, Week9MarketResult>  $marketResults
     * @param  list<string>  $partialRebrandMarkets
     * @return array<string, mixed>
     */
    private function outputSnapshot(
        array $marketResults,
        array $partialRebrandMarkets,
        BigDecimal $partialGain,
        BigDecimal $partialCost,
        BigDecimal $fullGain,
        BigDecimal $priceWarPartialGain,
        BigDecimal $costPerSite,
    ): array {
        return [
            'engine' => [
                'identifier' => self::ENGINE_IDENTIFIER,
                'version' => self::ENGINE_VERSION,
            ],
            'cost_per_site' => (string) $costPerSite->toScale(6, RoundingMode::HalfUp),
            'market_results' => array_map(fn (Week9MarketResult $result): array => $result->snapshot(), $marketResults),
            'partial_rebrand_markets' => $partialRebrandMarkets,
            'partial_gain_musd' => (string) $partialGain->toScale(6, RoundingMode::HalfUp),
            'partial_cost_musd' => (string) $partialCost->toScale(6, RoundingMode::HalfUp),
            'partial_payback_years' => (string) $this->payback($partialCost, $partialGain)->toScale(6, RoundingMode::HalfUp),
            'full_net_gain_musd' => (string) $fullGain->toScale(6, RoundingMode::HalfUp),
            'pricewar_partial_gain_musd' => (string) $priceWarPartialGain->toScale(6, RoundingMode::HalfUp),
            'pricewar_partial_payback_years' => (string) $this->payback($partialCost, $priceWarPartialGain)->toScale(6, RoundingMode::HalfUp),
        ];
    }

    /**
     * @param  array<string, BigDecimal>  $values
     * @param  int<0, max>  $scale
     * @return array<string, string>
     */
    private function formatDecimals(array $values, int $scale): array
    {
        return array_map(
            fn (BigDecimal $value): string => (string) $value->toScale($scale, RoundingMode::HalfUp),
            $values,
        );
    }
}
