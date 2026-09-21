<?php

namespace Database\Factories;

use App\Models\Simulation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Simulation>
 */
class SimulationFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Simulation '.fake()->unique()->numberBetween(1, 9999);

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'description' => fake()->sentence(),
            'status' => 'active',
            'metadata' => [],
        ];
    }
}
