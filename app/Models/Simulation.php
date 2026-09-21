<?php

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\SimulationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ulid', 'slug', 'name', 'description', 'status', 'metadata'])]
class Simulation extends Model
{
    /** @use HasFactory<SimulationFactory> */
    use HasFactory, HasUlidRouteKey;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<SimulationVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(SimulationVariant::class);
    }

    /**
     * @return HasMany<SimulationVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(SimulationVersion::class);
    }
}
