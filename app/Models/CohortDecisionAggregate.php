<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'source_section_simulation_week_id', 'target_section_simulation_week_id', 'cohort_response_function_id', 'function_key', 'function_version', 'individual_decisions_snapshot', 'aggregate_snapshot', 'response_snapshot', 'calculated_by_user_id', 'calculated_at'])]
class CohortDecisionAggregate extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (CohortDecisionAggregate $aggregate): void {
            self::validateRuntimeWeek($aggregate, $aggregate->source_section_simulation_week_id);
            self::validateRuntimeWeek($aggregate, $aggregate->target_section_simulation_week_id);
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Cohort decision aggregates are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Cohort decision aggregates are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'individual_decisions_snapshot' => 'array',
            'aggregate_snapshot' => 'array',
            'response_snapshot' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    private static function validateRuntimeWeek(CohortDecisionAggregate $aggregate, int $runtimeWeekId): void
    {
        $runtimeWeek = SectionSimulationWeek::query()->findOrFail($runtimeWeekId);

        if ($runtimeWeek->tenant_id !== $aggregate->tenant_id || $runtimeWeek->section_simulation_id !== $aggregate->section_simulation_id) {
            throw new InvalidArgumentException('Cohort aggregate runtime weeks must match section simulation.');
        }
    }

    /**
     * @return BelongsTo<CohortResponseFunction, $this>
     */
    public function responseFunction(): BelongsTo
    {
        return $this->belongsTo(CohortResponseFunction::class, 'cohort_response_function_id');
    }

    /**
     * @return HasOne<CohortFeedbackEffect, $this>
     */
    public function effect(): HasOne
    {
        return $this->hasOne(CohortFeedbackEffect::class);
    }
}
