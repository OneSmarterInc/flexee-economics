<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'source_section_simulation_week_id', 'target_section_simulation_week_id', 'cohort_decision_aggregate_id', 'cohort_response_function_id', 'effect_key', 'effect_version', 'effect_snapshot', 'revealed_at', 'applied_at'])]
class CohortFeedbackEffect extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (CohortFeedbackEffect $effect): void {
            $aggregate = CohortDecisionAggregate::query()->findOrFail($effect->cohort_decision_aggregate_id);

            if ($aggregate->tenant_id !== $effect->tenant_id || $aggregate->section_simulation_id !== $effect->section_simulation_id || $aggregate->source_section_simulation_week_id !== $effect->source_section_simulation_week_id || $aggregate->target_section_simulation_week_id !== $effect->target_section_simulation_week_id) {
                throw new InvalidArgumentException('Cohort feedback effect context must match aggregate.');
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Cohort feedback effects are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Cohort feedback effects are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'effect_snapshot' => 'array',
            'revealed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CohortDecisionAggregate, $this>
     */
    public function aggregate(): BelongsTo
    {
        return $this->belongsTo(CohortDecisionAggregate::class, 'cohort_decision_aggregate_id');
    }
}
