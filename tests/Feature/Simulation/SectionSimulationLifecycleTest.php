<?php

namespace Tests\Feature\Simulation;

use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\AuditEvent;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\TeamSimulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class SectionSimulationLifecycleTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_assigning_a_published_version_creates_runtime_weeks_teams_seat_assignments_and_audit(): void
    {
        $graph = $this->tenantGraph('A');
        $structure = $this->simulationStructure(4);
        $seat = \App\Models\Seat::factory()->create(['code' => 'evp-test']);
        \App\Models\TeamMember::query()
            ->where('tenant_id', $graph['tenant']->id)
            ->where('team_id', $graph['team']->id)
            ->where('user_id', $graph['student']->id)
            ->update(['seat_id' => $seat->id]);

        $sectionSimulation = app(SimulationLifecycleService::class)
            ->assignToSection($graph['section'], $structure['version'], $graph['faculty']);

        $this->assertSame($graph['tenant']->id, $sectionSimulation->tenant_id);
        $this->assertCount(4, $sectionSimulation->weeks);
        $this->assertDatabaseHas('team_simulations', [
            'tenant_id' => $graph['tenant']->id,
            'section_simulation_id' => $sectionSimulation->id,
            'team_id' => $graph['team']->id,
        ]);
        $this->assertDatabaseHas('simulation_seat_assignments', [
            'tenant_id' => $graph['tenant']->id,
            'team_id' => $graph['team']->id,
            'user_id' => $graph['student']->id,
            'seat_id' => $seat->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $graph['tenant']->id,
            'actor_user_id' => $graph['faculty']->id,
            'action' => 'section_simulation.assigned',
            'auditable_type' => SectionSimulation::class,
            'auditable_id' => $sectionSimulation->id,
        ]);
    }

    public function test_cross_tenant_assignment_actor_is_rejected(): void
    {
        $graph = $this->tenantGraph('A');
        $other = $this->tenantGraph('B');
        $structure = $this->simulationStructure(1);

        $this->expectException(InvalidArgumentException::class);

        app(SimulationLifecycleService::class)
            ->assignToSection($graph['section'], $structure['version'], $other['faculty']);
    }

    public function test_runtime_week_state_machine_allows_expected_sequence_and_audits_transition(): void
    {
        $graph = $this->tenantGraph('A');
        $sectionSimulation = $this->assignSimulation($graph, $this->simulationStructure(1)['version']);
        $week = $sectionSimulation->weeks()->first();
        $service = app(SimulationLifecycleService::class);

        $service->transitionWeek($week, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $service->transitionWeek($week->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty']);
        $service->transitionWeek($week->refresh(), SectionSimulationWeekStatus::Closed, $graph['faculty']);
        $service->transitionWeek($week->refresh(), SectionSimulationWeekStatus::Published, $graph['faculty']);

        $this->assertSame(SectionSimulationWeekStatus::Published, $week->refresh()->status);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $graph['tenant']->id,
            'action' => 'section_simulation_week.published',
            'auditable_type' => SectionSimulationWeek::class,
            'auditable_id' => $week->id,
        ]);

        $audit = AuditEvent::query()->where('action', 'section_simulation_week.published')->firstOrFail();
        $this->assertSame(['status' => 'closed'], $audit->before_state);
        $this->assertSame(['status' => 'published'], $audit->after_state);
    }

    public function test_scheduled_path_can_release(): void
    {
        $graph = $this->tenantGraph('A');
        $sectionSimulation = $this->assignSimulation($graph, $this->simulationStructure(1)['version']);
        $week = $sectionSimulation->weeks()->first();
        $service = app(SimulationLifecycleService::class);

        $service->transitionWeek($week, SectionSimulationWeekStatus::Scheduled, $graph['faculty']);
        $service->transitionWeek($week->refresh(), SectionSimulationWeekStatus::Released, $graph['faculty']);

        $this->assertSame(SectionSimulationWeekStatus::Released, $week->refresh()->status);
        $this->assertNotNull($week->scheduled_at);
        $this->assertNotNull($week->released_at);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $graph = $this->tenantGraph('A');
        $sectionSimulation = $this->assignSimulation($graph, $this->simulationStructure(1)['version']);
        $week = $sectionSimulation->weeks()->first();

        $this->expectException(InvalidArgumentException::class);

        app(SimulationLifecycleService::class)
            ->transitionWeek($week, SectionSimulationWeekStatus::Published, $graph['faculty']);
    }

    public function test_published_week_cannot_reopen(): void
    {
        $graph = $this->tenantGraph('A');
        $sectionSimulation = $this->assignSimulation($graph, $this->simulationStructure(1)['version']);
        $week = $sectionSimulation->weeks()->first();
        $service = app(SimulationLifecycleService::class);

        $service->transitionWeek($week, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $service->transitionWeek($week->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty']);
        $service->transitionWeek($week->refresh(), SectionSimulationWeekStatus::Closed, $graph['faculty']);
        $service->transitionWeek($week->refresh(), SectionSimulationWeekStatus::Published, $graph['faculty']);

        $this->expectException(InvalidArgumentException::class);

        $service->transitionWeek($week->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty']);
    }
}
