<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    public function definition(): array
    {
        $course = Course::factory()->create();

        return [
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'name' => fake()->randomElement(['Section A', 'Section B', 'Section C']).' '.fake()->numberBetween(1, 999),
            'status' => 'active',
        ];
    }
}
