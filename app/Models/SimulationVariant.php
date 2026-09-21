<?php

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\SimulationVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ulid', 'simulation_id', 'slug', 'name', 'duration_weeks', 'metadata'])]
class SimulationVariant extends Model
{
    /** @use HasFactory<SimulationVariantFactory> */
    use HasFactory, HasUlidRouteKey;

    protected function casts(): array
    {
        return [
            'duration_weeks' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Simulation, $this>
     */
    public function simulation(): BelongsTo
    {
        return $this->belongsTo(Simulation::class);
    }

    /**
     * @return HasMany<SimulationVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(SimulationVersion::class);
    }
}
