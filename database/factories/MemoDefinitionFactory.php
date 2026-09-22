<?php

namespace Database\Factories;

use App\Models\MemoDefinition;
use App\Models\SimulationWeek;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoDefinition>
 */
class MemoDefinitionFactory extends Factory
{
    public function definition(): array
    {
        $week = SimulationWeek::factory()->create();

        return [
            'simulation_version_id' => $week->simulation_version_id,
            'simulation_week_id' => $week->id,
            'key' => 'demo-memo-'.fake()->unique()->numberBetween(1, 9999),
            'title' => 'Demo memo',
            'instructions' => 'Development/demo only.',
            'version' => 'demo-v1',
            'is_required' => true,
            'word_limit' => null,
            'character_limit' => 2000,
            'rubric_reference' => null,
            'submission_format' => 'text',
            'status' => 'active',
            'metadata' => ['development_demo_only' => true],
        ];
    }
}
