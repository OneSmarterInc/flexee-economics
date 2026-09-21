<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['tenant_id', 'team_id', 'user_id', 'seat_id'])]
class TeamMember extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::saving(function (TeamMember $membership): void {
            $team = Team::query()->findOrFail($membership->team_id);
            $user = User::query()->findOrFail($membership->user_id);

            if ($team->tenant_id !== $membership->tenant_id || $user->tenant_id !== $membership->tenant_id) {
                throw new InvalidArgumentException('Team membership tenant does not match team and user.');
            }

            $isEnrolledInSection = Enrollment::query()
                ->where('tenant_id', $membership->tenant_id)
                ->where('section_id', $team->section_id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if (! $isEnrolledInSection) {
                throw new InvalidArgumentException('Team member must be actively enrolled in the team section.');
            }
        });
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
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
