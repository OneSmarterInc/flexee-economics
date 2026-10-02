<?php

namespace App\Domain\Economics\Week12;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Week12EconomicEngine
{
    public const ENGINE_IDENTIFIER = 'week12_transition_portfolio';

    public const ENGINE_VERSION = 'week12_transition_portfolio_v1';

    public function calculate(Week12EconomicInputs $inputs): Week12EconomicResult
    {
        $discretionary = $this->discretionaryEnvelope($inputs);
        $projectScenarioNpvs = $this->projectScenarioNpvs($inputs);
        $projectNpvRanges = $this->projectNpvRanges($projectScenarioNpvs);
        $portfolioResults = $this->portfolioResults($inputs, $discretionary);
        $feasiblePortfolios = array_values(array_filter(
            $portfolioResults,
            fn (Week12PortfolioResult $portfolioResult): bool => $portfolioResult->feasible,
        ));
        $helixProject = $inputs->project('helix_rotterdam');
        $offshoreWindProject = $inputs->project('offshore_wind');
        $divestmentProceeds = $this->totalDivestmentProceeds($inputs);
        $helixPlusWind = $helixProject['cost_musd']->plus($offshoreWindProject['cost_musd']);
        $workedExample = $this->workedExample($inputs);

        return new Week12EconomicResult(
            discretionaryEnvelopeMusd: $discretionary,
            envelopeWithDivestmentMusd: $discretionary->plus($divestmentProceeds),
            helixRotterdamCostMusd: $helixProject['cost_musd'],
            adjacentCeilingMusd: $inputs->bucket('adjacent')['ceiling'],
            hrPlusWindCostMusd: $helixPlusWind,
            hrPlusWindNeedsDivestment: $this->hrPlusWindNeedsDivestment($inputs, $discretionary, $helixPlusWind),
            feasiblePortfolioCount: count($feasiblePortfolios),
            feasibleWithHelixRotterdamCount: count(array_filter(
                $feasiblePortfolios,
                fn (Week12PortfolioResult $portfolioResult): bool => in_array('helix_rotterdam', $portfolioResult->projectKeys, true),
            )),
            portfoliosUnlockedByDivestmentCount: count(array_filter(
                $feasiblePortfolios,
                fn (Week12PortfolioResult $portfolioResult): bool => $portfolioResult->unlockedByDivestment,
            )),
            projectScenarioNpvs: $projectScenarioNpvs,
            projectNpvRanges: $projectNpvRanges,
            workedExample: $workedExample,
            portfolioResults: $portfolioResults,
            inputSnapshot: $this->inputSnapshot($inputs),
            outputSnapshot: $this->outputSnapshot($discretionary, $discretionary->plus($divestmentProceeds), $helixProject['cost_musd'], $inputs->bucket('adjacent')['ceiling'], $helixPlusWind, $projectNpvRanges, $workedExample, $portfolioResults),
            status: 'calculated',
            engineIdentifier: self::ENGINE_IDENTIFIER,
            engineVersion: self::ENGINE_VERSION,
            packageVersion: $inputs->packageVersion,
        );
    }

    /**
     * @return array<string, BigDecimal>
     */
    public function workedExample(Week12EconomicInputs $inputs): array
    {
        $carbonLow = $inputs->workedExampleParameter('carbon_low');
        $carbonHigh = $inputs->workedExampleParameter('carbon_high');

        return [
            'p1_low' => $this->carbonOnlyNpv(
                $inputs->workedExampleParameter('p1_base'),
                $inputs->workedExampleParameter('p1_carbon_sens'),
                $carbonLow,
            ),
            'p1_high' => $this->carbonOnlyNpv(
                $inputs->workedExampleParameter('p1_base'),
                $inputs->workedExampleParameter('p1_carbon_sens'),
                $carbonHigh,
            ),
            'p2_low' => $this->carbonOnlyNpv(
                $inputs->workedExampleParameter('p2_base'),
                $inputs->workedExampleParameter('p2_carbon_sens'),
                $carbonLow,
            ),
            'p2_high' => $this->carbonOnlyNpv(
                $inputs->workedExampleParameter('p2_base'),
                $inputs->workedExampleParameter('p2_carbon_sens'),
                $carbonHigh,
            ),
        ];
    }

