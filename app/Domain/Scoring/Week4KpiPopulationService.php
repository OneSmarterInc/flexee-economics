<?php

namespace App\Domain\Scoring;

use App\Models\EconomicResolution;
use App\Models\KpiSnapshot;
use App\Models\SectionSimulationWeek;
use App\Models\TeamSimulation;

final class Week4KpiPopulationService
{
    public function __construct(
        private readonly PackageBackedKpiPopulationService $packageBackedKpis,
    ) {}

    /**
     * @return list<KpiSnapshot>
     */
    public function populate(EconomicResolution $resolution): array
    {
        $teamSimulation = TeamSimulation::query()->findOrFail($resolution->team_simulation_id);
        $runtimeWeek = SectionSimulationWeek::query()->findOrFail($resolution->section_simulation_week_id);

        return $this->packageBackedKpis->populateForTeamWeek($teamSimulation, $runtimeWeek, $resolution);
    }
}
