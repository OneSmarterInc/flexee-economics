<?php

namespace App\Domain\Economics\Week1;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Week1EconomicEngine
{
    public const ENGINE_IDENTIFIER = 'week1_asset_register';

    public const ENGINE_VERSION = 'week1_asset_register_v1';

    public function calculate(Week1EconomicInputs $inputs): Week1EconomicResult
    {
        $permianRealized = $this->realizedPrice($inputs, 'Permian');
        $permianMargin = $this->upstreamPretaxMargin($inputs, 'Permian');

        $norwayPretax = $this->upstreamPretaxMargin($inputs, 'Norwegian');
        $norwayPosttax = $norwayPretax->multipliedBy($this->afterFiscalShare($inputs, 'Norwegian'));
        $norwayTwoUsdLossPosttax = BigDecimal::of('-2')->multipliedBy($this->afterFiscalShare($inputs, 'Norwegian'));

        $kessanaCompany = $this->upstreamPretaxMargin($inputs, 'Kessana')
            ->multipliedBy($this->afterFiscalShare($inputs, 'Kessana'));

        $batonRougeNet = $this->refineryNet($inputs, 'Baton Rouge');
        $rotterdamContribution = $this->refineryContribution($inputs, 'Rotterdam');
        $rotterdamNet = $this->refineryNet($inputs, 'Rotterdam');
        $rotterdamShutdownCrack = $inputs->rotterdamCost('variable_cost')
            ->minus($inputs->refinery('Rotterdam')['complexity']);
        $rotterdamCurrentCrack = $inputs->refinery('Rotterdam')['crack'];

        $singaporeNet = $this->refineryNet($inputs, 'Singapore');
        $singaporeHaldenShare = $singaporeNet->multipliedBy($inputs->refinery('Singapore')['halden_share']);

        $assetEconomicValues = [
            'Permian' => $permianMargin,
            'Kessana' => $kessanaCompany,
            'Baton Rouge' => $batonRougeNet,
            'Norwegian' => $norwayPosttax,
            'Singapore' => $singaporeHaldenShare,
            'Rotterdam' => $rotterdamNet,
        ];
        $economicRank = $this->rank($assetEconomicValues);
        $topEconomicAsset = explode(' > ', $economicRank)[0];

        $reportedValues = [];
        foreach (array_keys($inputs->bookValues) as $asset) {
            $reportedValues[$asset] = $inputs->bookValue($asset)['reported_profit_musd'];
        }
        $reportedRank = $this->rank($reportedValues);
        $workedExample = $this->workedExample($inputs);
        $orderingAssertions = $this->orderingAssertions(
            $rotterdamContribution,
            $rotterdamNet,
            $rotterdamCurrentCrack,
            $rotterdamShutdownCrack,
            $norwayPosttax,
            $norwayPretax,
            $norwayTwoUsdLossPosttax,
            $economicRank,
            $reportedRank,
            $permianMargin,
            $kessanaCompany,
            $batonRougeNet,
        );

        return new Week1EconomicResult(
            permianRealized: $permianRealized,
            permianMargin: $permianMargin,
            norwayPretax: $norwayPretax,
            norwayPosttax: $norwayPosttax,
            norwayTwoUsdLossPosttax: $norwayTwoUsdLossPosttax,
            kessanaCompany: $kessanaCompany,
            batonRougeNet: $batonRougeNet,
            rotterdamContribution: $rotterdamContribution,
            rotterdamNet: $rotterdamNet,
            rotterdamShutdownCrack: $rotterdamShutdownCrack,
            rotterdamCurrentCrack: $rotterdamCurrentCrack,
            singaporeHaldenShare: $singaporeHaldenShare,
            economicRank: $economicRank,
            reportedRank: $reportedRank,
            topEconomicAsset: $topEconomicAsset,
            assetEconomicValues: $this->formatDecimals($assetEconomicValues, 6),
            reportedProfitValues: $this->formatDecimals($reportedValues, 6),
            workedExample: $this->formatDecimals($workedExample, 6),
            orderingAssertions: $orderingAssertions,
            inputSnapshot: $this->inputSnapshot($inputs),
            outputSnapshot: $this->outputSnapshot(
                $permianRealized,
                $permianMargin,
                $norwayPretax,
                $norwayPosttax,
                $norwayTwoUsdLossPosttax,
                $kessanaCompany,
                $batonRougeNet,
                $rotterdamContribution,
                $rotterdamNet,
                $rotterdamShutdownCrack,
                $rotterdamCurrentCrack,
                $singaporeHaldenShare,
                $economicRank,
                $reportedRank,
                $topEconomicAsset,
                $assetEconomicValues,
                $reportedValues,
                $workedExample,
                $orderingAssertions,
            ),
            status: 'calculated',
            engineIdentifier: self::ENGINE_IDENTIFIER,
            engineVersion: self::ENGINE_VERSION,
            packageVersion: $inputs->packageVersion,
        );
    }