    private function carbonOnlyNpv(BigDecimal $base, BigDecimal $carbonSensitivity, BigDecimal $carbonPrice): BigDecimal
    {
        return $base->plus(
            $carbonSensitivity->multipliedBy($carbonPrice->minus('40')),
        );
    }

    private function discretionaryEnvelope(Week12EconomicInputs $inputs): BigDecimal
    {
        return $inputs->envelopeParameter('total_envelope')
            ->minus($inputs->envelopeParameter('sustaining_floor'));
    }

    /**
     * @return array<string, array<string, BigDecimal>>
     */
    private function projectScenarioNpvs(Week12EconomicInputs $inputs): array
    {
        $npvs = [];

        foreach ($inputs->projects as $projectKey => $project) {
            foreach ($inputs->carbonScenarios as $carbonScenario => $carbonPrice) {
                foreach ($inputs->demandScenarios as $demandScenario => $demandCode) {
                    $scenarioKey = $carbonScenario.'_'.$demandScenario;
                    $npvs[$projectKey][$scenarioKey] = $project['npv_base']
                        ->plus($project['carbon_sens']->multipliedBy($carbonPrice->minus('40')))
                        ->plus($project['demand_sens']->multipliedBy($demandCode));
                }
            }
        }

        return $npvs;
    }

    /**
     * @param  array<string, array<string, BigDecimal>>  $projectScenarioNpvs
     * @return array<string, array{min: BigDecimal, max: BigDecimal}>
     */
    private function projectNpvRanges(array $projectScenarioNpvs): array
    {
        $ranges = [];

        foreach ($projectScenarioNpvs as $projectKey => $scenarioNpvs) {
            $ranges[$projectKey] = [
                'min' => $this->minimum($scenarioNpvs),
                'max' => $this->maximum($scenarioNpvs),
            ];
        }

        return $ranges;
    }

    /**
     * @return list<Week12PortfolioResult>
     */
    private function portfolioResults(Week12EconomicInputs $inputs, BigDecimal $discretionary): array
    {
        $projectKeys = array_keys($inputs->projects);
        $portfolios = [];

        foreach ($this->nonEmptySubsets($projectKeys) as $portfolioProjectKeys) {
            $portfolios[] = $this->portfolioResult($inputs, $discretionary, $portfolioProjectKeys);
        }

        return $portfolios;
    }

    /**
     * @param  list<string>  $projectKeys
     */
    private function portfolioResult(Week12EconomicInputs $inputs, BigDecimal $discretionary, array $projectKeys): Week12PortfolioResult
    {
        $capitalRequired = BigDecimal::zero();
        $divestmentProceeds = BigDecimal::zero();
        $bucketSpend = [];

        foreach ($projectKeys as $projectKey) {
            $project = $inputs->project($projectKey);
            $cost = $project['cost_musd'];

            if ($cost->isGreaterThanOrEqualTo(BigDecimal::zero())) {
                $capitalRequired = $capitalRequired->plus($cost);
                $bucketSpend[$project['bucket']] = ($bucketSpend[$project['bucket']] ?? BigDecimal::zero())->plus($cost);
            } else {
                $divestmentProceeds = $divestmentProceeds->plus($cost->negated());
            }
        }

        $availableEnvelope = $discretionary->plus($divestmentProceeds);
        $constraintFailures = $this->constraintFailures($inputs, $capitalRequired, $availableEnvelope, $bucketSpend);
        $feasible = $constraintFailures === [];
        $unlockedByDivestment = $feasible
            && $divestmentProceeds->isGreaterThan(BigDecimal::zero())
            && ! $this->portfolioResultWithoutDivestment($inputs, $discretionary, $projectKeys)->feasible;

        return new Week12PortfolioResult(
            projectKeys: $projectKeys,
            capitalRequiredMusd: $capitalRequired,
            divestmentProceedsMusd: $divestmentProceeds,
            availableEnvelopeMusd: $availableEnvelope,
            bucketSpend: $bucketSpend,
            feasible: $feasible,
            unlockedByDivestment: $unlockedByDivestment,
            constraintFailures: $constraintFailures,
        );
    }

