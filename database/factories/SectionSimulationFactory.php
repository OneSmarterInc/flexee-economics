<?php

namespace Database\Factories;

use App\Enums\SectionSimulationStatus;
use App\Models\Section;
use App\Models\SectionSimulation;
use App\Models\SimulationVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionSimulation>
 */
class SectionSimulationFactory extends Factory
{
    public function definition(): array
    {
        $section = Section::factory()->create();
        $version = SimulationVersion::factory()->published()->create();

        return [
            'tenant_id' => $section->tenant_id,
            'section_id' => $section->id,
            'simulation_id' => $version->simulation_id,
            'simulation_variant_id' => $version->simulation_variant_id,
            'simulation_version_id' => $version->id,
            'created_by_user_id' => User::factory()->faculty()->create(['tenant_id' => $section->tenant_id])->id,
            'name' => 'Section Simulation '.fake()->unique()->numberBetween(1, 9999),
            'status' => SectionSimulationStatus::Active,
            'starts_at' => null,
            'ends_at' => null,
            'metadata' => [],
        ];
    }
}
