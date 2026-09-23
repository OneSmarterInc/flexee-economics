<?php

namespace App\Models;

use App\Enums\StandingValue;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'standing_state_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'counterparty_id', 'old_state', 'new_state', 'reason', 'trigger_type', 'trigger_id', 'occurred_at'])]
class StandingEvent extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (StandingEvent $event): void {
            $standing = StandingState::query()->findOrFail($event->standing_state_id);

            if ($standing->tenant_id !== $event->tenant_id || $standing->team_simulation_id !== $event->team_simulation_id || $standing->counterparty_id !== $event->counterparty_id) {
                throw new InvalidArgumentException('Standing event context must match standing state.');
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Standing event history is immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Standing event history is immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'old_state' => StandingValue::class,
            'new_state' => StandingValue::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function oldStateEnum(): ?StandingValue
    {
        $state = $this->getAttribute('old_state');

        if ($state === null) {
            return null;
        }

        if ($state instanceof StandingValue) {
            return $state;
        }

        return StandingValue::from((string) $state);
    }

    public function newStateEnum(): StandingValue
    {
        $state = $this->getAttribute('new_state');

        if ($state instanceof StandingValue) {
            return $state;
        }

        return StandingValue::from((string) $state);
    }

    public function occurredAtIso(): ?string
    {
        $occurredAt = $this->getAttribute('occurred_at');

        if ($occurredAt === null) {
            return null;
        }

        if ($occurredAt instanceof CarbonInterface) {
            return $occurredAt->toISOString();
        }

        return Carbon::parse((string) $occurredAt)->toISOString();
    }

    /**
     * @return BelongsTo<StandingState, $this>
     */
    public function standingState(): BelongsTo
    {
        return $this->belongsTo(StandingState::class);
    }

    /**
     * @return BelongsTo<Counterparty, $this>
     */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }
}
