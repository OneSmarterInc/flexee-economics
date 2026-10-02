<?php

namespace App\Domain\Scoring;

use App\Models\EconomicResolution;
use App\Models\KpiSnapshot;
use Illuminate\Support\Facades\DB;

final class Week4KpiPopulationService
{
    public function __construct(
        private readonly KpiDefinitionCatalog $catalog,
        private readonly Week4KpiInputAggregator $aggregator,
        private readonly KpiCalculationService $calculator,
        private readonly KpiSnapshotService $snapshots,
    ) {}

    /**
     * @return list<KpiSnapshot>
     */
    public function populate(EconomicResolution $resolution): array
    {
        return DB::transaction(function () use ($resolution): array {
            $definitions = $this->catalog->publishHaldenV1();
            $definitionIds = $definitions->pluck('id')->all();
            $existing = KpiSnapshot::query()
                ->where('tenant_id', $resolution->tenant_id)
                ->where('economic_resolution_id', $resolution->id)
                ->where('calculation_version', KpiCalculationService::CALCULATION_VERSION)
                ->whereIn('kpi_definition_id', $definitionIds)
                ->lockForUpdate()
                ->get();

            $existingDefinitionIds = $existing->pluck('kpi_definition_id')->all();
            $missingDefinitions = $definitions
                ->reject(fn ($definition) => in_array($definition->id, $existingDefinitionIds, true))
                ->values();

            if ($missingDefinitions->isNotEmpty()) {
                $context = $this->aggregator->fromEconomicResolution($resolution);
                $results = $this->calculator->calculate($context, $missingDefinitions);
                $this->snapshots->storeSnapshots($context, $results);
            }

            return array_values(KpiSnapshot::query()
                ->where('tenant_id', $resolution->tenant_id)
                ->where('economic_resolution_id', $resolution->id)
                ->where('calculation_version', KpiCalculationService::CALCULATION_VERSION)
                ->whereIn('kpi_definition_id', $definitionIds)
                ->orderBy('id')
                ->get()
                ->all());
        });
    }
}
