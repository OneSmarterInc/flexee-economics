<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Carbon\CarbonInterface;
use Database\Factories\SimulationSeatAssignmentPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'team_simulation_id', 'team_id', 'user_id', 'seat_id', 'role_phase', 'effective_from_week_number', 'effective_until_week_number', 'effective_from', 'effective_until', 'source', 'metadata'])]
class SimulationSeatAssignmentPeriod extends Model
{
    /** @use HasFactory<SimulationSeatAssignmentPeriodFactory> */
    use BelongsToTenant, HasFactory, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (SimulationSeatAssignmentPeriod $period): void {
            $teamSimulation = TeamSimulation::query()->findOrFail($period->team_simulation_id);
            $membershipExists = TeamMember::query()
                ->where('tenant_id', $period->tenant_id)
                ->where('team_id', $period->team_id)
                ->where('user_id', $period->user_id)
                ->exists();

            if ($teamSimulation->tenant_id !== $period->tenant_id || $teamSimulation->team_id !== $period->team_id) {
                throw new InvalidArgumentException('Seat assignment period must belong to the selected team simulation.');
            }

            if (! $membershipExists) {
                throw new InvalidArgumentException('Seat assignment period user must be a member of the team simulation team.');
            }

            $fromWeek = $period->effective_from_week_number;
            $untilWeek = $period->effective_until_week_number;
            if ($fromWeek !== null && $untilWeek !== null && $untilWeek < $fromWeek) {
                throw new InvalidArgumentException('Seat assignment period cannot end before it starts.');
            }

            $from = $period->getAttribute('effective_from');
            $until = $period->getAttribute('effective_until');
            if ($from instanceof CarbonInterface && $until instanceof CarbonInterface && $until->lessThan($from)) {
                throw new InvalidArgumentException('Seat assignment period timestamp range cannot end before it starts.');
            }

            if ($period->exists && $period->isDirty([
                'tenant_id',
                'team_simulation_id',
                'team_id',
                'user_id',
                'seat_id',
                'role_phase',
                'effective_from_week_number',
                'effective_until_week_number',
                'effective_from',
                'effective_until',
            ])) {
                throw new InvalidArgumentException('Historical seat assignment periods cannot be retimed or reassigned after creation.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function coversWeek(int $weekNumber): bool
    {
        $from = $this->effective_from_week_number;
        $until = $this->effective_until_week_number;

        return ($from === null || $from <= $weekNumber)
            && ($until === null || $until >= $weekNumber);
    }

    /**
     * @return BelongsTo<TeamSimulation, $this>
     */
    public function teamSimulation(): BelongsTo
    {
        return $this->belongsTo(TeamSimulation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Seat, $this>
     */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }
}