    /**
     * @param  list<string>  $projectKeys
     */
    private function portfolioResultWithoutDivestment(Week12EconomicInputs $inputs, BigDecimal $discretionary, array $projectKeys): Week12PortfolioResult
    {
        $positiveProjectKeys = array_values(array_filter(
            $projectKeys,
            fn (string $projectKey): bool => $inputs->project($projectKey)['cost_musd']->isGreaterThanOrEqualTo(BigDecimal::zero()),
        ));

        if ($positiveProjectKeys === $projectKeys) {
            return new Week12PortfolioResult(
                projectKeys: $projectKeys,
                capitalRequiredMusd: BigDecimal::zero(),
                divestmentProceedsMusd: BigDecimal::zero(),
                availableEnvelopeMusd: $discretionary,
                bucketSpend: [],
                feasible: true,
                unlockedByDivestment: false,
                constraintFailures: [],
            );
        }

        return $this->portfolioResult($inputs, $discretionary, $positiveProjectKeys);
    }

    /**
     * @param  array<string, BigDecimal>  $bucketSpend
     * @return list<string>
     */
    private function constraintFailures(
        Week12EconomicInputs $inputs,
        BigDecimal $capitalRequired,
        BigDecimal $availableEnvelope,
        array $bucketSpend,
    ): array {
        $failures = [];

        if ($capitalRequired->isGreaterThan($availableEnvelope)) {
            $failures[] = 'capital_envelope_exceeded';
        }

        foreach ($bucketSpend as $bucket => $spend) {
            if ($spend->isGreaterThan($inputs->bucket($bucket)['ceiling'])) {
                $failures[] = 'bucket_ceiling_exceeded:'.$bucket;
            }
        }

        return $failures;
    }

    /**
     * @param  list<string>  $items
     * @return list<list<string>>
     */
    private function nonEmptySubsets(array $items): array
    {
        $subsets = [];
        $count = count($items);
        $limit = 1 << $count;

        for ($mask = 1; $mask < $limit; $mask++) {
            $subset = [];

            for ($index = 0; $index < $count; $index++) {
                if (($mask & (1 << $index)) !== 0) {
                    $subset[] = $items[$index];
                }
            }

            $subsets[] = $subset;
        }

        return $subsets;
    }

    private function totalDivestmentProceeds(Week12EconomicInputs $inputs): BigDecimal
    {
        $proceeds = BigDecimal::zero();

        foreach ($inputs->projects as $project) {
            if ($project['cost_musd']->isLessThan(BigDecimal::zero())) {
                $proceeds = $proceeds->plus($project['cost_musd']->negated());
            }
        }

        return $proceeds;
    }

    private function hrPlusWindNeedsDivestment(Week12EconomicInputs $inputs, BigDecimal $discretionary, BigDecimal $helixPlusWind): bool
    {
        $withoutDivestment = $helixPlusWind->isGreaterThan($discretionary);
        $withDivestment = $helixPlusWind->isLessThanOrEqualTo($discretionary->plus($this->totalDivestmentProceeds($inputs)));
        $bucketOk = $inputs->project('helix_rotterdam')['cost_musd']->isLessThanOrEqualTo($inputs->bucket('adjacent')['ceiling'])
            && $inputs->project('offshore_wind')['cost_musd']->isLessThanOrEqualTo($inputs->bucket('renewable')['ceiling']);

        return $withoutDivestment && $withDivestment && $bucketOk;
    }

    /**
     * @param  array<string, BigDecimal>  $values
     */
    private function minimum(array $values): BigDecimal
    {
        $minimum = null;

        foreach ($values as $value) {
            if (! $minimum instanceof BigDecimal || $value->isLessThan($minimum)) {
                $minimum = $value;
            }
        }

        return $minimum ?? BigDecimal::zero();
    }

