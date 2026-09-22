<?php

namespace App\Models;

use App\Enums\SectionSimulationWeekStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\SectionSimulationWeekFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'simulation_version_id', 'simulation_week_id', 'status', 'scheduled_at', 'released_at', 'opened_at', 'closes_at', 'closed_at', 'published_at', 'metadata'])]
class SectionSimulationWeek extends Model
{
    /** @use HasFactory<SectionSimulationWeekFactory> */
    use BelongsToTenant, HasFactory, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (SectionSimulationWeek $runtimeWeek): void {
            $sectionSimulation = SectionSimulation::query()->findOrFail($runtimeWeek->section_simulation_id);
            $simulationWeek = SimulationWeek::query()->findOrFail($runtimeWeek->simulation_week_id);

            if ($sectionSimulation->tenant_id !== $runtimeWeek->tenant_id) {
                throw new InvalidArgumentException('Runtime week tenant must match its section simulation.');
            }

            if ($sectionSimulation->simulation_version_id !== $runtimeWeek->simulation_version_id || $simulationWeek->simulation_version_id !== $runtimeWeek->simulation_version_id) {
                throw new InvalidArgumentException('Runtime week must belong to the section simulation version.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'released_at' => 'datetime',
            'opened_at' => 'datetime',
            'closes_at' => 'datetime',
            'closed_at' => 'datetime',
            'published_at' => 'datetime',
            'metadata' => 'array',
            'status' => SectionSimulationWeekStatus::class,
        ];
    }

    public function statusEnum(): SectionSimulationWeekStatus
    {
        $status = $this->getAttribute('status');

        if ($status instanceof SectionSimulationWeekStatus) {
            return $status;
        }

        return SectionSimulationWeekStatus::from((string) $status);
    }

    public function statusValue(): string
    {
        return $this->statusEnum()->value;
    }

    /**
     * @return BelongsTo<SectionSimulation, $this>
     */
    public function sectionSimulation(): BelongsTo
    {
        return $this->belongsTo(SectionSimulation::class);
    }

    /**
     * @return BelongsTo<SimulationWeek, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(SimulationWeek::class, 'simulation_week_id');
    }

    /**
     * @return HasMany<DecisionSubmission, $this>
     */
    public function decisionSubmissions(): HasMany
    {
        return $this->hasMany(DecisionSubmission::class);
    }

    /**
     * @return HasMany<MemoSubmission, $this>
     */
    public function memoSubmissions(): HasMany
    {
        return $this->hasMany(MemoSubmission::class);
    }

    /**
     * @return HasMany<EconomicResolution, $this>
     */
    public function economicResolutions(): HasMany
    {
        return $this->hasMany(EconomicResolution::class);
    }
}
