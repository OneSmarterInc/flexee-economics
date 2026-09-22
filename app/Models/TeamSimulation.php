<?php

namespace App\Models;

use App\Enums\TeamSimulationStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\TeamSimulationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_id', 'team_id', 'status', 'metadata'])]
class TeamSimulation extends Model
{
    /** @use HasFactory<TeamSimulationFactory> */
    use BelongsToTenant, HasFactory, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (TeamSimulation $teamSimulation): void {
            $sectionSimulation = SectionSimulation::query()->findOrFail($teamSimulation->section_simulation_id);
            $team = Team::query()->findOrFail($teamSimulation->team_id);

            if ($sectionSimulation->tenant_id !== $teamSimulation->tenant_id || $team->tenant_id !== $teamSimulation->tenant_id) {
                throw new InvalidArgumentException('Team simulation tenant must match section simulation and team.');
            }

            if ($sectionSimulation->section_id !== $teamSimulation->section_id || $team->section_id !== $teamSimulation->section_id) {
                throw new InvalidArgumentException('Team simulation must use a team from the section simulation section.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'status' => TeamSimulationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<SectionSimulation, $this>
     */
    public function sectionSimulation(): BelongsTo
    {
        return $this->belongsTo(SectionSimulation::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<SimulationSeatAssignment, $this>
     */
    public function seatAssignments(): HasMany
    {
        return $this->hasMany(SimulationSeatAssignment::class);
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
