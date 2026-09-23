<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'source_economic_resolution_id', 'requested_by_user_id', 'scenario_type', 'counterfactual', 'scenario_inputs', 'calculated_outputs', 'engine_identifier', 'engine_version', 'requested_at'])]
class WhatIfRun extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (WhatIfRun $run): void {
            $source = EconomicResolution::query()->findOrFail($run->source_economic_resolution_id);

            if ($source->tenant_id !== $run->tenant_id || $source->section_simulation_week_id !== $run->section_simulation_week_id || $source->team_simulation_id !== $run->team_simulation_id) {
                throw new InvalidArgumentException('What-if run source resolution context is inconsistent.');
            }

            if ($source->section_simulation_id !== $run->section_simulation_id || $source->team_id !== $run->team_id) {
                throw new InvalidArgumentException('What-if run team context is inconsistent.');
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('What-if runs are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('What-if runs are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'counterfactual' => 'boolean',
            'scenario_inputs' => 'array',
            'calculated_outputs' => 'array',
            'requested_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EconomicResolution, $this>
     */
    public function sourceResolution(): BelongsTo
    {
        return $this->belongsTo(EconomicResolution::class, 'source_economic_resolution_id');
    }
}