    /**
     * @param  array<string, BigDecimal>  $values
     */
    private function maximum(array $values): BigDecimal
    {
        $maximum = null;

        foreach ($values as $value) {
            if (! $maximum instanceof BigDecimal || $value->isGreaterThan($maximum)) {
                $maximum = $value;
            }
        }

        return $maximum ?? BigDecimal::zero();
    }

    /**
     * @return array<string, mixed>
     */
    private function inputSnapshot(Week12EconomicInputs $inputs): array
    {
        return [
            'reference_package' => [
                'version' => $inputs->packageVersion,
                'source_hashes' => $inputs->sourceHashes,
            ],
            'envelope' => $this->formatDecimals($inputs->envelope, 6),
            'buckets' => array_map(
                fn (array $bucket): array => $this->formatDecimals($bucket, 6),
                $inputs->buckets,
            ),
            'projects' => array_map(fn (array $project): array => [
                'bucket' => $project['bucket'],
                'cost_musd' => (string) $project['cost_musd']->toScale(6, RoundingMode::HalfUp),
                'npv_base' => (string) $project['npv_base']->toScale(6, RoundingMode::HalfUp),
                'carbon_sens' => (string) $project['carbon_sens']->toScale(6, RoundingMode::HalfUp),
                'demand_sens' => (string) $project['demand_sens']->toScale(6, RoundingMode::HalfUp),
            ], $inputs->projects),
            'carbon_scenarios' => $this->formatDecimals($inputs->carbonScenarios, 6),
            'demand_scenarios' => $this->formatDecimals($inputs->demandScenarios, 6),
            'worked_example' => $this->formatDecimals($inputs->workedExample, 6),
        ];
    }

    /**
     * @param  array<string, array{min: BigDecimal, max: BigDecimal}>  $projectNpvRanges
     * @param  array<string, BigDecimal>  $workedExample
     * @param  list<Week12PortfolioResult>  $portfolioResults
     * @return array<string, mixed>
     */
    private function outputSnapshot(
        BigDecimal $discretionary,
        BigDecimal $envelopeWithDivestment,
        BigDecimal $helixCost,
        BigDecimal $adjacentCeiling,
        BigDecimal $helixPlusWind,
        array $projectNpvRanges,
        array $workedExample,
        array $portfolioResults,
    ): array {
        $feasible = array_values(array_filter(
            $portfolioResults,
            fn (Week12PortfolioResult $portfolioResult): bool => $portfolioResult->feasible,
        ));

        return [
            'engine' => [
                'identifier' => self::ENGINE_IDENTIFIER,
                'version' => self::ENGINE_VERSION,
            ],
            'discretionary' => (string) $discretionary->toScale(6, RoundingMode::HalfUp),
            'envelope_with_divestment' => (string) $envelopeWithDivestment->toScale(6, RoundingMode::HalfUp),
            'helix_rotterdam_cost' => (string) $helixCost->toScale(6, RoundingMode::HalfUp),
            'adjacent_ceiling' => (string) $adjacentCeiling->toScale(6, RoundingMode::HalfUp),
            'hr_plus_wind_cost' => (string) $helixPlusWind->toScale(6, RoundingMode::HalfUp),
            'feasible_portfolios' => count($feasible),
            'feasible_with_helix_rotterdam' => count(array_filter(
                $feasible,
                fn (Week12PortfolioResult $portfolioResult): bool => in_array('helix_rotterdam', $portfolioResult->projectKeys, true),
            )),
            'portfolios_unlocked_by_divest' => count(array_filter(
                $feasible,
                fn (Week12PortfolioResult $portfolioResult): bool => $portfolioResult->unlockedByDivestment,
            )),
            'project_npv_ranges' => array_map(fn (array $range): array => [
                'min' => (string) $range['min']->toScale(6, RoundingMode::HalfUp),
                'max' => (string) $range['max']->toScale(6, RoundingMode::HalfUp),
            ], $projectNpvRanges),
            'worked_example' => $this->formatDecimals($workedExample, 6),
            'portfolio_results' => array_map(
                fn (Week12PortfolioResult $portfolioResult): array => $portfolioResult->snapshot(),
                $portfolioResults,
            ),
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
