<?php

namespace Tests\Feature\Simulation;

use App\Enums\SectionSimulationWeekStatus;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class SimulationAuthorizationTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_assigned_faculty_can_manage_runtime_week_transition(): void
    {
        $graph = $this->tenantGraph('A');
        $sectionSimulation = $this->assignSimulation($graph, $this->simulationStructure(1)['version']);
        $week = $sectionSimulation->weeks()->first();

        $this->actingAs($graph['faculty'])
            ->post(route('foundation.section-simulation-weeks.transition', $week), [
                'status' => SectionSimulationWeekStatus::Released->value,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'released');
    }

    public function test_assigned_faculty_can_access_lifecycle_page(): void
    {
        $graph = $this->tenantGraph('A');
        $this->assignSimulation($graph, $this->simulationStructure(1)['version']);

        $this->actingAs($graph['faculty'])
            ->get(route('simulation-lifecycle.overview'))
            ->assertOk();
    }

    public function test_student_cannot_access_lifecycle_page(): void
    {
        $graph = $this->tenantGraph('A');

        $this->actingAs($graph['student'])
            ->get(route('simulation-lifecycle.overview'))
            ->assertForbidden();
    }

    public function test_faculty_cannot_manage_unassigned_section_runtime_week(): void
    {
        $graph = $this->tenantGraph('A');
        $otherSection = Section::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'course_id' => $graph['course']->id,
        ]);
        $other = $graph;
        $other['section'] = $otherSection;
        $sectionSimulation = $this->assignSimulation($other, $this->simulationStructure(1)['version']);
        $week = $sectionSimulation->weeks()->first();

        $this->actingAs($graph['faculty'])
            ->post(route('foundation.section-simulation-weeks.transition', $week), [
                'status' => SectionSimulationWeekStatus::Released->value,
            ])
            ->assertForbidden();
    }

    public function test_cross_tenant_faculty_cannot_view_runtime_week(): void
    {
        $graph = $this->tenantGraph('A');
        $other = $this->tenantGraph('B');
        $sectionSimulation = $this->assignSimulation($graph, $this->simulationStructure(1)['version']);
        $week = $sectionSimulation->weeks()->first();

        $this->actingAs($other['faculty'])
            ->get(route('foundation.section-simulation-weeks.show', $week))
            ->assertForbidden();
    }

    public function test_cross_tenant_faculty_cannot_mutate_runtime_week_by_ulid(): void
    {
        $graph = $this->tenantGraph('A');
        $other = $this->tenantGraph('B');
        $sectionSimulation = $this->assignSimulation($graph, $this->simulationStructure(1)['version']);
        $week = $sectionSimulation->weeks()->first();

        $this->actingAs($other['faculty'])
            ->post(route('foundation.section-simulation-weeks.transition', $week), [
                'status' => SectionSimulationWeekStatus::Released->value,
            ])
            ->assertForbidden();
    }

    public function test_student_cannot_perform_lifecycle_operations(): void
    {
        $graph = $this->tenantGraph('A');
        $sectionSimulation = $this->assignSimulation($graph, $this->simulationStructure(1)['version']);
        $week = $sectionSimulation->weeks()->first();

        $this->actingAs($graph['student'])
            ->post(route('foundation.section-simulation-weeks.transition', $week), [
                'status' => SectionSimulationWeekStatus::Released->value,
            ])
            ->assertForbidden();
    }
}
