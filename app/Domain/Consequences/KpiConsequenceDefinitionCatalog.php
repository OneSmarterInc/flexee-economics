<?php

namespace App\Domain\Consequences;

use App\Models\CapitalAllocationEvaluation;
use App\Models\ConsequenceDefinition;
use App\Models\EconomicResolution;
use App\Models\StandingState;
use App\Models\Week8EconomicEvaluation;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class KpiConsequenceDefinitionCatalog
{
    public const VERSION = 'v1';

    public function __construct(
        private KpiConsequenceReferencePackage $package,
    ) {}

    public function delacroixCover(): ConsequenceDefinition
    {
        return $this->definition('week4_tp_delacroix_cover', EconomicResolution::class, EconomicResolution::class);
    }

    public function whitakerStanding(): ConsequenceDefinition
    {
        return $this->definition('week4_whitaker_standing', EconomicResolution::class, StandingState::class);
    }

    public function hedgeCoverage(): ConsequenceDefinition
    {
        return $this->definition('week5_hedge_coverage', StandingState::class, StandingState::class);
    }

    public function cancellableCapex(): ConsequenceDefinition
    {
        return $this->definition('week6_cancellable_capex_musd', CapitalAllocationEvaluation::class, CapitalAllocationEvaluation::class);
    }

    public function cashCushion(): ConsequenceDefinition
    {
        return $this->definition('week8_cash_cushion_musd', Week8EconomicEvaluation::class, Week8EconomicEvaluation::class);
    }

    public function straitsPacificFlex(): ConsequenceDefinition
    {
        return $this->definition('week10_straits_pacific_flex', StandingState::class, StandingState::class);
    }

    /**
     * @param  class-string<Model>  $sourceType
     * @param  class-string<Model>  $targetType
     */
    private function definition(string $key, string $sourceType, string $targetType): ConsequenceDefinition
    {
        $row = $this->package->catalogRow($key);

        if (($row['status'] ?? '') !== 'active') {
            throw new InvalidArgumentException("Consequence catalog row [{$key}] is not active.");
        }

        return ConsequenceDefinition::query()->firstOrCreate(
            [
                'key' => $key,
                'version' => $row['version'] ?: self::VERSION,
            ],
            [
                'name' => str($key)->replace('_', ' ')->title()->toString(),
                'description' => $row['rule'] ?: null,
                'source_type' => (new $sourceType)->getMorphClass(),
                'target_type' => (new $targetType)->getMorphClass(),
                'effect_type' => $row['effect_type'] ?: 'consequence',
                'is_active' => true,
                'metadata' => [
                    'package' => KpiConsequenceReferencePackage::PACKAGE_ROOT,
                    'package_version' => $this->package->version(),
                    'catalog_row' => $row,
                ],
            ],
        );
    }
}