    /**
     * @return array<string, BigDecimal>
     */
    public function workedExample(Week1EconomicInputs $inputs): array
    {
        $xReportedGross = $inputs->workedExampleParameter('x_revenue')->minus($inputs->workedExampleParameter('x_cost'));
        $yContribution = $inputs->workedExampleParameter('y_gross')->minus($inputs->workedExampleParameter('y_variable'));

        return [
            'x_reported_gross' => $xReportedGross,
            'x_posttax' => $xReportedGross->multipliedBy(BigDecimal::one()->minus($inputs->workedExampleParameter('x_tax'))),
            'y_contribution' => $yContribution,
            'y_net' => $yContribution->minus($inputs->workedExampleParameter('y_fixed')),
        ];
    }

    private function realizedPrice(Week1EconomicInputs $inputs, string $asset): BigDecimal
    {
        $row = $inputs->upstreamAsset($asset);

        return $inputs->benchmark((string) $row['benchmark'])->plus($row['differential']);
    }

    private function upstreamPretaxMargin(Week1EconomicInputs $inputs, string $asset): BigDecimal
    {
        $row = $inputs->upstreamAsset($asset);

        return $this->realizedPrice($inputs, $asset)
            ->minus($row['lifting'])
            ->minus($row['logistics']);
    }

    private function afterFiscalShare(Week1EconomicInputs $inputs, string $asset): BigDecimal
    {
        return BigDecimal::one()->minus($inputs->upstreamAsset($asset)['fiscal_rate']);
    }

    private function refineryNet(Week1EconomicInputs $inputs, string $refinery): BigDecimal
    {
        $row = $inputs->refinery($refinery);

        return $row['crack']->plus($row['complexity'])->minus($row['opex']);
    }

    private function refineryContribution(Week1EconomicInputs $inputs, string $refinery): BigDecimal
    {
        $row = $inputs->refinery($refinery);

        return $row['crack']->plus($row['complexity'])->minus($inputs->rotterdamCost('variable_cost'));
    }

    /**
     * @param  array<string, BigDecimal>  $values
     */
    private function rank(array $values): string
    {
        uasort($values, fn (BigDecimal $left, BigDecimal $right): int => $right->compareTo($left));

        return implode(' > ', array_keys($values));
    }

