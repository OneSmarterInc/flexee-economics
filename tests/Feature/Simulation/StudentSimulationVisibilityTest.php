<?php

namespace Tests\Feature\Simulation;

use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class StudentSimulationVisibilityTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_student_dashboard_only_lists_released_or_later_weeks(): void
    {
        $graph = $this->tenantGraph('A');
        $sectionSimulation = $this->assignSimulation($graph, $this->simulationStructure(2)['version']);
        $weekOne = $sectionSimulation->weeks()->whereHas('definition', fn ($query) => $query->where('week_number', 1))->first();
        $weekTwo = $sectionSimulation->weeks()->whereHas('definition', fn ($query) => $query->where('week_number', 2))->first();

        app(SimulationLifecycleService::class)
            ->transitionWeek($weekOne, SectionSimulationWeekStatus::Released, $graph['faculty']);

        $this->actingAs($graph['student'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('foundation.simulations', 1)
                ->where('foundation.simulations.0.weeks.0.number', 1)
                ->missing('foundation.simulations.0.weeks.1'));

        $this->actingAs($graph['student'])
            ->get(route('foundation.section-simulation-weeks.show', $weekTwo))
            ->assertForbidden();
    }

    public function test_student_cannot_access_another_section_runtime_week(): void
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

        app(SimulationLifecycleService::class)
            ->transitionWeek($week, SectionSimulationWeekStatus::Released, $graph['faculty']);

        $this->actingAs($graph['student'])
            ->get(route('foundation.section-simulation-weeks.show', $week))
            ->assertForbidden();
    }
}
