<?php

namespace App\Domain\Consequences;

use App\Models\ConsequenceDefinition;
use App\Models\EconomicResolution;
use Illuminate\Database\Eloquent\Collection;

final class Week4ConsequenceDefinitionCatalog
{
    public const SEGMENT_MARGIN_IMPACT = 'week4_transfer_price_segment_margin_impact';

    public const GENEVA_ARBITRAGE_RECORD = 'week4_transfer_price_geneva_arbitrage_record';

    public const VERSION = 'v1';

    /**
     * @return Collection<int, ConsequenceDefinition>
     */
    public function ensureDefinitions(): Collection
    {
        return new Collection([
            $this->definition(
                key: self::SEGMENT_MARGIN_IMPACT,
                name: 'Week 4 transfer-price segment margin impact',
                description: 'Records how the selected transfer price split integrated value across upstream and refining.',
                effectType: 'segment_margin_impact',
            ),
            $this->definition(
                key: self::GENEVA_ARBITRAGE_RECORD,
                name: 'Week 4 transfer-price Geneva arbitrage record',
                description: 'Records the deterministic Geneva arbitrage exposure produced by the Week 4 transfer-pricing result.',
                effectType: 'geneva_arbitrage_record',
            ),
        ]);
    }

    public function segmentMarginImpact(): ConsequenceDefinition
    {
        $this->ensureDefinitions();

        return ConsequenceDefinition::query()
            ->where('key', self::SEGMENT_MARGIN_IMPACT)
            ->where('version', self::VERSION)
            ->firstOrFail();
    }

    public function genevaArbitrageRecord(): ConsequenceDefinition
    {
        $this->ensureDefinitions();

        return ConsequenceDefinition::query()
            ->where('key', self::GENEVA_ARBITRAGE_RECORD)
            ->where('version', self::VERSION)
            ->firstOrFail();
    }

    private function definition(string $key, string $name, string $description, string $effectType): ConsequenceDefinition
    {
        return ConsequenceDefinition::query()->firstOrCreate(
            [
                'key' => $key,
                'version' => self::VERSION,
            ],
            [
                'name' => $name,
                'description' => $description,
                'source_type' => EconomicResolution::class,
                'target_type' => EconomicResolution::class,
                'effect_type' => $effectType,
                'is_active' => true,
                'metadata' => [
                    'source' => 'Batch 6C Week 4 consequence mapping foundation',
                    'standing_updates' => 'deferred',
                ],
            ],
        );
    }
}
