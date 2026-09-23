<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'team_simulation_id', 'team_id', 'source_section_simulation_week_id', 'target_section_simulation_week_id', 'consequence_definition_id', 'definition_key', 'definition_version', 'source_type', 'source_id', 'target_type', 'target_id', 'effect_type', 'explanation', 'metadata', 'created_by_user_id', 'occurred_at'])]
class ConsequenceLink extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (ConsequenceLink $link): void {
            $teamSimulation = TeamSimulation::query()->findOrFail($link->team_simulation_id);

            if ($teamSimulation->tenant_id !== $link->tenant_id) {
                throw new InvalidArgumentException('Consequence link tenant must match team simulation.');
            }

            if ($teamSimulation->section_simulation_id !== $link->section_simulation_id || $teamSimulation->team_id !== $link->team_id) {
                throw new InvalidArgumentException('Consequence link team context is inconsistent.');
            }

            self::validateRuntimeWeekContext($link, $link->source_section_simulation_week_id);
            self::validateRuntimeWeekContext($link, $link->target_section_simulation_week_id);
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Consequence links are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Consequence links are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    private static function validateRuntimeWeekContext(ConsequenceLink $link, ?int $runtimeWeekId): void
    {
        if ($runtimeWeekId === null) {
            return;
        }

        $runtimeWeek = SectionSimulationWeek::query()->whereKey($runtimeWeekId)->firstOrFail();

        if ($runtimeWeek->tenant_id !== $link->tenant_id || $runtimeWeek->section_simulation_id !== $link->section_simulation_id) {
            throw new InvalidArgumentException('Consequence link runtime weeks must match team simulation.');
        }
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
     * @return BelongsTo<ConsequenceDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(ConsequenceDefinition::class, 'consequence_definition_id');
    }

    /**
     * @return BelongsTo<TeamSimulation, $this>
     */
    public function teamSimulation(): BelongsTo
    {
        return $this->belongsTo(TeamSimulation::class);
    }
}
