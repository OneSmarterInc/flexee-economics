<?php

namespace Database\Factories;

use App\Models\GeneratedArtifact;
use App\Models\SimulationWeek;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeneratedArtifact>
 */
class GeneratedArtifactFactory extends Factory
{
    public function definition(): array
    {
        $week = SimulationWeek::factory()->create();

        return [
            'simulation_version_id' => $week->simulation_version_id,
            'simulation_week_id' => $week->id,
            'artifact_key' => 'artifact-'.fake()->unique()->numberBetween(1, 9999),
            'artifact_type' => 'briefing',
            'version' => 'placeholder-v1',
            'hash' => fake()->sha1(),
            'storage_disk' => 'local',
            'storage_path' => null,
            'metadata' => [],
        ];
    }
}
