<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        $institution = Institution::factory()->create();

        return [
            'tenant_id' => $institution->tenant_id,
            'institution_id' => $institution->id,
            'code' => fake()->bothify('ECON###'),
            'name' => fake()->sentence(3),
            'term' => '2026 Fall',
        ];
    }
}
