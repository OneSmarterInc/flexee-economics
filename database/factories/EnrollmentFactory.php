<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        $section = Section::factory()->create();

        return [
            'tenant_id' => $section->tenant_id,
            'section_id' => $section->id,
            'user_id' => User::factory()->student()->create(['tenant_id' => $section->tenant_id])->id,
            'status' => 'active',
        ];
    }
}
