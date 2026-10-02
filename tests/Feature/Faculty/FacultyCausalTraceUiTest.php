<?php

namespace Tests\Feature\Faculty;

use App\Domain\Advisors\AdvisorCatalog;
use App\Domain\Advisors\AdvisorConsultationService;
use App\Domain\Economics\Resolution\WeekResolutionService;
use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Livewire\FacultyCausalTrace;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\Section;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class FacultyCausalTraceUiTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_assigned_faculty_can_view_trace_page_timeline(): void
    {
        $context = $this->traceContext();

        $this->actingAs($context['graph']['faculty'])
            ->get(route('faculty.causal-trace'))
            ->assertOk()
            ->assertSee('Causal trace');

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyCausalTrace::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('teamSimulationId', $context['teamSimulation']->id)
            ->set('runtimeWeekId', $context['runtimeWeek']->id)
            ->assertSee('Week 4 transfer pricing')
            ->assertSee('ana_ruiz')
            ->assertSee('week4_transfer_price_segment_margin_impact');
    }

    public function test_faculty_cannot_see_unassigned_sections_in_trace_filters(): void
    {
        $graph = $this->tenantGraph('A');
        $this->traceContextForGraph($graph);
        $otherSection = Section::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'course_id' => $graph['course']->id,
            'name' => 'Unassigned trace section',
        ]);
        $other = $graph;
        $other['section'] = $otherSection;
        $this->assignSimulation($other, $this->simulationStructure(1)['version']);

        $this->actingAs($graph['faculty'])
            ->get(route('faculty.causal-trace'))
            ->assertOk()
            ->assertDontSee('Unassigned trace section');
    }

    public function test_admin_can_view_tenant_scope_trace_sections(): void
    {
        $graph = $this->tenantGraph('A');
        $this->traceContextForGraph($graph);
        $otherSection = Section::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'course_id' => $graph['course']->id,
            'name' => 'Admin visible trace section',
        ]);
        $other = $graph;
        $other['section'] = $otherSection;
        $this->assignSimulation($other, $this->simulationStructure(1)['version']);

        $this->actingAs($graph['admin'])
            ->get(route('faculty.causal-trace'))
            ->assertOk()
            ->assertSee('Admin visible trace section');
    }

    public function test_student_is_denied_trace_page(): void
    {
        $graph = $this->tenantGraph('A');

        $this->actingAs($graph['student'])
            ->get(route('faculty.causal-trace'))
            ->assertForbidden();
    }

    public function test_faculty_can_switch_to_consequence_source_trace(): void
    {
        $context = $this->traceContext();

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyCausalTrace::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('teamSimulationId', $context['teamSimulation']->id)
            ->set('runtimeWeekId', $context['runtimeWeek']->id)
            ->set('sourceType', 'consequence')
            ->assertSee('Backward trace')
            ->assertSee('week4_transfer_price_segment_margin_impact');
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     runtimeWeek: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation,
     *     decision: DecisionSubmission,
     *     resolution: EconomicResolution
     * }
     */
    private function traceContext(string $suffix = 'A'): array
    {
        return $this->traceContextForGraph($this->tenantGraph($suffix));
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     runtimeWeek: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation,
     *     decision: DecisionSubmission,
     *     resolution: EconomicResolution
     * }
     */
    private function traceContextForGraph(array $graph): array
    {
        $context = $this->openWeek4Context($graph);
        $advisor = app(AdvisorCatalog::class)->ensureHaldenAdvisors()->firstWhere('key', 'ana_ruiz');
        app(AdvisorConsultationService::class)->requestConsultation(
            actor: $graph['student'],
            runtimeWeek: $context['runtimeWeek'],
            teamSimulation: $context['teamSimulation'],
            advisor: $advisor,
            question: 'How should we think about incentives?',
        );
        $decision = app(SubmissionService::class)->submitDecision(
            $graph['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['transfer_price' => '46.20'],
        );
        $resolution = app(WeekResolutionService::class)->resolveSubmittedDecision($decision, $graph['student']);
        $sectionSimulation = $context['sectionSimulation'];
        $runtimeWeek = $context['runtimeWeek'];
        $teamSimulation = $context['teamSimulation'];

        return compact('graph', 'sectionSimulation', 'runtimeWeek', 'teamSimulation', 'decision', 'resolution');
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array{sectionSimulation: SectionSimulation, runtimeWeek: SectionSimulationWeek, decisionDefinition: DecisionFormDefinition, teamSimulation: TeamSimulation}
     */
    private function openWeek4Context(array $graph): array
    {
        $structure = $this->simulationStructure(4);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week4 */
        $week4 = $structure['simulationWeeks']->firstWhere('week_number', 4);
        $runtimeWeek = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week4->id)
            ->firstOrFail();

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

        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        return compact('sectionSimulation', 'runtimeWeek', 'decisionDefinition', 'teamSimulation');
    }
}
