<?php

namespace Database\Factories;

use App\Models\SimulationWeek;
use App\Models\WeekContentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeekContentVersion>
 */
class WeekContentVersionFactory extends Factory
{
    public function definition(): array
    {
        $week = SimulationWeek::factory()->create();

        return [
            'simulation_version_id' => $week->simulation_version_id,
            'simulation_week_id' => $week->id,
            'version' => 'placeholder-v1',
            'content_key' => null,
            'manifest_reference' => null,
            'config_hash' => null,
            'metadata' => [],
            'status' => 'placeholder',
        ];
    }
}
