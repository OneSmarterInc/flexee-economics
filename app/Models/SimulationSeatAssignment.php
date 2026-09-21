<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\SimulationSeatAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'team_simulation_id', 'team_id', 'user_id', 'seat_id'])]
class SimulationSeatAssignment extends Model
{
    /** @use HasFactory<SimulationSeatAssignmentFactory> */
    use BelongsToTenant, HasFactory, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (SimulationSeatAssignment $assignment): void {
            $teamSimulation = TeamSimulation::query()->findOrFail($assignment->team_simulation_id);
            $membershipExists = TeamMember::query()
                ->where('tenant_id', $assignment->tenant_id)
                ->where('team_id', $assignment->team_id)
                ->where('user_id', $assignment->user_id)
                ->exists();

            if ($teamSimulation->tenant_id !== $assignment->tenant_id || $teamSimulation->team_id !== $assignment->team_id) {
                throw new InvalidArgumentException('Seat assignment must belong to the selected team simulation.');
            }

            if (! $membershipExists) {
                throw new InvalidArgumentException('Seat assignment user must be a member of the team simulation team.');
            }
        });
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
