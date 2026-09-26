<?php

namespace App\Domain\Scoring;

use App\Models\Week8EconomicEvaluation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Week8KpiInputAggregator
{
    public function fromEvaluation(Week8EconomicEvaluation $evaluation): KpiCalculationContext
    {
        return new KpiCalculationContext(
            tenantId: $evaluation->tenant_id,
            sectionSimulationId: $evaluation->section_simulation_id,
            sectionSimulationWeekId: $evaluation->section_simulation_week_id,
            teamSimulationId: $evaluation->team_simulation_id,
            teamId: $evaluation->team_id,
            sourceType: Week8EconomicEvaluation::class,
            sourceId: $evaluation->id,
            availableInputs: [
                'refining_net_margin_vs_benchmark' => $this->realizedRefiningMarginVsBenchmark($evaluation),
            ],
            inputSnapshot: [
                'week8_economic_evaluation_id' => $evaluation->id,
                'engine_version' => $evaluation->engine_version,
                'package_version' => $evaluation->package_version,
                'prediction_snapshot' => $evaluation->prediction_snapshot,
                'realization_snapshot' => $evaluation->realization_snapshot,
                'input_snapshot' => $evaluation->input_snapshot,
                'output_snapshot' => $evaluation->output_snapshot,
            ],
        );
    }

    private function realizedRefiningMarginVsBenchmark(Week8EconomicEvaluation $evaluation): ?string
    {
        if ($evaluation->realized_refining_crack === null) {
            return null;
        }

        $inputSnapshot = $evaluation->getAttribute('input_snapshot');

        if (! is_array($inputSnapshot)) {
            return null;
        }

        $baseline = $inputSnapshot['baseline'] ?? null;

        if (! is_array($baseline)) {
            return null;
        }

        $crackBase = $baseline['crack_base'] ?? null;

        if ($crackBase === null || $crackBase === '') {
            return null;
        }

        return (string) BigDecimal::of((string) $evaluation->realized_refining_crack)
            ->minus((string) $crackBase)
            ->toScale(4, RoundingMode::HalfUp);
    }
}
