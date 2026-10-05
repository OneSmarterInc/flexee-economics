<?php

namespace App\Domain\Scoring;

use App\Models\KpiSnapshot;
use App\Models\SectionSimulationWeek;
use App\Models\TeamSimulation;
use App\Models\Week10EconomicEvaluation;

final readonly class Week10KpiPopulationService
{
    public function __construct(
        private PackageBackedKpiPopulationService $packageBackedKpis,
    ) {}

    /**
     * @return list<KpiSnapshot>
     */
    public function populate(Week10EconomicEvaluation $evaluation): array
    {
        if ($evaluation->status !== Week10EconomicEvaluation::STATUS_CALCULATED) {
            return [];
        }

        $teamSimulation = TeamSimulation::query()->findOrFail($evaluation->team_simulation_id);
        $runtimeWeek = SectionSimulationWeek::query()->findOrFail($evaluation->section_simulation_week_id);

        return $this->packageBackedKpis->populateForTeamWeek($teamSimulation, $runtimeWeek, $evaluation);
    }
}
