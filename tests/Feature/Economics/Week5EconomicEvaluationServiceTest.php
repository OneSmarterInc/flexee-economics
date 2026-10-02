<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week5\Week5EconomicEngine;
use App\Domain\Economics\Week5\Week5EconomicEvaluationService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use App\Models\Week5EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week5EconomicEvaluationServiceTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_submitted_week5_decision_creates_idempotent_evaluation(): void
    {
        $context = $this->week5Context();
        $submission = app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            [
                'crude_hedge_coverage' => '0.45',
                'hedging_policy' => 'maintain existing EUR forward and avoid gross Rotterdam hedge',
            ],
        );

        $service = app(Week5EconomicEvaluationService::class);
        $first = $service->evaluate($submission, $context['graph']['faculty']);
        $second = $service->evaluate($submission, $context['graph']['faculty']);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Week5EconomicEvaluation::query()->count());
        $this->assertSame(Week5EconomicEvaluation::STATUS_CALCULATED, $first->status);
        $this->assertSame(Week5EconomicEngine::ENGINE_IDENTIFIER, $first->engine_identifier);
        $this->assertSame(Week5EconomicEngine::ENGINE_VERSION, $first->engine_version);
        $this->assertSame('1.0.0-draft', $first->package_version);
        $this->assertSame('25.846154', $first->norwayLiftingPostValue());
        $this->assertSame('-145.161290', $first->rotOverhedgeLossValue());
        $this->assertSame('0.450000', $first->output_snapshot['week10_inherited_state']['crude_hedge_coverage']);
        $this->assertSame('maintain existing EUR forward and avoid gross Rotterdam hedge', $first->decision_snapshot['answers']['hedging_policy']);
    }

    public function test_draft_week5_decision_is_not_evaluated(): void
    {
        $context = $this->week5Context();
        $submission = app(SubmissionService::class)->saveDecisionDraft(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['crude_hedge_coverage' => '0.45'],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a submitted decision');

        app(Week5EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);
    }

    public function test_cross_tenant_actor_cannot_evaluate_week5_submission(): void
    {
        $first = $this->week5Context('A');
        $second = $this->week5Context('B');
        $submission = app(SubmissionService::class)->submitDecision(
            $second['graph']['student'],
            $second['runtimeWeek'],
            $second['teamSimulation'],
            $second['decisionDefinition'],
            ['crude_hedge_coverage' => '0.45'],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('another tenant');

        app(Week5EconomicEvaluationService::class)->evaluate($submission, $first['graph']['faculty']);
    }

    public function test_week5_evaluations_are_immutable(): void
    {
        $context = $this->week5Context();
        $submission = app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['crude_hedge_coverage' => '0.45'],
        );
        $evaluation = app(Week5EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('immutable');

        $evaluation->forceFill(['status' => 'changed'])->save();
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     runtimeWeek: SectionSimulationWeek,
     *     decisionDefinition: DecisionFormDefinition,
     *     teamSimulation: TeamSimulation
     * }
     */
    private function week5Context(string $suffix = 'A'): array
    {
        $graph = $this->tenantGraph($suffix);
        $structure = $this->simulationStructure(5);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $weekDefinition */
        $weekDefinition = $structure['simulationWeeks']->firstWhere('week_number', 5);
        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()->where('simulation_week_id', $weekDefinition->id)->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = $this->week5DecisionDefinition($runtimeWeek);

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        return compact('graph', 'runtimeWeek', 'decisionDefinition', 'teamSimulation');
    }

    private function week5DecisionDefinition(SectionSimulationWeek $runtimeWeek): DecisionFormDefinition
    {
        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week5_currency_exposure',
            'name' => 'Week 5 currency exposure decision',
            'version' => Week5EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week5EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $definition->id,
            'field_key' => 'crude_hedge_coverage',
            'label' => 'Crude hedge coverage',
            'field_type' => DecisionFieldType::Decimal,
            'is_required' => true,
            'display_order' => 1,
            'validation' => ['min' => 0, 'max' => 1],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $definition->id,
            'field_key' => 'hedging_policy',
            'label' => 'Hedging policy',
            'field_type' => DecisionFieldType::ShortText,
            'is_required' => false,
            'display_order' => 2,
            'validation' => ['max_length' => 200],
        ]);

        return $definition;
    }
}
