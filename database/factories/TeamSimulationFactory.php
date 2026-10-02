<?php

namespace Database\Factories;

use App\Enums\TeamSimulationStatus;
use App\Models\SectionSimulation;
use App\Models\Team;
use App\Models\TeamSimulation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamSimulation>
 */
class TeamSimulationFactory extends Factory
{
    public function definition(): array
    {
        $sectionSimulation = SectionSimulation::factory()->create();
        $team = Team::factory()->create([
            'tenant_id' => $sectionSimulation->tenant_id,
            'section_id' => $sectionSimulation->section_id,
        ]);

        return [
            'tenant_id' => $sectionSimulation->tenant_id,
            'section_simulation_id' => $sectionSimulation->id,
            'section_id' => $sectionSimulation->section_id,
            'team_id' => $team->id,
            'status' => TeamSimulationStatus::Active,
            'metadata' => [],
        ];
    }
}
