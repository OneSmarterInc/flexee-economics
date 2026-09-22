<?php

namespace Tests\Feature\Simulation;

use App\Enums\PlatformRole;
use App\Models\Enrollment;
use App\Models\Seat;
use App\Models\Section;
use App\Models\SimulationSeatAssignment;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class SimulationSeatAssignmentTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_seat_assignment_is_specific_to_a_team_simulation_and_independent_from_platform_role(): void
    {
        $graph = $this->tenantGraph('A');
        $seat = Seat::factory()->create(['code' => 'evp-seat-test']);
        TeamMember::query()
            ->where('tenant_id', $graph['tenant']->id)
            ->where('team_id', $graph['team']->id)
            ->where('user_id', $graph['student']->id)
            ->update(['seat_id' => $seat->id]);

        $sectionSimulation = $this->assignSimulation($graph, $this->simulationStructure(1)['version']);
        $teamSimulation = TeamSimulation::query()->where('section_simulation_id', $sectionSimulation->id)->firstOrFail();

        $this->assertDatabaseHas('simulation_seat_assignments', [
            'team_simulation_id' => $teamSimulation->id,
            'user_id' => $graph['student']->id,
            'seat_id' => $seat->id,
        ]);
        $this->assertSame(PlatformRole::Student, $graph['student']->global_role);
    }

    public function test_cross_team_seat_assignment_is_rejected(): void
    {
        $graph = $this->tenantGraph('A');
        $sectionSimulation = $this->assignSimulation($graph, $this->simulationStructure(1)['version']);
        $teamSimulation = TeamSimulation::query()->where('section_simulation_id', $sectionSimulation->id)->firstOrFail();
        $otherTeam = Team::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'slug' => 'other-team',
        ]);
        $seat = Seat::factory()->create(['code' => 'bad-seat-test']);

        $this->expectException(InvalidArgumentException::class);

        SimulationSeatAssignment::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $otherTeam->id,
            'user_id' => $graph['student']->id,
            'seat_id' => $seat->id,
        ]);
    }

    public function test_cross_tenant_seat_assignment_is_rejected(): void
    {
        $graph = $this->tenantGraph('A');
        $other = $this->tenantGraph('B');
        $sectionSimulation = $this->assignSimulation($graph, $this->simulationStructure(1)['version']);
        $teamSimulation = TeamSimulation::query()->where('section_simulation_id', $sectionSimulation->id)->firstOrFail();
        $seat = Seat::factory()->create(['code' => 'tenant-seat-test']);

        $this->expectException(InvalidArgumentException::class);

        SimulationSeatAssignment::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'user_id' => $other['student']->id,
            'seat_id' => $seat->id,
        ]);
    }

    public function test_student_from_another_section_cannot_be_assigned_into_section_simulation_team(): void
    {
        $graph = $this->tenantGraph('A');
        $otherSection = Section::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'course_id' => $graph['course']->id,
        ]);
        $otherStudent = User::factory()->student()->create([
            'tenant_id' => $graph['tenant']->id,
            'email' => 'other-section-student@example.test',
        ]);
        Enrollment::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $otherSection->id,
            'user_id' => $otherStudent->id,
            'status' => 'active',
        ]);
        $sectionSimulation = $this->assignSimulation($graph, $this->simulationStructure(1)['version']);
        $teamSimulation = TeamSimulation::query()->where('section_simulation_id', $sectionSimulation->id)->firstOrFail();
        $seat = Seat::factory()->create(['code' => 'other-section-seat-test']);

        $this->expectException(InvalidArgumentException::class);

        SimulationSeatAssignment::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'user_id' => $otherStudent->id,
            'seat_id' => $seat->id,
        ]);
    }
}
