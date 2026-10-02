<?php

namespace Database\Factories;

use App\Models\SimulationVersion;
use App\Models\SimulationWeek;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SimulationWeek>
 */
class SimulationWeekFactory extends Factory
{
    public function definition(): array
    {
        $version = SimulationVersion::factory()->create();
        $weekNumber = fake()->unique()->numberBetween(1, 99);

        return [
            'simulation_id' => $version->simulation_id,
            'simulation_variant_id' => $version->simulation_variant_id,
            'simulation_version_id' => $version->id,
            'week_number' => $weekNumber,
            'slug' => 'week-'.$weekNumber,
            'title' => 'Week '.$weekNumber,
            'pattern' => 'briefing',
            'content_key' => null,
            'content_metadata' => [],
            'status' => 'active',
        ];
    }
}
