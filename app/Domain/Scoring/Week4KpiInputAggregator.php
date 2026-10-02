<?php

namespace App\Domain\Scoring;

use App\Models\EconomicResolution;

final class Week4KpiInputAggregator
{
    public function fromEconomicResolution(EconomicResolution $resolution): KpiCalculationContext
    {
        return new KpiCalculationContext(
            tenantId: $resolution->tenant_id,
            sectionSimulationId: $resolution->section_simulation_id,
            sectionSimulationWeekId: $resolution->section_simulation_week_id,
            teamSimulationId: $resolution->team_simulation_id,
            teamId: $resolution->team_id,
            sourceType: EconomicResolution::class,
            sourceId: $resolution->id,
            availableInputs: [
                'integrated_margin_per_boe' => $resolution->integrated_margin,
                'upstream_margin' => $resolution->upstream_margin,
                'refining_margin_contribution' => $resolution->refining_margin,
            ],
            inputSnapshot: [
                'economic_resolution_id' => $resolution->id,
                'engine_version' => $resolution->engine_version,
                'input_snapshot' => $resolution->input_snapshot,
                'output_snapshot' => $resolution->output_snapshot,
            ],
        );
    }
}
