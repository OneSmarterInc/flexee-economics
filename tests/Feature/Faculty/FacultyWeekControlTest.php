<?php

namespace Tests\Feature\Faculty;

use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Content\SimulationContentPackageService;
use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Livewire\FacultyWeekControl;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\Section;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use App\Models\WeekExecutionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class FacultyWeekControlTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_assigned_faculty_can_view_and_operate_week_lifecycle(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);

        $this->actingAs($graph['faculty'])
            ->get(route('faculty.week-control'))
            ->assertOk()
            ->assertSee('Week control')
            ->assertSee('Week 1');

        Livewire::actingAs($graph['faculty'])
            ->test(FacultyWeekControl::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('runtimeWeekId', $context['runtimeWeek']->id)
            ->assertSee('Submission state')
            ->call('transitionSelectedWeek', SectionSimulationWeekStatus::Closed->value)
            ->assertSee('closed');

        $this->assertSame(SectionSimulationWeekStatus::Closed, $context['runtimeWeek']->refresh()->statusEnum());
    }

    public function test_week_control_executes_week_and_prevents_duplicate_execution(): void
    {
        $context = $this->week4ContextWithSubmittedDecision();
        $this->activateContent($context['runtimeWeek']);

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyWeekControl::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('runtimeWeekId', $context['runtimeWeek']->id)
            ->call('executeSelectedWeek')
            ->assertSee('completed')
            ->assertSee('apply cohort effects')
            ->assertSee('deferred')
            ->call('executeSelectedWeek')
            ->assertHasErrors(['execution']);

        $this->assertSame(1, WeekExecutionRecord::query()
            ->where('section_simulation_week_id', $context['runtimeWeek']->id)
            ->count());
    }

    public function test_faculty_cannot_view_or_operate_unassigned_sections(): void
    {
        $graph = $this->tenantGraph('A');
        $this->assignSimulation($graph, $this->simulationStructure(1)['version']);
        $otherSection = Section::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'course_id' => $graph['course']->id,
            'name' => 'Unassigned week control section',
        ]);
        $other = $graph;
        $other['section'] = $otherSection;
        $this->assignSimulation($other, $this->simulationStructure(1)['version']);

        $this->actingAs($graph['faculty'])
            ->get(route('faculty.week-control'))
            ->assertOk()
            ->assertDontSee('Unassigned week control section');

        Livewire::actingAs($graph['faculty'])
            ->test(FacultyWeekControl::class)
            ->assertDontSee('Unassigned week control section');
    }

    public function test_admin_can_view_tenant_scope_week_control_sections(): void
    {
        $graph = $this->tenantGraph('A');
        $this->assignSimulation($graph, $this->simulationStructure(1)['version']);
        $otherSection = Section::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'course_id' => $graph['course']->id,
            'name' => 'Admin visible week control section',
        ]);
        $other = $graph;
        $other['section'] = $otherSection;
        $this->assignSimulation($other, $this->simulationStructure(1)['version']);

        $this->actingAs($graph['admin'])
            ->get(route('faculty.week-control'))
            ->assertOk()
            ->assertSee('Admin visible week control section');
    }

    public function test_student_is_denied_week_control(): void
    {
        $graph = $this->tenantGraph('A');

        $this->actingAs($graph['student'])
            ->get(route('faculty.week-control'))
            ->assertForbidden();
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     runtimeWeek: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation
     * }
     */
    private function week4ContextWithSubmittedDecision(): array
    {
        $graph = $this->tenantGraph('A');
        $structure = $this->simulationStructure(4);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $weekDefinition */
        $weekDefinition = $structure['simulationWeeks']->firstWhere('week_number', 4);
        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()->where('simulation_week_id', $weekDefinition->id)->firstOrFail();
        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week4_transfer_pricing',
            'name' => 'Week 4 transfer pricing',
            'version' => Week4EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week4EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
            'field_key' => 'transfer_price',
            'label' => 'Transfer price',
            'field_type' => DecisionFieldType::Currency,
            'is_required' => true,
            'display_order' => 1,
            'validation' => ['min' => 0, 'max' => 250],
        ]);

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        app(SubmissionService::class)->submitDecision(
            $graph['student'],
            $runtimeWeek,
            $teamSimulation,
            $decisionDefinition,
            ['transfer_price' => '46.20'],
        );

        return compact('graph', 'sectionSimulation', 'runtimeWeek', 'teamSimulation');
    }

    private function activateContent(SectionSimulationWeek $runtimeWeek): void
    {
        $doc = 'docs/BATCH12A_IMPLEMENTATION.md';
        $package = app(SimulationContentPackageService::class)->register(
            $runtimeWeek->definition,
            'reference_package',
            'week-control-v1',
            ['week' => $runtimeWeek->definition->week_number],
            [[
                'artifact_key' => 'week-control-artifact',
                'artifact_type' => 'documentation',
                'visibility' => 'faculty',
                'path_reference' => $doc,
                'checksum' => hash_file('sha256', base_path($doc)),
                'version' => 'v1',
            ]],
        );

        app(SimulationContentActivationService::class)->activate($package);
    }
}
