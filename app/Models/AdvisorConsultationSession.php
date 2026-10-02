<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'advisor_id', 'question', 'context_snapshot', 'requested_by_user_id', 'requested_at'])]
class AdvisorConsultationSession extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (AdvisorConsultationSession $session): void {
            $runtimeWeek = SectionSimulationWeek::query()->findOrFail($session->section_simulation_week_id);
            $teamSimulation = TeamSimulation::query()->findOrFail($session->team_simulation_id);

            if ($runtimeWeek->tenant_id !== $session->tenant_id || $teamSimulation->tenant_id !== $session->tenant_id) {
                throw new InvalidArgumentException('Advisor consultation tenant must match runtime week and team simulation.');
            }

            if ($runtimeWeek->section_simulation_id !== $session->section_simulation_id || $teamSimulation->section_simulation_id !== $session->section_simulation_id) {
                throw new InvalidArgumentException('Advisor consultation must belong to one section simulation.');
            }

            if ($teamSimulation->team_id !== $session->team_id) {
                throw new InvalidArgumentException('Advisor consultation team context is inconsistent.');
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Advisor consultation sessions are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Advisor consultation sessions are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'context_snapshot' => 'array',
            'requested_at' => 'datetime',
        ];
    }

    public function requestedAtIso(): ?string
    {
        $requestedAt = $this->getAttribute('requested_at');

        if ($requestedAt === null) {
            return null;
        }

        if ($requestedAt instanceof CarbonInterface) {
            return $requestedAt->toISOString();
        }

        return Carbon::parse((string) $requestedAt)->toISOString();
    }

    /**
     * @return BelongsTo<Advisor, $this>
     */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Advisor::class);
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
     * @return HasOne<AdvisorResponse, $this>
     */
    public function response(): HasOne
    {
        return $this->hasOne(AdvisorResponse::class);
    }
}
