<?php

namespace App\Domain\Scoring;

use App\Models\KpiSnapshot;
use App\Models\SectionSimulationWeek;
use App\Models\TeamSimulation;
use App\Models\Week11EconomicEvaluation;

final readonly class Week11KpiPopulationService
{
    public function __construct(
        private PackageBackedKpiPopulationService $packageBackedKpis,
    ) {}

    /**
     * @return list<KpiSnapshot>
     */
    public function populate(Week11EconomicEvaluation $evaluation): array
    {
        if ($evaluation->status !== Week11EconomicEvaluation::STATUS_CALCULATED) {
            return [];
        }

        $teamSimulation = TeamSimulation::query()->findOrFail($evaluation->team_simulation_id);
        $runtimeWeek = SectionSimulationWeek::query()->findOrFail($evaluation->section_simulation_week_id);

        return $this->packageBackedKpis->populateForTeamWeek($teamSimulation, $runtimeWeek, $evaluation);
    }
}
