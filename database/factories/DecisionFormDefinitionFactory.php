<?php

namespace Database\Factories;

use App\Models\DecisionFormDefinition;
use App\Models\SimulationWeek;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DecisionFormDefinition>
 */
class DecisionFormDefinitionFactory extends Factory
{
    public function definition(): array
    {
        $week = SimulationWeek::factory()->create();

        return [
            'simulation_version_id' => $week->simulation_version_id,
            'simulation_week_id' => $week->id,
            'key' => 'demo-decisions-'.fake()->unique()->numberBetween(1, 9999),
            'name' => 'Demo decisions',
            'version' => 'demo-v1',
            'is_required' => true,
            'status' => 'active',
            'metadata' => ['development_demo_only' => true],
        ];
    }
}
