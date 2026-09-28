<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week13\Week13EconomicEngine;
use App\Domain\Economics\Week13\Week13EconomicEvaluationService;
use App\Domain\Economics\Week13\Week13ReferencePackage;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use App\Models\Week13EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week13EconomicEvaluationServiceTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_submitted_week13_decision_creates_idempotent_package_backed_evaluation(): void
    {
        $context = $this->week13Context();
        $submission = app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['factor_market_strategy' => 'accept_norway_concession_compete_permian_delay_turnaround'],
        );

        $service = app(Week13EconomicEvaluationService::class);
        $first = $service->evaluate($submission, $context['graph']['faculty']);
        $second = $service->evaluate($submission, $context['graph']['faculty']);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Week13EconomicEvaluation::query()->count());
        $this->assertSame(Week13EconomicEvaluation::STATUS_CALCULATED, $first->status);
        $this->assertSame(Week13EconomicEngine::ENGINE_IDENTIFIER, $first->engine_identifier);
        $this->assertSame(Week13EconomicEngine::ENGINE_VERSION, $first->engine_version);
        $this->assertSame(Week13EconomicEvaluationService::PACKAGE_IDENTIFIER, $first->package_identifier);
        $this->assertSame('1.0.0-draft', $first->package_version);
        $this->assertSame('7.3920', $first->norwayAfterTaxCostValue());
        $this->assertSame('3.500690', $first->mrpToWageValue());
        $this->assertSame('0.037037', $first->delaySavingPctValue());
        $this->assertSame('3.000000', $first->asset_health_penalty_pts);
        $this->assertSame('50.000000', $first->worked_example_snapshot['after_tax_wage_increase']);
        $this->assertArrayHasKey('fixtures/week13_golden.json', $first->input_snapshot['reference_package']['source_hashes']);
        $this->assertSame('accept_norway_concession_compete_permian_delay_turnaround', $first->input_snapshot['decision_submission']['answers']['factor_market_strategy']);
    }

    public function test_missing_reference_package_creates_unavailable_evaluation_without_formula_fallback(): void
    {
        $context = $this->week13Context();
        $submission = app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['factor_market_strategy' => 'delay_turnaround'],
        );

        $evaluation = app(Week13EconomicEvaluationService::class)->evaluate(
            $submission,
            $context['graph']['faculty'],
            Week13ReferencePackage::missing(),
        );

        $this->assertSame(Week13EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE, $evaluation->status);
        $this->assertSame(Week13ReferencePackage::MISSING_REASON, $evaluation->unavailable_reason);
        $this->assertNull($evaluation->norway_after_tax_cost_musd);
        $this->assertSame(Week13EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE, $evaluation->output_snapshot['status']);
    }

    public function test_draft_week13_decision_is_not_evaluated(): void
    {
        $context = $this->week13Context();
        $submission = app(SubmissionService::class)->saveDecisionDraft(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['factor_market_strategy' => 'draft'],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a submitted decision');

        app(Week13EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);
    }

    public function test_cross_tenant_actor_cannot_evaluate_week13_submission(): void
    {
        $first = $this->week13Context('A');
        $second = $this->week13Context('B');
        $submission = app(SubmissionService::class)->submitDecision(
            $second['graph']['student'],
            $second['runtimeWeek'],
            $second['teamSimulation'],
            $second['decisionDefinition'],
            ['factor_market_strategy' => 'other_tenant'],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('another tenant');

        app(Week13EconomicEvaluationService::class)->evaluate($submission, $first['graph']['faculty']);
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     runtimeWeek: SectionSimulationWeek,
     *     decisionDefinition: DecisionFormDefinition,
     *     teamSimulation: TeamSimulation
     * }
     */
    private function week13Context(string $suffix = 'A'): array
    {
        $graph = $this->tenantGraph($suffix);
        $structure = $this->simulationStructure(13);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $weekDefinition */
        $weekDefinition = $structure['simulationWeeks']->firstWhere('week_number', 13);
        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()->where('simulation_week_id', $weekDefinition->id)->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = $this->week13DecisionDefinition($runtimeWeek);

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        return compact('graph', 'runtimeWeek', 'decisionDefinition', 'teamSimulation');
    }

    private function week13DecisionDefinition(SectionSimulationWeek $runtimeWeek): DecisionFormDefinition
    {
        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week13_factor_markets',
            'name' => 'Week 13 factor markets',
            'version' => Week13EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week13EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $definition->id,
            'field_key' => 'factor_market_strategy',
            'label' => 'Factor market strategy',
            'field_type' => DecisionFieldType::ShortText,
            'is_required' => true,
            'display_order' => 1,
            'help_text' => 'Package-backed Week 13 factor-market strategy note.',
            'validation' => ['max_length' => 255],
        ]);

        return $definition;
    }
}
