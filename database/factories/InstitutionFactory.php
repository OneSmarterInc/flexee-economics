<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Institution>
 */
class InstitutionFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company().' University';

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
        ];
    }
}
