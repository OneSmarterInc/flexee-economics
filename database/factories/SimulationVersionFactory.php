<?php

namespace Database\Factories;

use App\Enums\SimulationVersionStatus;
use App\Models\SimulationVariant;
use App\Models\SimulationVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SimulationVersion>
 */
class SimulationVersionFactory extends Factory
{
    public function definition(): array
    {
        $variant = SimulationVariant::factory()->create();

        return [
            'simulation_id' => $variant->simulation_id,
            'simulation_variant_id' => $variant->id,
            'version' => 'v'.fake()->unique()->numberBetween(1, 9999),
            'status' => SimulationVersionStatus::Draft,
            'config_hash' => fake()->sha1(),
            'configuration' => [],
            'notes' => null,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SimulationVersionStatus::Published,
            'published_at' => now(),
        ]);
    }
}
