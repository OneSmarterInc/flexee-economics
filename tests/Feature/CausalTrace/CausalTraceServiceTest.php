<?php

namespace Tests\Feature\CausalTrace;

use App\Domain\Advisors\AdvisorCatalog;
use App\Domain\Advisors\AdvisorConsultationService;
use App\Domain\CausalTrace\CausalTraceService;
use App\Domain\Economics\Resolution\WeekResolutionService;
use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Ranking\RankingCalculationService;
use App\Domain\Scoring\Week4KpiPopulationService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Standing\CounterpartyCatalog;
use App\Domain\Standing\StandingService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Enums\StandingValue;
use App\Models\ConsequenceLink;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\KpiSnapshot;
use App\Models\RankingSnapshot;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\StandingEvent;
use App\Models\TeamSimulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class CausalTraceServiceTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_forward_trace_from_week4_decision_contains_historical_nodes(): void
    {
        $context = $this->traceContext();

        $trace = app(CausalTraceService::class)->forwardFromDecision($context['graph']['faculty'], $context['decision']);

        $this->assertSame('forward', $trace->direction);
        $this->assertSame('decision', $trace->rootType);
        $this->assertSame([
            'decision',
            'advisor',
            'standing',
            'economic',
            'consequence',
            'consequence',
            'kpi',
            'kpi',
            'kpi',
            'kpi',
            'kpi',
            'kpi',
            'kpi',
            'ranking',
        ], $trace->nodeTypes());
    }

    public function test_backward_trace_from_consequence_returns_week4_origin(): void
    {
        $context = $this->traceContext();
        $consequence = ConsequenceLink::query()
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->orderBy('id')
            ->firstOrFail();

        $trace = app(CausalTraceService::class)->backwardFromConsequence($context['graph']['faculty'], $consequence);

        $this->assertSame('backward', $trace->direction);
        $this->assertSame('consequence', $trace->rootType);
        $this->assertContains('decision', $trace->nodeTypes());
        $this->assertContains('economic', $trace->nodeTypes());
        $this->assertContains('advisor', $trace->nodeTypes());
        $decisionNode = collect($trace->nodes)->firstWhere('type', 'decision');
        $this->assertSame($context['decision']->id, $decisionNode->id);
    }

    public function test_trace_includes_advisor_history_where_applicable(): void
    {
        $context = $this->traceContext();

        $trace = app(CausalTraceService::class)->forwardFromDecision($context['graph']['faculty'], $context['decision']);
        $advisorNode = collect($trace->nodes)->firstWhere('type', 'advisor');

        $this->assertNotNull($advisorNode);
        $this->assertSame('ana_ruiz', $advisorNode->label);
        $this->assertSame(AdvisorCatalog::CONTENT_VERSION, $advisorNode->payload['content_version']);
    }

    public function test_student_is_denied_faculty_causal_trace(): void
    {
        $context = $this->traceContext();

        $this->expectException(InvalidArgumentException::class);

        app(CausalTraceService::class)->forwardFromDecision($context['graph']['student'], $context['decision']);
    }

    public function test_tenant_isolation_blocks_cross_tenant_faculty_trace(): void
    {
        $first = $this->traceContext('A');
        $second = $this->traceContext('B');

        $this->expectException(InvalidArgumentException::class);

        app(CausalTraceService::class)->forwardFromDecision($first['graph']['faculty'], $second['decision']);
    }

    public function test_trace_ordering_is_deterministic(): void
    {
        $context = $this->traceContext();
        $service = app(CausalTraceService::class);

        $first = $service->forwardFromDecision($context['graph']['faculty'], $context['decision']);
        $second = $service->forwardFromDecision($context['graph']['faculty'], $context['decision']);

        $this->assertSame(
            collect($first->nodes)->map(fn ($node): array => [$node->type, $node->id])->all(),
            collect($second->nodes)->map(fn ($node): array => [$node->type, $node->id])->all(),
        );
    }

    public function test_trace_has_no_missing_historical_nodes_for_supported_chain(): void
    {
        $context = $this->traceContext();
        $trace = app(CausalTraceService::class)->forwardFromDecision($context['graph']['faculty'], $context['decision']);

        $this->assertSame(1, EconomicResolution::query()->where('decision_submission_id', $context['decision']->id)->count());
        $this->assertSame(7, KpiSnapshot::query()->where('economic_resolution_id', $context['resolution']->id)->count());
        $this->assertSame(1, RankingSnapshot::query()->where('team_simulation_id', $context['teamSimulation']->id)->count());
        $this->assertSame(1, StandingEvent::query()->where('trigger_id', $context['decision']->id)->count());
        $this->assertCount(14, $trace->nodes);
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
        $graph = $this->tenantGraph($suffix);
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
        app(Week4KpiPopulationService::class)->populate($resolution);
        app(RankingCalculationService::class)->calculateForSectionWeek($context['runtimeWeek']);

        $counterparty = app(CounterpartyCatalog::class)->ensureHaldenCounterparties()->firstWhere('key', 'delacroix');
        app(StandingService::class)->applyChange(
            teamSimulation: $context['teamSimulation'],
            counterparty: $counterparty,
            newState: StandingValue::Guarded,
            reason: 'Fixture standing change linked to the Week 4 decision.',
            runtimeWeek: $context['runtimeWeek'],
            trigger: $decision,
        );

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
