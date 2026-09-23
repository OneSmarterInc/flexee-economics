<?php

namespace App\Models;

use App\Enums\RankingScope;
use App\Enums\RankingSnapshotStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'scope', 'status', 'composite_score', 'rank', 'ranking_version', 'input_snapshot', 'incomplete_reason', 'calculated_at'])]
class RankingSnapshot extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (RankingSnapshot $snapshot): void {
            $runtimeWeek = SectionSimulationWeek::query()->findOrFail($snapshot->section_simulation_week_id);
            $teamSimulation = TeamSimulation::query()->findOrFail($snapshot->team_simulation_id);

            if ($runtimeWeek->tenant_id !== $snapshot->tenant_id || $teamSimulation->tenant_id !== $snapshot->tenant_id) {
                throw new InvalidArgumentException('Ranking snapshot tenant must match runtime week and team simulation.');
            }

            if ($runtimeWeek->section_simulation_id !== $snapshot->section_simulation_id || $teamSimulation->section_simulation_id !== $snapshot->section_simulation_id) {
                throw new InvalidArgumentException('Ranking snapshot must belong to one section simulation.');
            }

            if ($teamSimulation->team_id !== $snapshot->team_id) {
                throw new InvalidArgumentException('Ranking snapshot team context is inconsistent.');
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Ranking snapshots are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Ranking snapshots are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'scope' => RankingScope::class,
            'status' => RankingSnapshotStatus::class,
            'composite_score' => 'decimal:6',
            'input_snapshot' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function scopeEnum(): RankingScope
    {
        $scope = $this->getAttribute('scope');

        if ($scope instanceof RankingScope) {
            return $scope;
        }

        return RankingScope::from((string) $scope);
    }

    public function statusEnum(): RankingSnapshotStatus
    {
        $status = $this->getAttribute('status');

        if ($status instanceof RankingSnapshotStatus) {
            return $status;
        }

        return RankingSnapshotStatus::from((string) $status);
    }

    /**
     * @return BelongsTo<SectionSimulationWeek, $this>
     */
    public function runtimeWeek(): BelongsTo
    {
        return $this->belongsTo(SectionSimulationWeek::class, 'section_simulation_week_id');
    }

    /**
     * @return BelongsTo<TeamSimulation, $this>
     */
    public function teamSimulation(): BelongsTo
    {
        return $this->belongsTo(TeamSimulation::class);
    }
}
