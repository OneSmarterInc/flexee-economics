<?php

namespace App\Domain\Scoring;

use App\Models\KpiSnapshot;
use App\Models\SectionSimulationWeek;
use App\Models\TeamSimulation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class PackageBackedKpiPopulationService
{
    public function __construct(
        private KpiDefinitionCatalog $catalog,
        private KpiFinancialStateService $states,
        private KpiCalculationService $calculator,
        private KpiSnapshotService $snapshots,
    ) {}

    /**
     * @return list<KpiSnapshot>
     */
    public function populateForTeamWeek(
        TeamSimulation $teamSimulation,
        SectionSimulationWeek $runtimeWeek,
        ?Model $source = null,
    ): array {
        return DB::transaction(function () use ($teamSimulation, $runtimeWeek, $source): array {
            $definitions = $this->catalog->publishHaldenV1();
            $definitionIds = $definitions->pluck('id')->all();

            $existing = KpiSnapshot::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->where('calculation_version', KpiCalculationService::CALCULATION_VERSION)
                ->whereIn('kpi_definition_id', $definitionIds)
                ->lockForUpdate()
                ->get();

            $existingDefinitionIds = $existing->pluck('kpi_definition_id')->all();
            $missingDefinitions = $definitions
                ->reject(fn ($definition) => in_array($definition->id, $existingDefinitionIds, true))
                ->values();

            if ($missingDefinitions->isNotEmpty()) {
                $state = $this->states->forTeamWeek($teamSimulation, $runtimeWeek);
                $context = new KpiCalculationContext(
                    tenantId: $runtimeWeek->tenant_id,
                    sectionSimulationId: $runtimeWeek->section_simulation_id,
                    sectionSimulationWeekId: $runtimeWeek->id,
                    teamSimulationId: $teamSimulation->id,
                    teamId: $teamSimulation->team_id,
                    sourceType: $source instanceof Model ? $source::class : SectionSimulationWeek::class,
                    sourceId: $source instanceof Model ? (int) $source->getKey() : $runtimeWeek->id,
                    availableInputs: $state->state,
                    inputSnapshot: [
                        'package_backed_kpi_state' => true,
                        'runtime_week_id' => $runtimeWeek->id,
                        'runtime_week_number' => (int) $runtimeWeek->definition->week_number,
                        'source_type' => $source instanceof Model ? $source::class : SectionSimulationWeek::class,
                        'source_id' => $source instanceof Model ? (int) $source->getKey() : $runtimeWeek->id,
                        'state' => $state->state,
                        'inputs' => $state->inputs,
                        'applied_rules' => $state->appliedRules,
                        'provenance' => $state->provenance,
                    ],
                );

                $results = $this->calculator->calculate($context, $missingDefinitions);
                $this->snapshots->storeSnapshots($context, $results);
            }

            return array_values(KpiSnapshot::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->where('calculation_version', KpiCalculationService::CALCULATION_VERSION)
                ->whereIn('kpi_definition_id', $definitionIds)
                ->orderBy('id')
                ->get()
                ->all());
        });
    }
}
