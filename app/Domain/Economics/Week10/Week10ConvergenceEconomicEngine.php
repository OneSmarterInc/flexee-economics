<?php

namespace App\Domain\Economics\Week10;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Week10ConvergenceEconomicEngine
{
    public const ENGINE_IDENTIFIER = 'week10_convergence_recession';

    public const ENGINE_VERSION = 'week10_convergence_recession_v1';

    private const PRODUCT_ORDER = ['gasoline', 'diesel', 'jet'];

    /**
     * Package-backed qualitative standing states that bind Singapore flexibility.
     *
     * @var list<string>
     */
    private const BINDING_STRAITS_PACIFIC_STATES = ['strained', 'hostile'];

    public function calculate(Week10ReferenceInputs $inputs, Week10InheritedState $state): Week10EconomicResult
    {
        $demandImpacts = $this->demandImpacts($inputs);
        $blendedDemandHit = $this->blendedDemandHit($demandImpacts);
        $refineryImpacts = $this->refineryImpacts($inputs, $demandImpacts);
        $hardestHitRefinery = $this->hardestHitRefinery($refineryImpacts);
        $unresolved = $state->unresolvedDependencyKeys();
        $bindingConstraints = $unresolved === [] ? $this->bindingConstraints($inputs, $state) : [];
        $bindingCount = count(array_filter(
            $bindingConstraints,
            fn (Week10BindingConstraintResult $constraint): bool => $constraint->binding,
        ));
        $status = $unresolved === []
            ? Week10EconomicResult::STATUS_CALCULATED
            : Week10EconomicResult::STATUS_UNRESOLVED_DEPENDENCY;

        return new Week10EconomicResult(
            status: $status,
            demandImpacts: $demandImpacts,
            blendedDemandHit: $blendedDemandHit,
            refineryImpacts: $refineryImpacts,
            hardestHitRefinery: $hardestHitRefinery,
            bindingConstraints: $bindingConstraints,
            bindingCount: $bindingCount,
            unresolvedDependencies: $unresolved,
            inputSnapshot: $this->inputSnapshot($inputs, $state),
            outputSnapshot: $this->outputSnapshot($status, $demandImpacts, $blendedDemandHit, $refineryImpacts, $hardestHitRefinery, $bindingConstraints, $bindingCount, $unresolved),
            engineIdentifier: self::ENGINE_IDENTIFIER,
            engineVersion: self::ENGINE_VERSION,
            packageVersion: $inputs->packageVersion,
        );
    }

    /**
     * @return array{br_hit: BigDecimal, disciplined_binding_count: int}
     */
    public function workedExample(Week10ReferenceInputs $inputs): array
    {
        $result = $this->calculate($inputs, Week10InheritedState::fromReferenceFixture($inputs, 'reference_disciplined'));

        return [
            'br_hit' => $result->refineryHit('Baton Rouge'),
            'disciplined_binding_count' => $result->bindingCount,
        ];
    }

    /**
     * @return array<string, Week10DemandImpact>
     */
    private function demandImpacts(Week10ReferenceInputs $inputs): array
    {
        $impacts = [];
        $gdpChange = $inputs->recessionParameter('gdp_change');

        foreach (self::PRODUCT_ORDER as $product) {
            $impacts[$product] = new Week10DemandImpact(
                product: $product,
                incomeElasticity: $inputs->productElasticity($product),
                demandShare: $inputs->demandShare($product),
                demandHit: $gdpChange->multipliedBy($inputs->productElasticity($product)),
            );
        }

        return $impacts;
    }

    /**
     * @param  array<string, Week10DemandImpact>  $demandImpacts
     */
    private function blendedDemandHit(array $demandImpacts): BigDecimal
    {
        $sum = BigDecimal::zero();

        foreach ($demandImpacts as $impact) {
            $sum = $sum->plus($impact->demandHit->multipliedBy($impact->demandShare));
        }

        return $sum;
    }

    /**
     * @param  array<string, Week10DemandImpact>  $demandImpacts
     * @return array<string, Week10RefineryImpact>
     */
    private function refineryImpacts(Week10ReferenceInputs $inputs, array $demandImpacts): array
    {
        $impacts = [];

        foreach (array_keys($inputs->refineryYields) as $refinery) {
            $contributions = [];
            $hit = BigDecimal::zero();

            foreach (self::PRODUCT_ORDER as $product) {
                $contribution = $inputs->refineryYield($refinery, $product)->multipliedBy($demandImpacts[$product]->demandHit);
                $contributions[$product] = $contribution;
                $hit = $hit->plus($contribution);
            }

            $impacts[$refinery] = new Week10RefineryImpact(
                refinery: $refinery,
                demandHit: $hit,
                productContributions: $contributions,
            );
        }

        return $impacts;
    }

    /**
     * @param  array<string, Week10RefineryImpact>  $refineryImpacts
     */
    private function hardestHitRefinery(array $refineryImpacts): string
    {
        $hardest = null;

        foreach ($refineryImpacts as $impact) {
            if ($hardest === null || $impact->demandHit->isLessThan($hardest->demandHit)) {
                $hardest = $impact;
            }
        }

        return $hardest->refinery;
    }

