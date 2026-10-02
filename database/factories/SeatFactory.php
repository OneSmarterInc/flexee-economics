<?php

namespace Database\Factories;

use App\Models\Seat;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Seat>
 */
class SeatFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->jobTitle();

        return [
            'code' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'name' => $name,
            'sort_order' => fake()->numberBetween(1, 10),
            'content_ref' => null,
            'is_active' => true,
        ];
    }
}