    /**
     * @return array<string, bool>
     */
    private function orderingAssertions(
        BigDecimal $rotterdamContribution,
        BigDecimal $rotterdamNet,
        BigDecimal $rotterdamCurrentCrack,
        BigDecimal $rotterdamShutdownCrack,
        BigDecimal $norwayPosttax,
        BigDecimal $norwayPretax,
        BigDecimal $norwayTwoUsdLossPosttax,
        string $economicRank,
        string $reportedRank,
        BigDecimal $permianMargin,
        BigDecimal $kessanaCompany,
        BigDecimal $batonRougeNet,
    ): array {
        return [
            'rotterdam_loses_money_net_but_covers_variable_cost' => $rotterdamNet->isLessThan(BigDecimal::zero()) && $rotterdamContribution->isGreaterThan(BigDecimal::zero()),
            'rotterdam_current_crack_above_shutdown_crack' => $rotterdamCurrentCrack->isGreaterThan($rotterdamShutdownCrack),
            'norway_posttax_is_twenty_two_pct_of_pretax' => $norwayPosttax->dividedBy($norwayPretax, 18, RoundingMode::HalfUp)->isEqualTo('0.22'),
            'norway_two_usd_loss_costs_forty_four_cents_after_tax' => $norwayTwoUsdLossPosttax->isEqualTo('-0.44'),
            'reported_profit_ranking_differs_from_economic_ranking' => $reportedRank !== $economicRank,
            'ledger_reconciliation_core_values' => $permianMargin->isEqualTo('56.4') && $kessanaCompany->isEqualTo('25.46') && $batonRougeNet->isEqualTo('20.35') && $rotterdamNet->isEqualTo('-0.3'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inputSnapshot(Week1EconomicInputs $inputs): array
    {
        return [
            'reference_package' => [
                'version' => $inputs->packageVersion,
                'source_hashes' => $inputs->sourceHashes,
            ],
            'benchmarks' => $this->formatDecimals($inputs->benchmarks, 6),
            'upstream_assets' => array_map(fn (array $row): array => [
                'benchmark' => $row['benchmark'],
                'differential' => $this->decimalFromPackageValue($row['differential'], 6),
                'lifting' => $this->decimalFromPackageValue($row['lifting'], 6),
                'logistics' => $this->decimalFromPackageValue($row['logistics'], 6),
                'fiscal_type' => $row['fiscal_type'],
                'fiscal_rate' => $this->decimalFromPackageValue($row['fiscal_rate'], 6),
            ], $inputs->upstreamAssets),
            'refining_assets' => array_map(fn (array $row): array => $this->formatDecimals($row, 6), $inputs->refiningAssets),
            'rotterdam_cost_split' => $this->formatDecimals($inputs->rotterdamCostSplit, 6),
            'book_values' => array_map(fn (array $row): array => $this->formatDecimals($row, 6), $inputs->bookValues),
            'worked_example' => $this->formatDecimals($inputs->workedExample, 6),
        ];
    }

    /**
     * @param  array<string, BigDecimal>  $assetEconomicValues
     * @param  array<string, BigDecimal>  $reportedValues
     * @param  array<string, BigDecimal>  $workedExample
     * @param  array<string, bool>  $orderingAssertions
     * @return array<string, mixed>
     */
    private function outputSnapshot(
        BigDecimal $permianRealized,
        BigDecimal $permianMargin,
        BigDecimal $norwayPretax,
        BigDecimal $norwayPosttax,
        BigDecimal $norwayTwoUsdLossPosttax,
        BigDecimal $kessanaCompany,
        BigDecimal $batonRougeNet,
        BigDecimal $rotterdamContribution,
        BigDecimal $rotterdamNet,
        BigDecimal $rotterdamShutdownCrack,
        BigDecimal $rotterdamCurrentCrack,
        BigDecimal $singaporeHaldenShare,
        string $economicRank,
        string $reportedRank,
        string $topEconomicAsset,
        array $assetEconomicValues,
        array $reportedValues,
        array $workedExample,
        array $orderingAssertions,
    ): array {
        return [
            'engine' => [
                'identifier' => self::ENGINE_IDENTIFIER,
                'version' => self::ENGINE_VERSION,
            ],
            'permian_realized' => $this->decimal($permianRealized, 6),
            'permian_margin' => $this->decimal($permianMargin, 6),
            'norway_pretax' => $this->decimal($norwayPretax, 6),
            'norway_posttax' => $this->decimal($norwayPosttax, 6),
            'norway_2usd_loss_posttax' => $this->decimal($norwayTwoUsdLossPosttax, 6),
            'kessana_company' => $this->decimal($kessanaCompany, 6),
            'br_net' => $this->decimal($batonRougeNet, 6),
            'rot_contribution' => $this->decimal($rotterdamContribution, 6),
            'rot_net' => $this->decimal($rotterdamNet, 6),
            'rot_shutdown_crack' => $this->decimal($rotterdamShutdownCrack, 6),
            'rot_current_crack' => $this->decimal($rotterdamCurrentCrack, 6),
            'sing_halden_share' => $this->decimal($singaporeHaldenShare, 6),
            'economic_rank' => $economicRank,
            'reported_rank' => $reportedRank,
            'top_economic_asset' => $topEconomicAsset,
            'asset_economic_values' => $this->formatDecimals($assetEconomicValues, 6),
            'reported_profit_values' => $this->formatDecimals($reportedValues, 6),
            'worked_example' => $this->formatDecimals($workedExample, 6),
            'ordering_assertions' => $orderingAssertions,
        ];
    }

    /**
     * @param  int<0, max>  $scale
     */
    private function decimal(BigDecimal $value, int $scale): string
    {
        return (string) $value->toScale($scale, RoundingMode::HalfUp);
    }

    /**
     * @param  int<0, max>  $scale
     */
    private function decimalFromPackageValue(mixed $value, int $scale): string
    {
        if (! $value instanceof BigDecimal) {
            throw new \InvalidArgumentException('Week 1 package value expected a decimal numeric cell.');
        }

        return $this->decimal($value, $scale);
    }

    /**
     * @param  array<string, BigDecimal>  $values
     * @param  int<0, max>  $scale
     * @return array<string, string>
     */
    private function formatDecimals(array $values, int $scale): array
    {
        return array_map(
            fn (BigDecimal $value): string => $this->decimal($value, $scale),
            $values,
        );
    }
}
