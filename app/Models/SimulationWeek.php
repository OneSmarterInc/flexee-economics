<?php

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\SimulationWeekFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable(['ulid', 'simulation_id', 'simulation_variant_id', 'simulation_version_id', 'week_number', 'slug', 'title', 'pattern', 'content_key', 'content_metadata', 'status'])]
class SimulationWeek extends Model
{
    /** @use HasFactory<SimulationWeekFactory> */
    use HasFactory, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (SimulationWeek $week): void {
            $version = SimulationVersion::query()->findOrFail($week->simulation_version_id);

            if ($version->simulation_id !== $week->simulation_id || $version->simulation_variant_id !== $week->simulation_variant_id) {
                throw new InvalidArgumentException('Simulation week must belong to the selected simulation version.');
            }
        });

        static::updating(function (SimulationWeek $week): void {
            $materialFields = ['simulation_id', 'simulation_variant_id', 'simulation_version_id', 'week_number', 'slug', 'title', 'pattern', 'content_key', 'content_metadata'];
            $version = $week->version()->firstOrFail();

            if ($week->isDirty($materialFields) && $version->isFrozen()) {
                throw new InvalidArgumentException('Weeks belonging to published or in-use simulation versions cannot be materially modified.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'content_metadata' => 'array',
            'week_number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SimulationVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(SimulationVersion::class, 'simulation_version_id');
    }

    /**
     * @return HasMany<WeekContentVersion, $this>
     */
    public function contentVersions(): HasMany
    {
        return $this->hasMany(WeekContentVersion::class);
    }
}
