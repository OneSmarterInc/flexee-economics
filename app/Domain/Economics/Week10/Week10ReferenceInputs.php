<?php

namespace App\Domain\Economics\Week10;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final readonly class Week10ReferenceInputs
{
    /**
     * @param  array<string, array{income_elasticity: BigDecimal, demand_share_blended: BigDecimal}>  $productElasticities
     * @param  array<string, BigDecimal>  $recessionParameters
     * @param  array<string, array<string, BigDecimal>>  $refineryYields
     * @param  array<string, BigDecimal>  $bindingRules
     * @param  array<string, array<string, mixed>>  $teamPriorStates
     * @param  array<string, mixed>  $golden
     * @param  array<string, string>  $sourceHashes
     * @param  array<string, array<string, string>>  $runtimeDependencies
     * @param  list<array<string, string>>  $decisionStructure
     */
    public function __construct(
        public array $productElasticities,
        public array $recessionParameters,
        public array $refineryYields,
        public array $bindingRules,
        public array $teamPriorStates,
        public array $golden,
        public array $sourceHashes,
        public array $runtimeDependencies,
        public array $decisionStructure,
        public string $packageVersion,
    ) {}

    public function productElasticity(string $product): BigDecimal
    {
        $value = $this->productElasticities[$product]['income_elasticity'] ?? null;

        if (! $value instanceof BigDecimal) {
            throw new InvalidArgumentException("Unknown Week 10 product [{$product}].");
        }

        return $value;
    }

    public function demandShare(string $product): BigDecimal
    {
        $value = $this->productElasticities[$product]['demand_share_blended'] ?? null;

        if (! $value instanceof BigDecimal) {
            throw new InvalidArgumentException("Unknown Week 10 product [{$product}].");
        }

        return $value;
    }

    public function recessionParameter(string $parameter): BigDecimal
    {
        $value = $this->recessionParameters[$parameter] ?? null;

        if (! $value instanceof BigDecimal) {
            throw new InvalidArgumentException("Unknown Week 10 recession parameter [{$parameter}].");
        }

        return $value;
    }

    public function refineryYield(string $refinery, string $product): BigDecimal
    {
        $value = $this->refineryYields[$refinery][$product] ?? null;

        if (! $value instanceof BigDecimal) {
            throw new InvalidArgumentException("Unknown Week 10 refinery yield [{$refinery}:{$product}].");
        }

        return $value;
    }

    public function bindingRule(string $parameter): BigDecimal
    {
        $value = $this->bindingRules[$parameter] ?? null;

        if (! $value instanceof BigDecimal) {
            throw new InvalidArgumentException("Unknown Week 10 binding rule [{$parameter}].");
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function priorState(string $team): array
    {
        $state = $this->teamPriorStates[$team] ?? null;

        if (! is_array($state)) {
            throw new InvalidArgumentException("Unknown Week 10 prior-state fixture [{$team}].");
        }

        return $state;
    }
}
