<?php

namespace Tests\Feature\Faculty;

use App\Domain\Advisors\AdvisorCatalog;
use App\Domain\Advisors\AdvisorConsultationService;
use App\Domain\Economics\Resolution\WeekResolutionService;
use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Interpretation\InterpretiveAssistantProvider;
use App\Domain\Interpretation\InterpretiveAssistantService;
use App\Domain\Ranking\RankingCalculationService;
use App\Domain\Scoring\Week4KpiPopulationService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Standing\CounterpartyCatalog;
use App\Domain\Standing\StandingService;
use App\Domain\Submissions\SubmissionService;
use App\Domain\WhatIf\Week4WhatIfSimulationService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Enums\StandingValue;
use App\Jobs\GenerateInterpretationJob;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\InterpretationRequest;
use App\Models\InterpretationResult;
use App\Models\MemoDefinition;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class InterpretiveAssistantFoundationTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_context_includes_existing_historical_records(): void
    {
        $context = $this->interpretationContext();

        $payload = app(InterpretiveAssistantService::class)->buildContextFor(
            $context['graph']['faculty'],
            $context['teamSimulation'],
            $context['runtimeWeek'],
        );

        $this->assertSame($context['teamSimulation']->id, $payload['team']['team_simulation_id']);
        $this->assertSame(4, $payload['runtime_week']['week_number']);
        $this->assertCount(1, $payload['decisions']);
        $this->assertCount(1, $payload['memos']);
        $this->assertCount(1, $payload['economic_resolutions']);
        $this->assertCount(7, $payload['kpi_snapshots']);
        $this->assertCount(1, $payload['ranking_snapshots']);
        $this->assertCount(8, $payload['standing_states']);
        $this->assertGreaterThanOrEqual(9, count($payload['standing_events']));
        $this->assertCount(2, $payload['consequence_links']);
        $this->assertCount(1, $payload['advisor_consultations']);
        $this->assertCount(1, $payload['alternatives']);
        $this->assertSame('week4_transfer_pricing', $payload['decisions'][0]['definition_key']);
        $this->assertSame('ana_ruiz', $payload['advisor_consultations'][0]['advisor_key']);
    }

    public function test_faculty_request_stores_context_and_dispatches_queued_job(): void
    {
        Bus::fake();
        $context = $this->interpretationContext();

        $request = app(InterpretiveAssistantService::class)->requestInterpretation(
            $context['graph']['faculty'],
            $context['teamSimulation'],
            $context['runtimeWeek'],
            'week4_debrief',
        );

        $this->assertSame('week4_debrief', $request->focus);
        $this->assertSame(64, strlen($request->context_hash));
        $this->assertSame(InterpretiveAssistantService::PROMPT_VERSION, $request->prompt_version);
        $this->assertSame($context['teamSimulation']->id, $request->context_snapshot['team']['team_simulation_id']);
        $this->assertSame(1, InterpretationRequest::query()->count());
        $this->assertSame(0, InterpretationResult::query()->count());

        Bus::assertDispatched(GenerateInterpretationJob::class, fn (GenerateInterpretationJob $job): bool => $job->interpretationRequestId === $request->id);
    }

    public function test_queued_job_creates_stored_interpretation_result_once(): void
    {
        Bus::fake();
        $context = $this->interpretationContext();
        $request = app(InterpretiveAssistantService::class)->requestInterpretation(
            $context['graph']['faculty'],
            $context['teamSimulation'],
            $context['runtimeWeek'],
        );
        $job = new GenerateInterpretationJob($request->id);

        $job->handle(app(InterpretiveAssistantProvider::class));
        $job->handle(app(InterpretiveAssistantProvider::class));

        $result = InterpretationResult::query()->firstOrFail();

        $this->assertSame(1, InterpretationResult::query()->count());
        $this->assertSame($request->id, $result->interpretation_request_id);
        $this->assertSame($request->context_hash, $result->context_hash);
        $this->assertSame($request->prompt_version, $result->prompt_version);
        $this->assertSame('structured_placeholder', $result->provider);
        $this->assertStringContainsString('does not assign grades', $result->response);
        $this->assertSame(1, $result->response_snapshot['record_counts']['decisions']);
        $this->assertSame(7, $result->response_snapshot['record_counts']['kpi_snapshots']);
    }

    public function test_student_cannot_request_interpretation(): void
    {
        Bus::fake();
        $context = $this->interpretationContext();

        $this->expectException(InvalidArgumentException::class);

        app(InterpretiveAssistantService::class)->requestInterpretation(
            $context['graph']['student'],
            $context['teamSimulation'],
            $context['runtimeWeek'],
        );
    }

    public function test_cross_tenant_faculty_cannot_request_interpretation(): void
    {
        Bus::fake();
        $first = $this->interpretationContext('A');
        $second = $this->interpretationContext('B');

        $this->expectException(InvalidArgumentException::class);

        app(InterpretiveAssistantService::class)->requestInterpretation(
            $first['graph']['faculty'],
            $second['teamSimulation'],
            $second['runtimeWeek'],
        );
    }

    public function test_interpretation_records_are_immutable(): void
    {
        Bus::fake();
        $context = $this->interpretationContext();
        $request = app(InterpretiveAssistantService::class)->requestInterpretation(
            $context['graph']['faculty'],
            $context['teamSimulation'],
            $context['runtimeWeek'],
        );
        (new GenerateInterpretationJob($request->id))->handle(app(InterpretiveAssistantProvider::class));

        $this->expectException(InvalidArgumentException::class);

        InterpretationResult::query()->firstOrFail()->update(['response' => 'changed']);
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
    private function interpretationContext(string $suffix = 'A'): array
    {
        $graph = $this->tenantGraph($suffix);
        $context = $this->openWeek4Context($graph);

        $advisor = app(AdvisorCatalog::class)->ensureHaldenAdvisors()->firstWhere('key', 'ana_ruiz');
        app(AdvisorConsultationService::class)->requestConsultation(
            actor: $graph['student'],
            runtimeWeek: $context['runtimeWeek'],
            teamSimulation: $context['teamSimulation'],
            advisor: $advisor,
            question: 'What should we consider before setting the transfer price?',
        );

        app(SubmissionService::class)->submitMemo(
            $graph['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['memoDefinition'],
            'We selected a market-based transfer price and noted incentive risks.',
        );

        $submission = app(SubmissionService::class)->submitDecision(
            $graph['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['transfer_price' => '46.20'],
        );
        $resolution = app(WeekResolutionService::class)->resolveSubmittedDecision($submission, $graph['student']);
        app(Week4KpiPopulationService::class)->populate($resolution);
        app(RankingCalculationService::class)->calculateForSectionWeek($context['runtimeWeek']);

        $standingService = app(StandingService::class);
        $standingService->initializeTeamSimulation($context['teamSimulation']);
        $counterparty = app(CounterpartyCatalog::class)->ensureHaldenCounterparties()->firstWhere('key', 'delacroix');
        $standingService->applyChange(
            $context['teamSimulation'],
            $counterparty,
            StandingValue::Guarded,
            'Transfer-pricing debate increased scrutiny.',
            $context['runtimeWeek'],
            $submission,
        );

        app(Week4WhatIfSimulationService::class)->runTransferPriceScenario(
            $resolution,
            '18.70',
            $graph['faculty'],
        );

        $sectionSimulation = $context['sectionSimulation'];
        $runtimeWeek = $context['runtimeWeek'];
        $teamSimulation = $context['teamSimulation'];

        return compact('graph', 'sectionSimulation', 'runtimeWeek', 'teamSimulation', 'submission', 'resolution');
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array{sectionSimulation: SectionSimulation, runtimeWeek: SectionSimulationWeek, decisionDefinition: DecisionFormDefinition, memoDefinition: MemoDefinition, teamSimulation: TeamSimulation}
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

        $memoDefinition = MemoDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week4_board_memo',
            'title' => 'Week 4 board memo',
            'character_limit' => 1000,
        ]);

        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        return compact('sectionSimulation', 'runtimeWeek', 'decisionDefinition', 'memoDefinition', 'teamSimulation');
    }
}
