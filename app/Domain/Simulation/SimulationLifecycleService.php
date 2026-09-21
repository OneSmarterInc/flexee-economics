<?php

namespace App\Domain\Simulation;

use App\Enums\SectionSimulationStatus;
use App\Enums\SectionSimulationWeekStatus;
use App\Enums\SimulationVersionStatus;
use App\Enums\TeamSimulationStatus;
use App\Models\AuditEvent;
use App\Models\Section;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationVersion;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SimulationLifecycleService
{
    /**
     * @return array<string, list<SectionSimulationWeekStatus>>
     */
    public function validTransitions(): array
    {
        return [
            SectionSimulationWeekStatus::Draft->value => [
                SectionSimulationWeekStatus::Scheduled,
                SectionSimulationWeekStatus::Released,
            ],
            SectionSimulationWeekStatus::Scheduled->value => [
                SectionSimulationWeekStatus::Released,
            ],
            SectionSimulationWeekStatus::Released->value => [
                SectionSimulationWeekStatus::Open,
            ],
            SectionSimulationWeekStatus::Open->value => [
                SectionSimulationWeekStatus::Closed,
            ],
            SectionSimulationWeekStatus::Closed->value => [
                SectionSimulationWeekStatus::Published,
            ],
            SectionSimulationWeekStatus::Published->value => [],
        ];
    }

    public function assignToSection(Section $section, SimulationVersion $version, User $actor, ?string $name = null): SectionSimulation
    {
        if ($actor->tenant_id !== $section->tenant_id) {
            throw new InvalidArgumentException('Simulation assignment actor must belong to the section tenant.');
        }

        if ($version->status !== SimulationVersionStatus::Published) {
            throw new InvalidArgumentException('Only published simulation versions can be assigned to sections.');
        }

        return DB::transaction(function () use ($section, $version, $actor, $name): SectionSimulation {
            $version->loadMissing(['simulation', 'variant', 'weeks']);
            $section->loadMissing(['teams.members']);

            $sectionSimulation = SectionSimulation::query()->create([
                'tenant_id' => $section->tenant_id,
                'section_id' => $section->id,
                'simulation_id' => $version->simulation_id,
                'simulation_variant_id' => $version->simulation_variant_id,
                'simulation_version_id' => $version->id,
                'created_by_user_id' => $actor->id,
                'name' => $name ?? $version->simulation->name.' - '.$section->name,
                'status' => SectionSimulationStatus::Active,
                'metadata' => [
                    'assignment_source' => 'lifecycle_service',
                ],
            ]);

            foreach ($version->weeks()->orderBy('week_number')->get() as $week) {
                SectionSimulationWeek::query()->create([
                    'tenant_id' => $section->tenant_id,
                    'section_simulation_id' => $sectionSimulation->id,
                    'simulation_version_id' => $version->id,
                    'simulation_week_id' => $week->id,
                    'status' => SectionSimulationWeekStatus::Draft,
                ]);
            }

            foreach ($section->teams as $team) {
                $this->createTeamSimulation($sectionSimulation, $team);
            }

            $this->recordAudit(
                tenantId: $section->tenant_id,
                actor: $actor,
                action: 'section_simulation.assigned',
                auditable: $sectionSimulation,
                before: null,
                after: [
                    'status' => $sectionSimulation->status->value,
                    'section_id' => $section->id,
                    'simulation_version_id' => $version->id,
                ],
            );

            return $sectionSimulation;
        });
    }

    public function transitionWeek(
        SectionSimulationWeek $runtimeWeek,
        SectionSimulationWeekStatus $to,
        User $actor,
        ?CarbonInterface $closesAt = null,
    ): SectionSimulationWeek {
        if ($actor->tenant_id !== $runtimeWeek->tenant_id) {
            throw new InvalidArgumentException('Lifecycle actor must belong to the runtime week tenant.');
        }

        $from = $runtimeWeek->status;
        $allowed = $this->validTransitions()[$from->value] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new InvalidArgumentException("Invalid lifecycle transition from {$from->value} to {$to->value}.");
        }

        return DB::transaction(function () use ($runtimeWeek, $to, $actor, $from, $closesAt): SectionSimulationWeek {
            $now = Carbon::now();
            $runtimeWeek->status = $to;

            match ($to) {
                SectionSimulationWeekStatus::Scheduled => $runtimeWeek->scheduled_at = $now,
                SectionSimulationWeekStatus::Released => $runtimeWeek->released_at = $now,
                SectionSimulationWeekStatus::Open => $runtimeWeek->opened_at = $now,
                SectionSimulationWeekStatus::Closed => $runtimeWeek->closed_at = $now,
                SectionSimulationWeekStatus::Published => $runtimeWeek->published_at = $now,
                SectionSimulationWeekStatus::Draft => null,
            };

            if ($to === SectionSimulationWeekStatus::Open && $closesAt !== null) {
                $runtimeWeek->closes_at = $closesAt;
            }

            $runtimeWeek->save();
            $runtimeWeek->loadMissing('definition');

            $this->recordAudit(
                tenantId: $runtimeWeek->tenant_id,
                actor: $actor,
                action: 'section_simulation_week.'.$to->value,
                auditable: $runtimeWeek,
                before: ['status' => $from->value],
                after: ['status' => $to->value],
                metadata: [
                    'section_simulation_id' => $runtimeWeek->section_simulation_id,
                    'simulation_week_id' => $runtimeWeek->simulation_week_id,
                    'week_number' => $runtimeWeek->definition?->week_number,
                ],
            );

            return $runtimeWeek;
        });
    }

    private function createTeamSimulation(SectionSimulation $sectionSimulation, Team $team): TeamSimulation
    {
        $teamSimulation = TeamSimulation::query()->create([
            'tenant_id' => $sectionSimulation->tenant_id,
            'section_simulation_id' => $sectionSimulation->id,
            'section_id' => $sectionSimulation->section_id,
            'team_id' => $team->id,
            'status' => TeamSimulationStatus::Active,
        ]);

        TeamMember::query()
            ->where('tenant_id', $sectionSimulation->tenant_id)
            ->where('team_id', $team->id)
            ->whereNotNull('seat_id')
            ->get()
            ->each(function (TeamMember $member) use ($teamSimulation): void {
                $teamSimulation->seatAssignments()->create([
                    'tenant_id' => $teamSimulation->tenant_id,
                    'team_id' => $teamSimulation->team_id,
                    'user_id' => $member->user_id,
                    'seat_id' => $member->seat_id,
                ]);
            });

        return $teamSimulation;
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>|null  $metadata
     */
    private function recordAudit(
        int $tenantId,
        User $actor,
        string $action,
        object $auditable,
        ?array $before,
        ?array $after,
        ?array $metadata = null,
    ): void {
        AuditEvent::query()->create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->id,
            'before_state' => $before,
            'after_state' => $after,
            'metadata' => $metadata,
            'occurred_at' => Carbon::now(),
        ]);
    }
}