    /**
     * @return array<string, Week10BindingConstraintResult>
     */
    private function bindingConstraints(Week10ReferenceInputs $inputs, Week10InheritedState $state): array
    {
        $minCapex = $inputs->bindingRule('min_cancellable_capex_musd');
        $minHedge = $inputs->bindingRule('min_crude_hedge_coverage');
        $minCash = $inputs->bindingRule('min_cash_cushion_musd');
        $standing = strtolower($state->requireStraitsPacificStanding());

        return [
            'capex' => new Week10BindingConstraintResult(
                key: 'capex',
                label: 'Cancellable capex flexibility',
                binding: $state->requireCancellableCapex()->isLessThan($minCapex),
                sourceDependencyKey: 'cancellable_capex_musd',
                sourceValue: (string) $state->requireCancellableCapex(),
                rule: 'cancellable_capex_musd < '.$minCapex,
            ),
            'hedge' => new Week10BindingConstraintResult(
                key: 'hedge',
                label: 'Crude hedge coverage',
                binding: $state->requireCrudeHedgeCoverage()->isLessThan($minHedge),
                sourceDependencyKey: 'crude_hedge_coverage',
                sourceValue: (string) $state->requireCrudeHedgeCoverage(),
                rule: 'crude_hedge_coverage < '.$minHedge,
            ),
            'delacroix_cover' => new Week10BindingConstraintResult(
                key: 'delacroix_cover',
                label: 'Baton Rouge reported-margin cover',
                binding: $state->requireBatonRougeReportedMarginStrong(),
                sourceDependencyKey: 'br_reported_margin_strong',
                sourceValue: $state->requireBatonRougeReportedMarginStrong() ? 'true' : 'false',
                rule: 'br_reported_margin_strong = true',
            ),
            'straits_pacific' => new Week10BindingConstraintResult(
                key: 'straits_pacific',
                label: 'Straits Pacific operating flexibility',
                binding: in_array($standing, self::BINDING_STRAITS_PACIFIC_STATES, true),
                sourceDependencyKey: 'straits_pacific_standing',
                sourceValue: $state->requireStraitsPacificStanding(),
                rule: 'straits_pacific_standing in strained,hostile',
            ),
            'cash' => new Week10BindingConstraintResult(
                key: 'cash',
                label: 'Cash cushion',
                binding: $state->requireCashCushion()->isLessThan($minCash),
                sourceDependencyKey: 'cash_cushion_musd',
                sourceValue: (string) $state->requireCashCushion(),
                rule: 'cash_cushion_musd < '.$minCash,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inputSnapshot(Week10ReferenceInputs $inputs, Week10InheritedState $state): array
    {
        return [
            'reference_package' => [
                'version' => $inputs->packageVersion,
                'source_hashes' => $inputs->sourceHashes,
            ],
            'recession_parameters' => $this->formatDecimals($inputs->recessionParameters, 6),
            'product_elasticities' => array_map(
                fn (array $values): array => $this->formatDecimals($values, 6),
                $inputs->productElasticities,
            ),
            'refinery_yields' => array_map(
                fn (array $values): array => $this->formatDecimals($values, 6),
                $inputs->refineryYields,
            ),
            'binding_rules' => $this->formatDecimals($inputs->bindingRules, 6),
            'inherited_state' => $state->snapshot(),
        ];
    }

    /**
     * @param  array<string, Week10DemandImpact>  $demandImpacts
     * @param  array<string, Week10RefineryImpact>  $refineryImpacts
     * @param  array<string, Week10BindingConstraintResult>  $bindingConstraints
     * @param  list<string>  $unresolved
     * @return array<string, mixed>
     */
    private function outputSnapshot(
        string $status,
        array $demandImpacts,
        BigDecimal $blendedDemandHit,
        array $refineryImpacts,
        string $hardestHitRefinery,
        array $bindingConstraints,
        int $bindingCount,
        array $unresolved,
    ): array {
        return [
            'engine' => [
                'identifier' => self::ENGINE_IDENTIFIER,
                'version' => self::ENGINE_VERSION,
            ],
            'status' => $status,
            'demand_impacts' => array_map(fn (Week10DemandImpact $impact): array => $impact->snapshot(), $demandImpacts),
            'blended_demand_hit' => (string) $blendedDemandHit->toScale(6, RoundingMode::HalfUp),
            'refinery_impacts' => array_map(fn (Week10RefineryImpact $impact): array => $impact->snapshot(), $refineryImpacts),
            'hardest_hit_refinery' => $hardestHitRefinery,
            'binding_constraints' => array_map(fn (Week10BindingConstraintResult $constraint): array => $constraint->snapshot(), $bindingConstraints),
            'binding_count' => $bindingCount,
            'unresolved_dependencies' => $unresolved,
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
