<?php

namespace App\Domain\Assignments;

use App\Models\Seat;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationSeatAssignment;
use App\Models\SimulationSeatAssignmentPeriod;
use App\Models\TeamSimulation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class EffectiveSeatAssignmentService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createPeriod(
        TeamSimulation $teamSimulation,
        User $user,
        Seat $seat,
        ?int $effectiveFromWeekNumber,
        ?int $effectiveUntilWeekNumber = null,
        ?string $rolePhase = null,
        ?CarbonInterface $effectiveFrom = null,
        ?CarbonInterface $effectiveUntil = null,
        string $source = 'manual',
        array $metadata = [],
    ): SimulationSeatAssignmentPeriod {
        if ($user->tenant_id !== $teamSimulation->tenant_id) {
            throw new InvalidArgumentException('Seat assignment user must belong to the team simulation tenant.');
        }

        if ($effectiveFromWeekNumber !== null && $effectiveUntilWeekNumber !== null && $effectiveUntilWeekNumber < $effectiveFromWeekNumber) {
            throw new InvalidArgumentException('Seat assignment period cannot end before it starts.');
        }

        return SimulationSeatAssignmentPeriod::query()->create([
            'tenant_id' => $teamSimulation->tenant_id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'user_id' => $user->id,
            'seat_id' => $seat->id,
            'role_phase' => $rolePhase,
            'effective_from_week_number' => $effectiveFromWeekNumber,
            'effective_until_week_number' => $effectiveUntilWeekNumber,
            'effective_from' => $effectiveFrom,
            'effective_until' => $effectiveUntil,
            'source' => $source,
            'metadata' => $metadata,
        ]);
    }

    public function assignmentAt(
        TeamSimulation $teamSimulation,
        User $user,
        ?SectionSimulationWeek $runtimeWeek = null,
        ?CarbonInterface $at = null,
    ): ?SimulationSeatAssignmentPeriod {
        $weekNumber = $this->weekNumber($runtimeWeek);

        $query = SimulationSeatAssignmentPeriod::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('user_id', $user->id)
            ->with(['seat', 'user'])
            ->orderByDesc('effective_from_week_number')
            ->orderByDesc('effective_from')
            ->orderByDesc('id');

        if ($weekNumber !== null) {
            $query
                ->where(function ($inner) use ($weekNumber): void {
                    $inner->whereNull('effective_from_week_number')
                        ->orWhere('effective_from_week_number', '<=', $weekNumber);
                })
                ->where(function ($inner) use ($weekNumber): void {
                    $inner->whereNull('effective_until_week_number')
                        ->orWhere('effective_until_week_number', '>=', $weekNumber);
                });
        }

        if ($at instanceof CarbonInterface) {
            $query
                ->where(function ($inner) use ($at): void {
                    $inner->whereNull('effective_from')
                        ->orWhere('effective_from', '<=', $at);
                })
                ->where(function ($inner) use ($at): void {
                    $inner->whereNull('effective_until')
                        ->orWhere('effective_until', '>=', $at);
                });
        }

        return $query->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function contextFor(
        TeamSimulation $teamSimulation,
        User $user,
        ?SectionSimulationWeek $runtimeWeek = null,
        ?CarbonInterface $at = null,
    ): ?array {
        $period = $this->assignmentAt($teamSimulation, $user, $runtimeWeek, $at);
        $weekNumber = $this->weekNumber($runtimeWeek);

        if ($period instanceof SimulationSeatAssignmentPeriod) {
            $period->loadMissing(['seat', 'user']);

            return [
                'resolved_from' => 'effective_period',
                'assignment_period_id' => $period->id,
                'assignment_id' => null,
                'team_simulation_id' => $teamSimulation->id,
                'team_id' => $teamSimulation->team_id,
                'user_id' => $user->id,
                'user_name' => $period->user->name,
                'seat_id' => $period->seat_id,
                'seat_code' => $period->seat?->code,
                'seat_name' => $period->seat?->name,
                'role_phase' => $period->role_phase,
                'runtime_week_number' => $weekNumber,
                'effective_from_week_number' => $period->effective_from_week_number,
                'effective_until_week_number' => $period->effective_until_week_number,
                'effective_from' => $this->dateIso($period->getAttribute('effective_from')),
                'effective_until' => $this->dateIso($period->getAttribute('effective_until')),
                'source' => $period->source,
            ];
        }

        $assignment = SimulationSeatAssignment::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('user_id', $user->id)
            ->with('seat')
            ->first();

        if (! $assignment instanceof SimulationSeatAssignment) {
            return null;
        }

        return [
            'resolved_from' => 'current_assignment_fallback',
            'assignment_period_id' => null,
            'assignment_id' => $assignment->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'seat_id' => $assignment->seat_id,
            'seat_code' => $assignment->seat?->code,
            'seat_name' => $assignment->seat?->name,
            'role_phase' => $this->phaseFor($runtimeWeek),
            'runtime_week_number' => $weekNumber,
            'effective_from_week_number' => null,
            'effective_until_week_number' => null,
            'effective_from' => null,
            'effective_until' => null,
            'source' => 'current_assignment',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function teamHistory(TeamSimulation $teamSimulation): array
    {
        $periods = SimulationSeatAssignmentPeriod::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->with(['seat', 'user'])
            ->orderBy('user_id')
            ->orderBy('effective_from_week_number')
            ->orderBy('id')
            ->get();

        if ($periods->isNotEmpty()) {
            return array_values($periods
                ->map(fn (SimulationSeatAssignmentPeriod $period): array => [
                    'assignment_period_id' => $period->id,
                    'user_id' => $period->user_id,
                    'user_name' => $period->user?->name,
                    'seat_id' => $period->seat_id,
                    'seat_code' => $period->seat?->code,
                    'seat_name' => $period->seat?->name,
                    'role_phase' => $period->role_phase,
                    'effective_from_week_number' => $period->effective_from_week_number,
                    'effective_until_week_number' => $period->effective_until_week_number,
                    'source' => $period->source,
                ])
                ->values()
                ->all());
        }

        return array_values(SimulationSeatAssignment::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->with(['seat', 'user'])
            ->orderBy('user_id')
            ->get()
            ->map(fn (SimulationSeatAssignment $assignment): array => [
                'assignment_period_id' => null,
                'assignment_id' => $assignment->id,
                'user_id' => $assignment->user_id,
                'user_name' => $assignment->user?->name,
                'seat_id' => $assignment->seat_id,
                'seat_code' => $assignment->seat?->code,
                'seat_name' => $assignment->seat?->name,
                'role_phase' => null,
                'effective_from_week_number' => null,
                'effective_until_week_number' => null,
                'source' => 'current_assignment_fallback',
            ])
            ->values()
            ->all());
    }

    private function weekNumber(?SectionSimulationWeek $runtimeWeek): ?int
    {
        $runtimeWeek?->loadMissing('definition');

        return $runtimeWeek?->definition?->week_number;
    }

    private function phaseFor(?SectionSimulationWeek $runtimeWeek): ?string
    {
        $weekNumber = $this->weekNumber($runtimeWeek);

        if ($weekNumber === null) {
            return null;
        }

        return $weekNumber <= 8 ? 'first_seat' : 'second_seat';
    }

    private function dateIso(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        if ($value === null) {
            return null;
        }

        return Carbon::parse((string) $value)->toISOString();
    }
}
