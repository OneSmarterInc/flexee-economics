<?php

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\MemoDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'simulation_version_id', 'simulation_week_id', 'key', 'title', 'instructions', 'version', 'is_required', 'word_limit', 'character_limit', 'rubric_reference', 'submission_format', 'status', 'metadata'])]
class MemoDefinition extends Model
{
    /** @use HasFactory<MemoDefinitionFactory> */
    use HasFactory, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (MemoDefinition $definition): void {
            $week = SimulationWeek::query()->findOrFail($definition->simulation_week_id);

            if ($week->simulation_version_id !== $definition->simulation_version_id) {
                throw new InvalidArgumentException('Memo definition must match its simulation week version.');
            }
        });

        static::updating(function (MemoDefinition $definition): void {
            $materialFields = ['simulation_version_id', 'simulation_week_id', 'key', 'title', 'instructions', 'version', 'is_required', 'word_limit', 'character_limit', 'rubric_reference', 'submission_format', 'metadata'];
            $version = $definition->simulationVersion;

            if ($definition->isDirty($materialFields) && $version->isFrozen()) {
                throw new InvalidArgumentException('Memo definitions for published or in-use versions cannot be materially modified.');
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
}
