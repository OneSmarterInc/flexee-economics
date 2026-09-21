<?php

namespace Database\Factories;

use App\Enums\SectionSimulationWeekStatus;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionSimulationWeek>
 */
class SectionSimulationWeekFactory extends Factory
{
    public function definition(): array
    {
        $sectionSimulation = SectionSimulation::factory()->create();
        $week = SimulationWeek::factory()->create([
            'simulation_id' => $sectionSimulation->simulation_id,
            'simulation_variant_id' => $sectionSimulation->simulation_variant_id,
            'simulation_version_id' => $sectionSimulation->simulation_version_id,
        ]);

        return [
            'tenant_id' => $sectionSimulation->tenant_id,
            'section_simulation_id' => $sectionSimulation->id,
            'simulation_version_id' => $sectionSimulation->simulation_version_id,
            'simulation_week_id' => $week->id,
            'status' => SectionSimulationWeekStatus::Draft,
            'metadata' => [],
        ];
    }
}
