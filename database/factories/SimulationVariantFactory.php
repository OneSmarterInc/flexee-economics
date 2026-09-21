<?php

namespace Database\Factories;

use App\Models\Simulation;
use App\Models\SimulationVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SimulationVariant>
 */
class SimulationVariantFactory extends Factory
{
    public function definition(): array
    {
        $simulation = Simulation::factory()->create();
        $name = 'Variant '.fake()->unique()->numberBetween(1, 9999);

        return [
            'simulation_id' => $simulation->id,
            'slug' => Str::slug($name),
            'name' => $name,
            'duration_weeks' => 14,
            'metadata' => [],
        ];
    }
}
