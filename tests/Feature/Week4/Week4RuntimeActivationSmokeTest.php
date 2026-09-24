<?php

namespace Tests\Feature\Week4;

use App\Domain\Content\Week4\Week4ContentPackageRegistrationService;
use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Interpretation\InterpretiveAssistantService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Jobs\GenerateInterpretationJob;
use App\Livewire\FacultyCausalTrace;
use App\Livewire\FacultyWeekControl;
use App\Livewire\FacultyWhatIfConsole;
use App\Models\ConsequenceLink;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\EconomicResolution;
use App\Models\InterpretationRequest;
use App\Models\KpiSnapshot;
use App\Models\MemoDefinition;
use App\Models\RankingSnapshot;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use App\Models\WhatIfRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week4RuntimeActivationSmokeTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week4_runs_end_to_end_through_student_and_faculty_workspaces(): void
    {
        $context = $this->week4RuntimeContext();

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertSee('halden_week4.xlsx', false)
            ->assertDontSee('faculty/halden_week4_solution', false)
            ->assertInertia(fn ($page) => $page
                ->where('contentPackage.status', 'active')
                ->where('decisionDefinition.fields.0.key', 'transfer_price')
                ->where('memoDefinition.title', 'Week 4 transfer pricing memo')
                ->where('status.resolution_status', 'unresolved'));

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.decisions.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['decisionDefinition']->ulid,
                'answers' => ['transfer_price' => '46.20'],
            ])
            ->assertRedirect();

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.memo.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['memoDefinition']->ulid,
                'body' => 'We selected a transfer price that preserves integrated economics while making the segment tradeoff explicit.',
            ])
            ->assertRedirect();

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyWeekControl::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('runtimeWeekId', $context['runtimeWeek']->id)
            ->call('executeSelectedWeek')
            ->assertSee('completed')
            ->assertSee('deferred');

        $resolution = EconomicResolution::query()
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->where('section_simulation_week_id', $context['runtimeWeek']->id)
            ->firstOrFail();

        $this->assertSame('46.200', $resolution->transfer_price);
        $this->assertSame(1, EconomicResolution::query()->where('section_simulation_week_id', $context['runtimeWeek']->id)->count());
        $this->assertGreaterThan(0, KpiSnapshot::query()->where('section_simulation_week_id', $context['runtimeWeek']->id)->count());
        $this->assertGreaterThan(0, RankingSnapshot::query()->where('section_simulation_week_id', $context['runtimeWeek']->id)->count());
        $this->assertSame(2, ConsequenceLink::query()->where('source_section_simulation_week_id', $context['runtimeWeek']->id)->count());

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertDontSee('faculty/halden_week4_solution', false)
            ->assertInertia(fn ($page) => $page
                ->where('decisionDefinition.status', 'submitted')
                ->where('memoDefinition.status', 'submitted')
                ->where('status.resolution_status', 'resolved'));

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyCausalTrace::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('teamSimulationId', $context['teamSimulation']->id)
            ->set('runtimeWeekId', $context['runtimeWeek']->id)
            ->assertSee('Week 4 transfer pricing')
            ->assertSee('week4_transfer_price_segment_margin_impact');

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyWhatIfConsole::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('teamSimulationId', $context['teamSimulation']->id)
            ->set('sourceResolutionId', $resolution->id)
            ->set('transferPrice', '18.70')
            ->call('runScenario')
            ->assertSee('Counterfactual result')
            ->assertSee('18.70');

        $this->assertSame(1, WhatIfRun::query()->count());

        Bus::fake();

        app(InterpretiveAssistantService::class)->requestInterpretation(
            $context['graph']['faculty'],
            $context['teamSimulation'],
            $context['runtimeWeek'],
        );

        $this->assertSame(1, InterpretationRequest::query()->count());
        Bus::assertDispatched(GenerateInterpretationJob::class);

        $this->actingAs($context['graph']['student'])
            ->get(route('faculty.causal-trace'))
            ->assertForbidden();

        $this->actingAs($context['graph']['student'])
            ->get(route('faculty.what-if'))
            ->assertForbidden();
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     runtimeWeek: SectionSimulationWeek,
     *     decisionDefinition: DecisionFormDefinition,
     *     memoDefinition: MemoDefinition,
     *     teamSimulation: TeamSimulation
     * }
     */
    private function week4RuntimeContext(): array
    {
        $graph = $this->tenantGraph('A');
        $structure = $this->simulationStructure(4);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week4 */
        $week4 = $structure['simulationWeeks']->firstWhere('week_number', 4);
        app(Week4ContentPackageRegistrationService::class)->ensureActivated($week4);

        /** @var SectionSimulationWeek $runtimeWeek */
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
            'unit' => '$/bbl',
            'validation' => ['min' => 0, 'max' => 250],
        ]);

        $memoDefinition = MemoDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week4_transfer_pricing_memo',
            'title' => 'Week 4 transfer pricing memo',
            'version' => Week4EconomicEngine::ENGINE_VERSION,
            'is_required' => true,
            'character_limit' => 4000,
        ]);

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        return compact('graph', 'sectionSimulation', 'runtimeWeek', 'decisionDefinition', 'memoDefinition', 'teamSimulation');
    }
}
