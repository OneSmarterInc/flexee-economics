<?php

namespace App\Domain\Consequences;

use App\Models\ConsequenceDefinition;
use App\Models\ConsequenceLink;
use App\Models\EconomicResolution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class Week4ConsequenceResolver
{
    public function __construct(
        private readonly ConsequenceService $consequences,
        private readonly Week4ConsequenceDefinitionCatalog $definitions,
        private readonly DerivedWeek10ConstraintService $derivedConstraints,
    ) {}

    /**
     * @return list<ConsequenceLink>
     */
    public function resolve(EconomicResolution $resolution, ?User $actor = null): array
    {
        $resolution->loadMissing(['runtimeWeek.definition', 'teamSimulation']);

        if ($resolution->runtimeWeek->definition->week_number !== 4) {
            throw new InvalidArgumentException('Only Week 4 economic resolutions can produce Week 4 consequence links.');
        }

        return DB::transaction(function () use ($resolution, $actor): array {
            $links = [];
            $links[] = $this->firstOrCreateLink(
                resolution: $resolution,
                definition: $this->definitions->segmentMarginImpact(),
                explanation: 'Week 4 transfer price split integrated margin across upstream and refining without changing integrated margin.',
                metadata: [
                    'transfer_price' => $resolution->transfer_price,
                    'integrated_margin' => $resolution->integrated_margin,
                    'upstream_margin' => $resolution->upstream_margin,
                    'refining_margin' => $resolution->refining_margin,
                    'upstream_vs_target' => $resolution->upstream_vs_target,
                    'refining_vs_target' => $resolution->refining_vs_target,
                    'standing_update' => 'not_applied_without_authoritative_rule',
                ],
                actor: $actor,
            );
            $links[] = $this->firstOrCreateLink(
                resolution: $resolution,
                definition: $this->definitions->genevaArbitrageRecord(),
                explanation: 'Week 4 transfer price produced a deterministic Geneva arbitrage exposure recorded for future traceability.',
                metadata: [
                    'transfer_price' => $resolution->transfer_price,
                    'geneva_gap' => $resolution->geneva_gap,
                    'geneva_capture_per_bbl' => $resolution->geneva_capture_per_bbl,
                    'geneva_max_volume_bbl_day' => $resolution->geneva_max_volume_bbl_day,
                    'standing_update' => 'not_applied_without_authoritative_rule',
                ],
                actor: $actor,
            );

            $this->derivedConstraints->resolveWeek4Consequences($resolution, $actor);

            return $links;
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function firstOrCreateLink(
        EconomicResolution $resolution,
        ConsequenceDefinition $definition,
        string $explanation,
        array $metadata,
        ?User $actor,
    ): ConsequenceLink {
        $existing = ConsequenceLink::query()
            ->where('tenant_id', $resolution->tenant_id)
            ->where('team_simulation_id', $resolution->team_simulation_id)
            ->where('source_type', $resolution->getMorphClass())
            ->where('source_id', $resolution->id)
            ->where('target_type', $resolution->getMorphClass())
            ->where('target_id', $resolution->id)
            ->where('definition_key', $definition->key)
            ->where('definition_version', $definition->version)
            ->lockForUpdate()
            ->first();

        if ($existing instanceof ConsequenceLink) {
            return $existing;
        }

        return $this->consequences->createLink(
            teamSimulation: $resolution->teamSimulation,
            definition: $definition,
            source: $resolution,
            target: $resolution,
            explanation: $explanation,
            sourceWeek: $resolution->runtimeWeek,
            targetWeek: $resolution->runtimeWeek,
            actor: $actor,
            metadata: $metadata,
        );
    }
}
