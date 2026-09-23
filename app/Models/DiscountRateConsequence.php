<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'source_section_simulation_week_id', 'target_section_simulation_week_id', 'team_simulation_id', 'team_id', 'economic_resolution_id', 'discount_rate_schedule_id', 'schedule_key', 'schedule_version', 'status', 'classification', 'discount_rate_percent', 'capital_envelope_musd', 'input_snapshot', 'result_snapshot', 'resolved_by_user_id', 'resolved_at'])]
class DiscountRateConsequence extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_UNRESOLVED = 'unresolved';

    protected static function booted(): void
    {
        static::saving(function (DiscountRateConsequence $consequence): void {
            $resolution = EconomicResolution::query()->findOrFail($consequence->economic_resolution_id);

            if ($resolution->tenant_id !== $consequence->tenant_id || $resolution->section_simulation_week_id !== $consequence->source_section_simulation_week_id || $resolution->team_simulation_id !== $consequence->team_simulation_id) {
                throw new InvalidArgumentException('Discount rate consequence source resolution context is inconsistent.');
            }

            $targetWeek = SectionSimulationWeek::query()->findOrFail($consequence->target_section_simulation_week_id);

            if ($targetWeek->tenant_id !== $consequence->tenant_id || $targetWeek->section_simulation_id !== $consequence->section_simulation_id) {
                throw new InvalidArgumentException('Discount rate consequence target week context is inconsistent.');
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Discount rate consequences are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Discount rate consequences are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'discount_rate_percent' => 'decimal:3',
            'capital_envelope_musd' => 'decimal:3',
            'input_snapshot' => 'array',
            'result_snapshot' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EconomicResolution, $this>
     */
    public function economicResolution(): BelongsTo
    {
        return $this->belongsTo(EconomicResolution::class);
    }

    /**
     * @return BelongsTo<DiscountRateSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DiscountRateSchedule::class, 'discount_rate_schedule_id');
    }
}
