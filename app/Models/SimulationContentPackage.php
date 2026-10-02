<?php

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable(['ulid', 'simulation_version_id', 'simulation_week_id', 'package_type', 'version', 'manifest', 'manifest_hash', 'status', 'validation_summary', 'validated_at'])]
class SimulationContentPackage extends Model
{
    use HasUlidRouteKey;

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_INVALID = 'invalid';

    protected static function booted(): void
    {
        static::saving(function (SimulationContentPackage $package): void {
            $week = SimulationWeek::query()->findOrFail($package->simulation_week_id);

            if ($week->simulation_version_id !== $package->simulation_version_id) {
                throw new InvalidArgumentException('Simulation content package must belong to the selected simulation version.');
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Simulation content packages are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Simulation content packages are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'validation_summary' => 'array',
            'validated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SimulationVersion, $this>
     */
    public function simulationVersion(): BelongsTo
    {
        return $this->belongsTo(SimulationVersion::class);
    }

    /**
     * @return BelongsTo<SimulationWeek, $this>
     */
    public function week(): BelongsTo
    {
        return $this->belongsTo(SimulationWeek::class, 'simulation_week_id');
    }

    /**
     * @return HasMany<ContentArtifact, $this>
     */
    public function artifacts(): HasMany
    {
        return $this->hasMany(ContentArtifact::class);
    }
}
