<?php

namespace Tests\Feature\Consequences;

use App\Domain\Consequences\ConsequenceService;
use App\Domain\Consequences\Week4ConsequenceDefinitionCatalog;
use App\Domain\Economics\Resolution\WeekResolutionService;
use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\ConsequenceDefinition;
use App\Models\ConsequenceLink;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\StandingState;
use App\Models\TeamSimulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week4ConsequenceMappingTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week4_resolution_creates_supported_consequence_links(): void
    {
        $context = $this->resolvedWeek4Decision();

        $links = ConsequenceLink::query()
            ->where('tenant_id', $context['graph']['tenant']->id)
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $links);
        $this->assertSame([
            Week4ConsequenceDefinitionCatalog::SEGMENT_MARGIN_IMPACT,
            Week4ConsequenceDefinitionCatalog::GENEVA_ARBITRAGE_RECORD,
        ], $links->pluck('definition_key')->all());
        $this->assertSame(['segment_margin_impact', 'geneva_arbitrage_record'], $links->pluck('effect_type')->all());
        $this->assertSame('46.200', $links[0]->metadata['transfer_price']);
        $this->assertSame('76.750', $links[0]->metadata['integrated_margin']);
        $this->assertSame('9.625', $links[1]->metadata['geneva_capture_per_bbl']);
    }

    public function test_week4_consequence_population_is_idempotent(): void
    {
        $context = $this->resolvedWeek4Decision();

        app(WeekResolutionService::class)->resolveSubmittedDecision($context['submission'], $context['graph']['student']);

        $this->assertSame(1, EconomicResolution::query()->count());
        $this->assertSame(2, ConsequenceLink::query()->count());
    }

    public function test_week4_consequence_links_are_immutable(): void
    {
        $this->resolvedWeek4Decision();
        $link = ConsequenceLink::query()->firstOrFail();

        $this->expectException(InvalidArgumentException::class);

        $link->update(['explanation' => 'changed']);
    }

    public function test_week4_consequence_mapping_does_not_create_standing_changes(): void
    {
        $this->resolvedWeek4Decision();

        $this->assertSame(0, StandingState::query()->count());
    }

    public function test_backward_retrieval_returns_week4_origin(): void
    {
        $context = $this->resolvedWeek4Decision();

        $links = app(ConsequenceService::class)->backwardTo($context['resolution'], $context['teamSimulation']);

        $this->assertCount(2, $links);
        $this->assertTrue($links->every(fn (ConsequenceLink $link): bool => $link->source_id === $context['resolution']->id));
        $this->assertTrue($links->every(fn (ConsequenceLink $link): bool => $link->source_section_simulation_week_id === $context['runtimeWeek']->id));
    }

    public function test_student_cannot_view_another_tenants_week4_consequence_link(): void
    {
        $first = $this->resolvedWeek4Decision('A');
        $second = $this->resolvedWeek4Decision('B');
        $link = ConsequenceLink::query()
            ->where('tenant_id', $second['graph']['tenant']->id)
            ->firstOrFail();

        $this->expectException(InvalidArgumentException::class);

        app(ConsequenceService::class)->assertCanView($first['graph']['student'], $link);
    }

    public function test_unsupported_week4_to_week6_cost_of_capital_mapping_is_not_created(): void
    {
        $this->resolvedWeek4Decision();

        $this->assertFalse(ConsequenceDefinition::query()
            ->where('key', 'week4_transfer_price_week6_cost_of_capital_input')
            ->exists());
        $this->assertSame(2, ConsequenceLink::query()->count());
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
        $graph = $this->tenantGraph($suffix);
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
