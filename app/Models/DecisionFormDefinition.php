<?php

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\DecisionFormDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable(['ulid', 'simulation_version_id', 'simulation_week_id', 'key', 'name', 'version', 'is_required', 'status', 'metadata'])]
class DecisionFormDefinition extends Model
{
    /** @use HasFactory<DecisionFormDefinitionFactory> */
    use HasFactory, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (DecisionFormDefinition $definition): void {
            $week = SimulationWeek::query()->findOrFail($definition->simulation_week_id);

            if ($week->simulation_version_id !== $definition->simulation_version_id) {
                throw new InvalidArgumentException('Decision form definition must match its simulation week version.');
            }
        });

        static::updating(function (DecisionFormDefinition $definition): void {
            $materialFields = ['simulation_version_id', 'simulation_week_id', 'key', 'name', 'version', 'is_required', 'metadata'];
            $version = $definition->simulationVersion;

            if ($definition->isDirty($materialFields) && $version->isFrozen()) {
                throw new InvalidArgumentException('Decision definitions for published or in-use versions cannot be materially modified.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'metadata' => 'array',
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
    public function simulationWeek(): BelongsTo
    {
        return $this->belongsTo(SimulationWeek::class);
    }

    /**
     * @return HasMany<DecisionFieldDefinition, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(DecisionFieldDefinition::class)->orderBy('display_order')->orderBy('id');
    }
}
