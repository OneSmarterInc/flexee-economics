<?php

namespace App\Domain\Standing;

use App\Enums\StandingValue;
use App\Models\Counterparty;
use App\Models\SectionSimulationWeek;
use App\Models\StandingEvent;
use App\Models\StandingState;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class StandingService
{
    public function __construct(
        private readonly CounterpartyCatalog $catalog,
    ) {}

    /**
     * @return list<StandingState>
     */
    public function initializeTeamSimulation(
        TeamSimulation $teamSimulation,
        StandingValue $initialState = StandingValue::Watchful,
        string $reason = 'Initial relationship baseline.',
    ): array {
        return DB::transaction(function () use ($teamSimulation, $initialState, $reason): array {
            $states = [];

            foreach ($this->catalog->ensureHaldenCounterparties() as $counterparty) {
                $standing = StandingState::query()->firstOrCreate(
                    [
                        'tenant_id' => $teamSimulation->tenant_id,
                        'team_simulation_id' => $teamSimulation->id,
                        'counterparty_id' => $counterparty->id,
                    ],
                    [
                        'section_simulation_id' => $teamSimulation->section_simulation_id,
                        'team_id' => $teamSimulation->team_id,
                        'state' => $initialState->value,
                        'reason' => $reason,
                        'state_changed_at' => Carbon::now(),
                    ],
                );

                if (! $standing->events()->exists()) {
                    $this->recordEvent(
                        standing: $standing,
                        oldState: null,
                        newState: $standing->stateEnum(),
                        reason: $standing->reason,
                        runtimeWeek: null,
                        trigger: null,
                    );
                }

                $states[] = $standing;
            }

            return $states;
        });
    }

    public function applyChange(
        TeamSimulation $teamSimulation,
        Counterparty $counterparty,
        StandingValue $newState,
        string $reason,
        ?SectionSimulationWeek $runtimeWeek = null,
        ?Model $trigger = null,
    ): StandingState {
        if ($runtimeWeek !== null && ($runtimeWeek->tenant_id !== $teamSimulation->tenant_id || $runtimeWeek->section_simulation_id !== $teamSimulation->section_simulation_id)) {
            throw new InvalidArgumentException('Standing runtime week must match team simulation.');
        }

        return DB::transaction(function () use ($teamSimulation, $counterparty, $newState, $reason, $runtimeWeek, $trigger): StandingState {
            $standing = StandingState::query()
                ->where('tenant_id', $teamSimulation->tenant_id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->where('counterparty_id', $counterparty->id)
                ->lockForUpdate()
                ->first();

            if (! $standing) {
                $standing = StandingState::query()->create([
                    'tenant_id' => $teamSimulation->tenant_id,
                    'section_simulation_id' => $teamSimulation->section_simulation_id,
                    'team_simulation_id' => $teamSimulation->id,
                    'team_id' => $teamSimulation->team_id,
                    'counterparty_id' => $counterparty->id,
                    'state' => $newState->value,
                    'reason' => $reason,
                    'state_changed_at' => Carbon::now(),
                ]);

                $this->recordEvent($standing, null, $newState, $reason, $runtimeWeek, $trigger);

                return $standing->refresh();
            }

            $oldState = $standing->stateEnum();
            $standing->fill([
                'state' => $newState->value,
                'reason' => $reason,
                'state_changed_at' => Carbon::now(),
            ]);
            $standing->save();
            $this->recordEvent($standing, $oldState, $newState, $reason, $runtimeWeek, $trigger);

            return $standing->refresh();
        });
    }

    /**
     * @return array{state: string, reason: string, history: list<array{old_state: string|null, new_state: string, reason: string, week_id: int|null, trigger_type: string|null, trigger_id: int|null, occurred_at: string|null}>}
     */
    public function studentView(StandingState $standing): array
    {
        $standing->loadMissing(['events' => fn ($query) => $query->orderBy('occurred_at')->orderBy('id')]);
        $history = [];

        foreach ($standing->events as $event) {
            $history[] = [
                'old_state' => $event->oldStateEnum()?->value,
                'new_state' => $event->newStateEnum()->value,
                'reason' => $event->reason,
                'week_id' => $event->section_simulation_week_id,
                'trigger_type' => $event->trigger_type,
                'trigger_id' => $event->trigger_id,
                'occurred_at' => $event->occurredAtIso(),
            ];
        }

        return [
            'state' => $standing->stateEnum()->value,
            'reason' => $standing->reason,
            'history' => $history,
        ];
    }

    public function assertCanView(User $actor, StandingState $standing): void
    {
        if ($actor->tenant_id !== $standing->tenant_id) {
            throw new InvalidArgumentException('Actor cannot access standing for another tenant.');
        }

        if ($actor->isAdministrator()) {
            return;
        }

        if ($actor->isFaculty()) {
            $assigned = $actor->facultySections()
                ->wherePivot('tenant_id', $standing->tenant_id)
                ->whereHas('sectionSimulations', fn ($query) => $query->whereKey($standing->section_simulation_id))
                ->exists();

            if ($assigned) {
                return;
            }
        }

        if ($actor->isStudent()) {
            $member = TeamMember::query()
                ->where('tenant_id', $standing->tenant_id)
                ->where('team_id', $standing->team_id)
                ->where('user_id', $actor->id)
                ->exists();

            if ($member) {
                return;
            }
        }

        throw new InvalidArgumentException('Actor cannot access this team standing state.');
    }

    private function recordEvent(
        StandingState $standing,
        ?StandingValue $oldState,
        StandingValue $newState,
        string $reason,
        ?SectionSimulationWeek $runtimeWeek,
        ?Model $trigger,
    ): StandingEvent {
        return StandingEvent::query()->create([
            'tenant_id' => $standing->tenant_id,
            'standing_state_id' => $standing->id,
            'section_simulation_id' => $standing->section_simulation_id,
            'section_simulation_week_id' => $runtimeWeek?->id,
            'team_simulation_id' => $standing->team_simulation_id,
            'team_id' => $standing->team_id,
            'counterparty_id' => $standing->counterparty_id,
            'old_state' => $oldState?->value,
            'new_state' => $newState->value,
            'reason' => $reason,
            'trigger_type' => $trigger?->getMorphClass(),
            'trigger_id' => $trigger?->getKey(),
            'occurred_at' => Carbon::now(),
        ]);
    }
}
