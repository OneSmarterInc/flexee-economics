<?php

namespace App\Domain\Economics\Week10;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week10EconomicResult
{
    public const STATUS_CALCULATED = 'calculated';

    public const STATUS_UNRESOLVED_DEPENDENCY = 'unresolved_dependency';

    /**
     * @param  array<string, Week10DemandImpact>  $demandImpacts
     * @param  array<string, Week10RefineryImpact>  $refineryImpacts
     * @param  array<string, Week10BindingConstraintResult>  $bindingConstraints
     * @param  list<string>  $unresolvedDependencies
     * @param  array<string, mixed>  $inputSnapshot
     * @param  array<string, mixed>  $outputSnapshot
     */
    public function __construct(
        public string $status,
        public array $demandImpacts,
        public BigDecimal $blendedDemandHit,
        public array $refineryImpacts,
        public string $hardestHitRefinery,
        public array $bindingConstraints,
        public int $bindingCount,
        public array $unresolvedDependencies,
        public array $inputSnapshot,
        public array $outputSnapshot,
        public string $engineIdentifier,
        public string $engineVersion,
        public string $packageVersion,
    ) {}

    public function demandHit(string $product): BigDecimal
    {
        return $this->demandImpacts[$product]->demandHit;
    }

    public function refineryHit(string $refinery): BigDecimal
    {
        return $this->refineryImpacts[$refinery]->demandHit;
    }

    public function bindingStatus(string $constraint): bool
    {
        return $this->bindingConstraints[$constraint]->binding;
    }

    /**
     * @return array<string, string>
     */
    public function scaledDemandHits(): array
    {
        return array_map(
            fn (Week10DemandImpact $impact): string => (string) $impact->demandHit->toScale(6, RoundingMode::HalfUp),
            $this->demandImpacts,
        );
    }
}
