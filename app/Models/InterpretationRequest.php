<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;
use JsonException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'requested_by_user_id', 'focus', 'context_version', 'context_hash', 'prompt_version', 'context_snapshot', 'requested_at'])]
class InterpretationRequest extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (InterpretationRequest $request): void {
            $runtimeWeek = SectionSimulationWeek::query()->findOrFail($request->section_simulation_week_id);
            $teamSimulation = TeamSimulation::query()->findOrFail($request->team_simulation_id);
            $requester = User::query()->findOrFail($request->requested_by_user_id);

            if ($runtimeWeek->tenant_id !== $request->tenant_id || $teamSimulation->tenant_id !== $request->tenant_id || $requester->tenant_id !== $request->tenant_id) {
                throw new InvalidArgumentException('Interpretation request tenant context is inconsistent.');
            }

            if ($runtimeWeek->section_simulation_id !== $request->section_simulation_id || $teamSimulation->section_simulation_id !== $request->section_simulation_id) {
                throw new InvalidArgumentException('Interpretation request section simulation context is inconsistent.');
            }

            if ($teamSimulation->team_id !== $request->team_id) {
                throw new InvalidArgumentException('Interpretation request team context is inconsistent.');
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Interpretation requests are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Interpretation requests are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'context_snapshot' => 'array',
            'requested_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function contextSnapshot(): array
    {
        $snapshot = $this->getAttribute('context_snapshot');

        if (is_array($snapshot)) {
            return $snapshot;
        }

        if (is_string($snapshot)) {
            $decoded = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new InvalidArgumentException('Interpretation request context snapshot is unavailable.');
    }

    /**
     * @return BelongsTo<TeamSimulation, $this>
     */
    public function teamSimulation(): BelongsTo
    {
        return $this->belongsTo(TeamSimulation::class);
    }

    /**
     * @return BelongsTo<SectionSimulationWeek, $this>
     */
    public function runtimeWeek(): BelongsTo
    {
        return $this->belongsTo(SectionSimulationWeek::class, 'section_simulation_week_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return HasOne<InterpretationResult, $this>
     */
    public function result(): HasOne
    {
        return $this->hasOne(InterpretationResult::class);
    }
}
