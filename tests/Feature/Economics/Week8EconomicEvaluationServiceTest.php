<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week8\Week8EconomicEngine;
use App\Domain\Economics\Week8\Week8EconomicEvaluationService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use App\Models\Week8EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week8EconomicEvaluationServiceTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_submitted_week8_decision_creates_idempotent_evaluation(): void
    {
        $context = $this->week8Context();
        $submission = app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            [
                'probability_holds_full' => '0.10',
                'probability_holds_partial' => '0.20',
                'probability_fails' => '0.70',
                'realized_scenario_key' => 'holds_full',
            ],
        );

        $service = app(Week8EconomicEvaluationService::class);
        $first = $service->evaluate($submission, $context['graph']['faculty']);
        $second = $service->evaluate($submission, $context['graph']['faculty']);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Week8EconomicEvaluation::query()->count());
        $this->assertSame(Week8EconomicEvaluation::STATUS_CALCULATED, $first->status);
        $this->assertSame(Week8EconomicEngine::ENGINE_IDENTIFIER, $first->engine_identifier);
        $this->assertSame(Week8EconomicEngine::ENGINE_VERSION, $first->engine_version);
        $this->assertSame('80.70', $first->expectedWtiValue());
        $this->assertSame('74.00', $first->predictionExpectedWtiValue());
        $this->assertSame('88.00', $first->realizedWtiValue());
        $this->assertSame('holds_full', $first->realized_scenario_key);
        $this->assertSame('0.100000', $first->prediction_snapshot['probability_distribution']['holds_full']);
        $this->assertSame('holds_full', $first->realization_snapshot['scenario_key']);
    }

    public function test_draft_week8_decision_is_not_evaluated(): void
    {
        $context = $this->week8Context();
        $submission = app(SubmissionService::class)->saveDecisionDraft(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            [
                'probability_holds_full' => '0.35',
                'probability_holds_partial' => '0.40',
                'probability_fails' => '0.25',
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a submitted decision');

        app(Week8EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);
    }

    public function test_cross_tenant_actor_cannot_evaluate_week8_submission(): void
    {
        $first = $this->week8Context('A');
        $second = $this->week8Context('B');
        $submission = app(SubmissionService::class)->submitDecision(
            $second['graph']['student'],
            $second['runtimeWeek'],
            $second['teamSimulation'],
            $second['decisionDefinition'],
            [
                'probability_holds_full' => '0.35',
                'probability_holds_partial' => '0.40',
                'probability_fails' => '0.25',
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('another tenant');

        app(Week8EconomicEvaluationService::class)->evaluate($submission, $first['graph']['faculty']);
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     runtimeWeek: SectionSimulationWeek,
     *     decisionDefinition: DecisionFormDefinition,
     *     teamSimulation: TeamSimulation
     * }
     */
    private function week8Context(string $suffix = 'A'): array
    {
        $graph = $this->tenantGraph($suffix);
        $structure = $this->simulationStructure(8);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $weekDefinition */
        $weekDefinition = $structure['simulationWeeks']->firstWhere('week_number', 8);
        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()->where('simulation_week_id', $weekDefinition->id)->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = $this->week8DecisionDefinition($runtimeWeek);

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        return compact('graph', 'runtimeWeek', 'decisionDefinition', 'teamSimulation');
    }

    private function week8DecisionDefinition(SectionSimulationWeek $runtimeWeek): DecisionFormDefinition
    {
        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week8_opec_prediction',
            'name' => 'Week 8 OPEC scenario prediction',
            'version' => Week8EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week8EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        foreach ([
            ['probability_holds_full', 'Probability: full hold', 1],
            ['probability_holds_partial', 'Probability: partial hold', 2],
            ['probability_fails', 'Probability: fails', 3],
        ] as [$key, $label, $order]) {
            DecisionFieldDefinition::factory()->create([
                'decision_form_definition_id' => $definition->id,
                'field_key' => $key,
                'label' => $label,
                'field_type' => DecisionFieldType::Decimal,
                'is_required' => true,
                'display_order' => $order,
                'validation' => ['min' => 0, 'max' => 1],
            ]);
        }

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $definition->id,
            'field_key' => 'realized_scenario_key',
            'label' => 'Realized scenario',
            'field_type' => DecisionFieldType::Radio,
            'is_required' => false,
            'display_order' => 4,
            'options' => [
                ['value' => 'holds_full', 'label' => 'Full hold'],
                ['value' => 'holds_partial', 'label' => 'Partial hold'],
                ['value' => 'fails', 'label' => 'Fails'],
            ],
        ]);

        return $definition;
    }
}
