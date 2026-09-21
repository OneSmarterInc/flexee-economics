<?php

namespace Database\Factories;

use App\Models\Section;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        $section = Section::factory()->create();
        $name = 'Team '.fake()->unique()->numberBetween(1, 9999);

        return [
            'tenant_id' => $section->tenant_id,
            'section_id' => $section->id,
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
