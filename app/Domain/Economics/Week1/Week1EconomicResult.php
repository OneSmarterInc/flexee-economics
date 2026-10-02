<?php

namespace App\Domain\Economics\Week1;

use Brick\Math\BigDecimal;

final readonly class Week1EconomicResult
{
    /**
     * @param  array<string, string>  $assetEconomicValues
     * @param  array<string, string>  $reportedProfitValues
     * @param  array<string, string>  $workedExample
     * @param  array<string, bool>  $orderingAssertions
     * @param  array<string, mixed>  $inputSnapshot
     * @param  array<string, mixed>  $outputSnapshot
     */
    public function __construct(
        public BigDecimal $permianRealized,
        public BigDecimal $permianMargin,
        public BigDecimal $norwayPretax,
        public BigDecimal $norwayPosttax,
        public BigDecimal $norwayTwoUsdLossPosttax,
        public BigDecimal $kessanaCompany,
        public BigDecimal $batonRougeNet,
        public BigDecimal $rotterdamContribution,
        public BigDecimal $rotterdamNet,
        public BigDecimal $rotterdamShutdownCrack,
        public BigDecimal $rotterdamCurrentCrack,
        public BigDecimal $singaporeHaldenShare,
        public string $economicRank,
        public string $reportedRank,
        public string $topEconomicAsset,
        public array $assetEconomicValues,
        public array $reportedProfitValues,
        public array $workedExample,
        public array $orderingAssertions,
        public array $inputSnapshot,
        public array $outputSnapshot,
        public string $status,
        public string $engineIdentifier,
        public string $engineVersion,
        public string $packageVersion,
    ) {}
}
