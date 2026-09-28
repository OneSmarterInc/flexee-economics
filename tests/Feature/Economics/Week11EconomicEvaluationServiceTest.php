<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week11\Week11EconomicEngine;
use App\Domain\Economics\Week11\Week11EconomicEvaluationService;
use App\Domain\Economics\Week11\Week11ReferencePackage;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use App\Models\Week11EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week11EconomicEvaluationServiceTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_submitted_week11_decision_creates_idempotent_package_backed_evaluation(): void
    {
        $context = $this->week11Context();
        $submission = app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['kessana_position' => 'accept_demanded_take'],
        );

        $service = app(Week11EconomicEvaluationService::class);
        $first = $service->evaluate($submission, $context['graph']['faculty']);
        $second = $service->evaluate($submission, $context['graph']['faculty']);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Week11EconomicEvaluation::query()->count());
        $this->assertSame(Week11EconomicEvaluation::STATUS_CALCULATED, $first->status);
        $this->assertSame(Week11EconomicEngine::ENGINE_IDENTIFIER, $first->engine_identifier);
        $this->assertSame(Week11EconomicEngine::ENGINE_VERSION, $first->engine_version);
        $this->assertSame('1.0.0-draft', $first->package_version);
        $this->assertSame('67.0000', $first->profitOilValue());
        $this->assertSame('3089.4010', $first->pvStayDemandedValue());
        $this->assertSame('0.984851', $first->indifferenceTakeValue());
        $this->assertSame('25.460000', $first->take_results['current']['company_margin_per_bbl']);
        $this->assertSame('0.740000', $first->demanded_take);
        $this->assertTrue($first->demanded_take_inside_comparables);
        $this->assertTrue($first->sunk_invariant);
        $this->assertArrayHasKey('fixtures/week11_golden.json', $first->input_snapshot['reference_package']['source_hashes']);
        $this->assertSame(['kessana_position' => 'accept_demanded_take'], $first->input_snapshot['decision_submission']['answers']);
        $this->assertSame('3089.400975', $first->output_snapshot['take_results']['demanded']['pv_stay_musd']);
    }

    public function test_missing_reference_package_creates_unavailable_evaluation_without_formula_fallback(): void
    {
        $context = $this->week11Context();
        $submission = app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['kessana_position' => 'walk_away'],
        );

        $evaluation = app(Week11EconomicEvaluationService::class)->evaluate(
            $submission,
            $context['graph']['faculty'],
            Week11ReferencePackage::missing(),
        );

        $this->assertSame(Week11EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE, $evaluation->status);
        $this->assertSame(Week11ReferencePackage::MISSING_REASON, $evaluation->unavailable_reason);
        $this->assertNull($evaluation->profit_oil);
        $this->assertSame(Week11EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE, $evaluation->output_snapshot['status']);
    }

    public function test_draft_week11_decision_is_not_evaluated(): void
    {
        $context = $this->week11Context();
        $submission = app(SubmissionService::class)->saveDecisionDraft(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['kessana_position' => 'accept_demanded_take'],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a submitted decision');

        app(Week11EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);
    }

    public function test_cross_tenant_actor_cannot_evaluate_week11_submission(): void
    {
        $first = $this->week11Context('A');
        $second = $this->week11Context('B');
        $submission = app(SubmissionService::class)->submitDecision(
            $second['graph']['student'],
            $second['runtimeWeek'],
            $second['teamSimulation'],
            $second['decisionDefinition'],
            ['kessana_position' => 'accept_demanded_take'],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('another tenant');

        app(Week11EconomicEvaluationService::class)->evaluate($submission, $first['graph']['faculty']);
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     runtimeWeek: SectionSimulationWeek,
     *     decisionDefinition: DecisionFormDefinition,
     *     teamSimulation: TeamSimulation
     * }
     */
    private function week11Context(string $suffix = 'A'): array
    {
        $graph = $this->tenantGraph($suffix);
        $structure = $this->simulationStructure(11);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $weekDefinition */
        $weekDefinition = $structure['simulationWeeks']->firstWhere('week_number', 11);
        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()->where('simulation_week_id', $weekDefinition->id)->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = $this->week11DecisionDefinition($runtimeWeek);

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        return compact('graph', 'runtimeWeek', 'decisionDefinition', 'teamSimulation');
    }

    private function week11DecisionDefinition(SectionSimulationWeek $runtimeWeek): DecisionFormDefinition
    {
        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week11_kessana_position',
            'name' => 'Week 11 Kessana position',
            'version' => Week11EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week11EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $definition->id,
            'field_key' => 'kessana_position',
            'label' => 'Kessana negotiating position',
            'field_type' => DecisionFieldType::Radio,
            'is_required' => true,
            'display_order' => 1,
            'options' => [
                ['value' => 'accept_demanded_take', 'label' => 'Accept demanded take'],
                ['value' => 'negotiate_midpoint', 'label' => 'Negotiate midpoint'],
                ['value' => 'walk_away', 'label' => 'Walk away'],
            ],
        ]);

        return $definition;
    }
}
