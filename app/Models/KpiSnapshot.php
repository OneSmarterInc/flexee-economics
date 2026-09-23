<?php

namespace App\Models;

use App\Enums\KpiSnapshotStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'kpi_definition_id', 'economic_resolution_id', 'status', 'value', 'unit', 'precision', 'calculation_version', 'input_snapshot', 'unavailable_reason', 'calculated_at'])]
class KpiSnapshot extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (KpiSnapshot $snapshot): void {
            $runtimeWeek = SectionSimulationWeek::query()->findOrFail($snapshot->section_simulation_week_id);
            $teamSimulation = TeamSimulation::query()->findOrFail($snapshot->team_simulation_id);

            if ($runtimeWeek->tenant_id !== $snapshot->tenant_id || $teamSimulation->tenant_id !== $snapshot->tenant_id) {
                throw new InvalidArgumentException('KPI snapshot tenant must match runtime week and team simulation.');
            }

            if ($runtimeWeek->section_simulation_id !== $snapshot->section_simulation_id || $teamSimulation->section_simulation_id !== $snapshot->section_simulation_id) {
                throw new InvalidArgumentException('KPI snapshot must belong to one section simulation.');
            }

            if ($teamSimulation->team_id !== $snapshot->team_id) {
                throw new InvalidArgumentException('KPI snapshot team context is inconsistent.');
            }

            if ($snapshot->economic_resolution_id !== null) {
                $resolution = EconomicResolution::query()->findOrFail($snapshot->economic_resolution_id);

                if ($resolution->tenant_id !== $snapshot->tenant_id || $resolution->section_simulation_week_id !== $snapshot->section_simulation_week_id || $resolution->team_simulation_id !== $snapshot->team_simulation_id) {
                    throw new InvalidArgumentException('KPI snapshot economic resolution context is inconsistent.');
                }
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('KPI snapshots are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('KPI snapshots are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'status' => KpiSnapshotStatus::class,
            'value' => 'decimal:4',
            'input_snapshot' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function statusEnum(): KpiSnapshotStatus
    {
        $status = $this->getAttribute('status');

        if ($status instanceof KpiSnapshotStatus) {
            return $status;
        }

        return KpiSnapshotStatus::from((string) $status);
    }

    /**
     * @return BelongsTo<KpiDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(KpiDefinition::class, 'kpi_definition_id');
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

    /**
     * @return BelongsTo<EconomicResolution, $this>
     */
    public function economicResolution(): BelongsTo
    {
        return $this->belongsTo(EconomicResolution::class);
    }
}
