<?php

namespace Tests\Feature\Faculty;

use App\Domain\Economics\Resolution\WeekResolutionService;
use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Domain\WhatIf\Week4WhatIfSimulationService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Livewire\FacultyWhatIfConsole;
use App\Models\ConsequenceLink;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\KpiSnapshot;
use App\Models\RankingSnapshot;
use App\Models\Section;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\StandingEvent;
use App\Models\TeamSimulation;
use App\Models\WhatIfRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class FacultyWhatIfConsoleTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_assigned_faculty_can_run_week4_transfer_price_what_if(): void
    {
        $context = $this->resolvedWeek4Decision(transferPrice: '73.70');

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyWhatIfConsole::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('teamSimulationId', $context['teamSimulation']->id)
            ->set('sourceResolutionId', $context['resolution']->id)
            ->set('transferPrice', '18.70')
            ->call('runScenario')
            ->assertSee('Counterfactual result')
            ->assertSee('week4_transfer_price')
            ->assertSee('18.70');

        $run = WhatIfRun::query()->firstOrFail();

        $this->assertTrue($run->counterfactual);
        $this->assertSame(Week4WhatIfSimulationService::SCENARIO_TYPE, $run->scenario_type);
        $this->assertSame('73.700', $run->scenario_inputs['original_transfer_price']);
        $this->assertSame('18.70', $run->scenario_inputs['counterfactual_transfer_price']);
        $this->assertSame('76.75', $run->calculated_outputs['segment_result']['integrated_margin']);
        $this->assertSame('18.70', $run->calculated_outputs['segment_result']['transfer_price']);
        $this->assertSame(Week4EconomicEngine::ENGINE_IDENTIFIER, $run->engine_identifier);
        $this->assertSame(Week4EconomicEngine::ENGINE_VERSION, $run->engine_version);
    }

    public function test_student_is_denied_what_if_page_and_service(): void
    {
        $context = $this->resolvedWeek4Decision();

        $this->actingAs($context['graph']['student'])
            ->get(route('faculty.what-if'))
            ->assertForbidden();

        $this->expectException(InvalidArgumentException::class);

        app(Week4WhatIfSimulationService::class)->runTransferPriceScenario(
            $context['resolution'],
            '18.70',
            $context['graph']['student'],
        );
    }

    public function test_what_if_does_not_mutate_historical_state_or_create_real_records(): void
    {
        $context = $this->resolvedWeek4Decision(transferPrice: '73.70');
        $originalResolution = $context['resolution']->only([
            'transfer_price',
            'integrated_margin',
            'upstream_margin',
            'refining_margin',
            'geneva_capture_per_bbl',
        ]);
        $counts = $this->historicalRecordCounts();

        app(Week4WhatIfSimulationService::class)->runTransferPriceScenario(
            $context['resolution'],
            '18.70',
            $context['graph']['faculty'],
        );

        $this->assertSame($originalResolution, $context['resolution']->refresh()->only(array_keys($originalResolution)));
        $this->assertSame($counts, $this->historicalRecordCounts());
        $this->assertSame(1, WhatIfRun::query()->count());
    }

    public function test_week4_what_if_is_deterministic(): void
    {
        $context = $this->resolvedWeek4Decision(transferPrice: '73.70');
        $service = app(Week4WhatIfSimulationService::class);

        $first = $service->runTransferPriceScenario($context['resolution'], '18.70', $context['graph']['faculty']);
        $second = $service->runTransferPriceScenario($context['resolution'], '18.70', $context['graph']['faculty']);

        $this->assertSame($first->scenario_inputs, $second->scenario_inputs);
        $this->assertSame($first->calculated_outputs, $second->calculated_outputs);
        $this->assertSame('0.000', $first->calculated_outputs['deltas']['integrated_margin']);
        $this->assertSame('9.625', $first->calculated_outputs['geneva_arbitrage']['capture_per_bbl']);
    }

    public function test_cross_tenant_faculty_cannot_run_or_view_what_if_context(): void
    {
        $first = $this->resolvedWeek4Decision('A');
        $second = $this->resolvedWeek4Decision('B');

        $this->expectException(InvalidArgumentException::class);

        app(Week4WhatIfSimulationService::class)->runTransferPriceScenario(
            $second['resolution'],
            '18.70',
            $first['graph']['faculty'],
        );
    }

    public function test_faculty_cannot_see_unassigned_sections_in_what_if_filters(): void
    {
        $graph = $this->tenantGraph('A');
        $this->resolvedWeek4DecisionForGraph($graph);
        $otherSection = Section::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'course_id' => $graph['course']->id,
            'name' => 'Unassigned what-if section',
        ]);
        $other = $graph;
        $other['section'] = $otherSection;
        $this->assignSimulation($other, $this->simulationStructure(1)['version']);

        $this->actingAs($graph['faculty'])
            ->get(route('faculty.what-if'))
            ->assertOk()
            ->assertDontSee('Unassigned what-if section');
    }

    public function test_admin_can_view_tenant_scope_what_if_sections(): void
    {
        $graph = $this->tenantGraph('A');
        $this->resolvedWeek4DecisionForGraph($graph);
        $otherSection = Section::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'course_id' => $graph['course']->id,
            'name' => 'Admin visible what-if section',
        ]);
        $other = $graph;
        $other['section'] = $otherSection;
        $this->assignSimulation($other, $this->simulationStructure(1)['version']);

        $this->actingAs($graph['admin'])
            ->get(route('faculty.what-if'))
            ->assertOk()
            ->assertSee('Admin visible what-if section');
    }

    public function test_what_if_runs_are_immutable(): void
    {
        $context = $this->resolvedWeek4Decision();
        $run = app(Week4WhatIfSimulationService::class)->runTransferPriceScenario(
            $context['resolution'],
            '18.70',
            $context['graph']['faculty'],
        );

        $this->expectException(InvalidArgumentException::class);

        $run->update(['scenario_type' => 'changed']);
    }

    /**
     * @return array<string, int>
     */
    private function historicalRecordCounts(): array
    {
        return [
            'economic_resolutions' => EconomicResolution::query()->count(),
            'kpi_snapshots' => KpiSnapshot::query()->count(),
            'ranking_snapshots' => RankingSnapshot::query()->count(),
            'standing_events' => StandingEvent::query()->count(),
            'consequence_links' => ConsequenceLink::query()->count(),
        ];
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     runtimeWeek: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation,
     *     submission: DecisionSubmission,
     *     resolution: EconomicResolution
     * }
     */
    private function resolvedWeek4Decision(string $suffix = 'A', string $transferPrice = '46.20'): array
    {
        return $this->resolvedWeek4DecisionForGraph($this->tenantGraph($suffix), $transferPrice);
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     runtimeWeek: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation,
     *     submission: DecisionSubmission,
     *     resolution: EconomicResolution
     * }
     */
    private function resolvedWeek4DecisionForGraph(array $graph, string $transferPrice = '46.20'): array
    {
        $context = $this->openWeek4Context($graph);
        $submission = app(SubmissionService::class)->submitDecision(
            $graph['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['transfer_price' => $transferPrice],
        );
        $resolution = app(WeekResolutionService::class)->resolveSubmittedDecision($submission, $graph['student']);
        $sectionSimulation = $context['sectionSimulation'];
        $runtimeWeek = $context['runtimeWeek'];
        $teamSimulation = $context['teamSimulation'];

        return compact('graph', 'sectionSimulation', 'runtimeWeek', 'teamSimulation', 'submission', 'resolution');
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
