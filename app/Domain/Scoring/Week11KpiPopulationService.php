<?php

namespace App\Domain\Scoring;

use App\Models\KpiSnapshot;
use App\Models\Week11EconomicEvaluation;
use Illuminate\Support\Facades\DB;

final readonly class Week11KpiPopulationService
{
    public function __construct(
        private KpiDefinitionCatalog $catalog,
        private Week11KpiInputAggregator $aggregator,
        private KpiCalculationService $calculator,
        private KpiSnapshotService $snapshots,
    ) {}

    /**
     * @return list<KpiSnapshot>
     */
    public function populate(Week11EconomicEvaluation $evaluation): array
    {
        if ($evaluation->status !== Week11EconomicEvaluation::STATUS_CALCULATED) {
            return [];
        }

        return DB::transaction(function () use ($evaluation): array {
            $definitions = $this->catalog->publishHaldenV1();
            $definitionIds = $definitions->pluck('id')->all();
            $existing = KpiSnapshot::query()
                ->where('tenant_id', $evaluation->tenant_id)
                ->where('section_simulation_week_id', $evaluation->section_simulation_week_id)
                ->where('team_simulation_id', $evaluation->team_simulation_id)
                ->where('calculation_version', KpiCalculationService::CALCULATION_VERSION)
                ->whereIn('kpi_definition_id', $definitionIds)
                ->lockForUpdate()
                ->get();

            $existingDefinitionIds = $existing->pluck('kpi_definition_id')->all();
            $missingDefinitions = $definitions
                ->reject(fn ($definition) => in_array($definition->id, $existingDefinitionIds, true))
                ->values();

            if ($missingDefinitions->isNotEmpty()) {
                $context = $this->aggregator->fromEvaluation($evaluation);
                $results = $this->calculator->calculate($context, $missingDefinitions);
                $this->snapshots->storeSnapshots($context, $results);
            }

            return array_values(KpiSnapshot::query()
                ->where('tenant_id', $evaluation->tenant_id)
                ->where('section_simulation_week_id', $evaluation->section_simulation_week_id)
                ->where('team_simulation_id', $evaluation->team_simulation_id)
                ->where('calculation_version', KpiCalculationService::CALCULATION_VERSION)
                ->whereIn('kpi_definition_id', $definitionIds)
                ->orderBy('id')
                ->get()
                ->all());
        });
    }
}
