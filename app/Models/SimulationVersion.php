<?php

namespace App\Models;

use App\Enums\SimulationVersionStatus;
use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\SimulationVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable(['ulid', 'simulation_id', 'simulation_variant_id', 'version', 'status', 'config_hash', 'configuration', 'notes', 'published_at'])]
class SimulationVersion extends Model
{
    /** @use HasFactory<SimulationVersionFactory> */
    use HasFactory, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (SimulationVersion $version): void {
            $variant = SimulationVariant::query()->findOrFail($version->simulation_variant_id);

            if ($variant->simulation_id !== $version->simulation_id) {
                throw new InvalidArgumentException('Simulation version must belong to the selected simulation and variant.');
            }
        });

        static::updating(function (SimulationVersion $version): void {
            $materialFields = ['simulation_id', 'simulation_variant_id', 'version', 'config_hash', 'configuration'];
            $originalStatus = SimulationVersionStatus::tryFrom((string) $version->getOriginal('status'));
            $isMaterialChange = $version->isDirty($materialFields);
            $isPublished = $originalStatus === SimulationVersionStatus::Published;
            $isInUse = $version->sectionSimulations()->exists();

            if ($isMaterialChange && ($isPublished || $isInUse)) {
                throw new InvalidArgumentException('Published or in-use simulation versions cannot be materially modified.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'published_at' => 'datetime',
            'status' => SimulationVersionStatus::class,
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
     * @return BelongsTo<SimulationVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(SimulationVariant::class, 'simulation_variant_id');
    }

    /**
     * @return HasMany<SimulationWeek, $this>
     */
    public function weeks(): HasMany
    {
        return $this->hasMany(SimulationWeek::class);
    }

    /**
     * @return HasMany<SectionSimulation, $this>
     */
    public function sectionSimulations(): HasMany
    {
        return $this->hasMany(SectionSimulation::class);
    }
}
