<?php

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\WeekContentVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'simulation_version_id', 'simulation_week_id', 'version', 'content_key', 'manifest_reference', 'config_hash', 'metadata', 'status'])]
class WeekContentVersion extends Model
{
    /** @use HasFactory<WeekContentVersionFactory> */
    use HasFactory, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (WeekContentVersion $contentVersion): void {
            $week = SimulationWeek::query()->findOrFail($contentVersion->simulation_week_id);

            if ($week->simulation_version_id !== $contentVersion->simulation_version_id) {
                throw new InvalidArgumentException('Week content version must belong to the selected simulation version.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SimulationWeek, $this>
     */
    public function week(): BelongsTo
    {
        return $this->belongsTo(SimulationWeek::class, 'simulation_week_id');
    }
}
