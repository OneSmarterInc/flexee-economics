<?php

namespace App\Domain\Scoring;

use App\Models\Week11EconomicEvaluation;

final class Week11KpiInputAggregator
{
    public function fromEvaluation(Week11EconomicEvaluation $evaluation): KpiCalculationContext
    {
        return new KpiCalculationContext(
            tenantId: $evaluation->tenant_id,
            sectionSimulationId: $evaluation->section_simulation_id,
            sectionSimulationWeekId: $evaluation->section_simulation_week_id,
            teamSimulationId: $evaluation->team_simulation_id,
            teamId: $evaluation->team_id,
            sourceType: Week11EconomicEvaluation::class,
            sourceId: $evaluation->id,
            availableInputs: [],
            inputSnapshot: [
                'week11_economic_evaluation_id' => $evaluation->id,
                'decision_submission_id' => $evaluation->decision_submission_id,
                'engine_version' => $evaluation->engine_version,
                'package_version' => $evaluation->package_version,
                'status' => $evaluation->status,
                'input_snapshot' => $evaluation->input_snapshot,
                'output_snapshot' => $evaluation->output_snapshot,
                'economic_outputs' => [
                    'realized_price' => $evaluation->getRawOriginal('realized_price'),
                    'profit_oil' => $evaluation->getRawOriginal('profit_oil'),
                    'annual_mbbl' => $evaluation->getRawOriginal('annual_mbbl'),
                    'annuity_factor' => $evaluation->getRawOriginal('annuity_factor'),
                    'pv_stay_current_musd' => $evaluation->getRawOriginal('pv_stay_current_musd'),
                    'pv_stay_demanded_musd' => $evaluation->getRawOriginal('pv_stay_demanded_musd'),
                    'exit_value_musd' => $evaluation->getRawOriginal('exit_value_musd'),
                    'stay_minus_exit_demanded_musd' => $evaluation->getRawOriginal('stay_minus_exit_demanded_musd'),
                    'indifference_take' => $evaluation->getRawOriginal('indifference_take'),
                    'comparables_min' => $evaluation->getRawOriginal('comparables_min'),
                    'comparables_max' => $evaluation->getRawOriginal('comparables_max'),
                    'demanded_take' => $evaluation->getRawOriginal('demanded_take'),
                    'demanded_take_inside_comparables' => $evaluation->demanded_take_inside_comparables,
                    'sunk_invariant' => $evaluation->sunk_invariant,
                ],
            ],
        );
    }
}
