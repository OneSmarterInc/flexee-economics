<?php

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'simulation_version_id', 'simulation_week_id', 'simulation_content_package_id', 'package_type', 'package_version', 'status', 'activated_at', 'retired_at'])]
class SimulationContentActivation extends Model
{
    use HasUlidRouteKey;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RETIRED = 'retired';

    protected static function booted(): void
    {
        static::saving(function (SimulationContentActivation $activation): void {
            $package = SimulationContentPackage::query()->findOrFail($activation->simulation_content_package_id);

            if ($package->simulation_version_id !== $activation->simulation_version_id || $package->simulation_week_id !== $activation->simulation_week_id) {
                throw new InvalidArgumentException('Content activation package must match the selected simulation week.');
            }

            if ($package->package_type !== $activation->package_type || $package->version !== $activation->package_version) {
                throw new InvalidArgumentException('Content activation package identity is inconsistent.');
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Simulation content activations are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Simulation content activations are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SimulationContentPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(SimulationContentPackage::class, 'simulation_content_package_id');
    }
}
