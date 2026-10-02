<?php

namespace Database\Factories;

use App\Enums\DecisionFieldType;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DecisionFieldDefinition>
 */
class DecisionFieldDefinitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'decision_form_definition_id' => DecisionFormDefinition::factory(),
            'field_key' => 'demo_field_'.fake()->unique()->numberBetween(1, 9999),
            'label' => 'Demo field',
            'field_type' => DecisionFieldType::Integer,
            'is_required' => true,
            'display_order' => 1,
            'help_text' => 'Development/demo only.',
            'unit' => null,
            'validation' => ['min' => 0, 'max' => 100],
            'options' => null,
            'visibility' => null,
        ];
    }
}
