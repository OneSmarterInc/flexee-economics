<?php

namespace App\Models;

use App\Enums\StandingValue;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'team_simulation_id', 'team_id', 'counterparty_id', 'state', 'reason', 'state_changed_at'])]
class StandingState extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (StandingState $standing): void {
            $teamSimulation = TeamSimulation::query()->findOrFail($standing->team_simulation_id);

            if ($teamSimulation->tenant_id !== $standing->tenant_id) {
                throw new InvalidArgumentException('Standing state tenant must match team simulation.');
            }

            if ($teamSimulation->section_simulation_id !== $standing->section_simulation_id || $teamSimulation->team_id !== $standing->team_id) {
                throw new InvalidArgumentException('Standing state context is inconsistent.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'state' => StandingValue::class,
            'state_changed_at' => 'datetime',
        ];
    }

    public function stateEnum(): StandingValue
    {
        $state = $this->getAttribute('state');

        if ($state instanceof StandingValue) {
            return $state;
        }

        return StandingValue::from((string) $state);
    }

    /**
     * @return BelongsTo<TeamSimulation, $this>
     */
    public function teamSimulation(): BelongsTo
    {
        return $this->belongsTo(TeamSimulation::class);
    }

    /**
     * @return BelongsTo<Counterparty, $this>
     */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    /**
     * @return HasMany<StandingEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(StandingEvent::class);
    }
}
