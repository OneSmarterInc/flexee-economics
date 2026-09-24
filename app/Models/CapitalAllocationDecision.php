<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'discount_rate_consequence_id', 'submitted_by_user_id', 'selected_projects', 'rejected_projects', 'context_snapshot', 'memo_references', 'submitted_at'])]
class CapitalAllocationDecision extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (CapitalAllocationDecision $decision): void {
            $runtimeWeek = SectionSimulationWeek::query()->findOrFail($decision->section_simulation_week_id);
            $teamSimulation = TeamSimulation::query()->findOrFail($decision->team_simulation_id);
            $submitter = User::query()->findOrFail($decision->submitted_by_user_id);

            if ($runtimeWeek->tenant_id !== $decision->tenant_id || $teamSimulation->tenant_id !== $decision->tenant_id || $submitter->tenant_id !== $decision->tenant_id) {
                throw new InvalidArgumentException('Capital allocation decision tenant context is inconsistent.');
            }

            if ($runtimeWeek->section_simulation_id !== $decision->section_simulation_id || $teamSimulation->section_simulation_id !== $decision->section_simulation_id) {
                throw new InvalidArgumentException('Capital allocation decision section simulation context is inconsistent.');
            }

            if ($teamSimulation->team_id !== $decision->team_id) {
                throw new InvalidArgumentException('Capital allocation decision team context is inconsistent.');
            }

            if ($decision->discount_rate_consequence_id !== null) {
                $consequence = DiscountRateConsequence::query()->findOrFail($decision->discount_rate_consequence_id);

                if ($consequence->tenant_id !== $decision->tenant_id || $consequence->team_simulation_id !== $decision->team_simulation_id || $consequence->target_section_simulation_week_id !== $decision->section_simulation_week_id) {
                    throw new InvalidArgumentException('Capital allocation discount-rate context is inconsistent.');
                }
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Capital allocation decisions are immutable once submitted.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Capital allocation decisions are immutable once submitted.');
        });
    }

    protected function casts(): array
    {
        return [
            'selected_projects' => 'array',
            'rejected_projects' => 'array',
            'context_snapshot' => 'array',
            'memo_references' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function selectedProjectSnapshots(): array
    {
        return $this->projectSnapshotList('selected_projects');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rejectedProjectSnapshots(): array
    {
        return $this->projectSnapshotList('rejected_projects');
    }

    /**
     * @return array<string, mixed>
     */
    public function contextSnapshot(): array
    {
        $value = $this->getAttribute('context_snapshot');

        if (is_array($value) && ! array_is_list($value)) {
            return $value;
        }

        throw new InvalidArgumentException('Capital allocation context snapshot is unavailable.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function projectSnapshotList(string $key): array
    {
        $value = $this->getAttribute($key);

        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException("Capital allocation project snapshot [{$key}] is unavailable.");
        }

        $snapshots = [];

        foreach ($value as $snapshot) {
            if (! is_array($snapshot)) {
                throw new InvalidArgumentException("Capital allocation project snapshot [{$key}] contains an invalid entry.");
            }

            $snapshots[] = $snapshot;
        }

        return $snapshots;
    }

    /**
     * @return BelongsTo<DiscountRateConsequence, $this>
     */
    public function discountRateConsequence(): BelongsTo
    {
        return $this->belongsTo(DiscountRateConsequence::class);
    }

    /**
     * @return BelongsTo<SectionSimulationWeek, $this>
     */
    public function runtimeWeek(): BelongsTo
    {
        return $this->belongsTo(SectionSimulationWeek::class, 'section_simulation_week_id');
    }

    /**
     * @return HasOne<CapitalAllocationEvaluation, $this>
     */
    public function evaluation(): HasOne
    {
        return $this->hasOne(CapitalAllocationEvaluation::class);
    }
}
