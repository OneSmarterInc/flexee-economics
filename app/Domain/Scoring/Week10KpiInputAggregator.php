<?php

namespace App\Domain\Scoring;

use App\Models\Week10EconomicEvaluation;

final class Week10KpiInputAggregator
{
    public function fromEvaluation(Week10EconomicEvaluation $evaluation): KpiCalculationContext
    {
        return new KpiCalculationContext(
            tenantId: $evaluation->tenant_id,
            sectionSimulationId: $evaluation->section_simulation_id,
            sectionSimulationWeekId: $evaluation->section_simulation_week_id,
            teamSimulationId: $evaluation->team_simulation_id,
            teamId: $evaluation->team_id,
            sourceType: Week10EconomicEvaluation::class,
            sourceId: $evaluation->id,
            availableInputs: [],
            inputSnapshot: [
                'week10_economic_evaluation_id' => $evaluation->id,
                'engine_version' => $evaluation->engine_version,
                'package_version' => $evaluation->package_version,
                'status' => $evaluation->status,
                'inherited_state_snapshot' => $evaluation->inherited_state_snapshot,
                'input_snapshot' => $evaluation->input_snapshot,
                'output_snapshot' => $evaluation->output_snapshot,
                'economic_outputs' => [
                    'gasoline_demand_hit' => $evaluation->getRawOriginal('gasoline_demand_hit'),
                    'diesel_demand_hit' => $evaluation->getRawOriginal('diesel_demand_hit'),
                    'jet_demand_hit' => $evaluation->getRawOriginal('jet_demand_hit'),
                    'blended_demand_hit' => $evaluation->getRawOriginal('blended_demand_hit'),
                    'baton_rouge_demand_hit' => $evaluation->getRawOriginal('baton_rouge_demand_hit'),
                    'rotterdam_demand_hit' => $evaluation->getRawOriginal('rotterdam_demand_hit'),
                    'singapore_demand_hit' => $evaluation->getRawOriginal('singapore_demand_hit'),
                    'hardest_hit_refinery' => $evaluation->hardest_hit_refinery,
                    'binding_constraint_count' => $evaluation->binding_constraint_count,
                ],
            ],
        );
    }
}
