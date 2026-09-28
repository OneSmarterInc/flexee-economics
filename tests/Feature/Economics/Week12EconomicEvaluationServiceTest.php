<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week12\Week12EconomicEngine;
use App\Domain\Economics\Week12\Week12EconomicEvaluationService;
use App\Domain\Economics\Week12\Week12ReferencePackage;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use App\Models\Week12EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week12EconomicEvaluationServiceTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_submitted_week12_decision_creates_idempotent_package_backed_evaluation(): void
    {
        $context = $this->week12Context();
        $submission = app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['selected_project_keys' => 'helix_rotterdam offshore_wind euro_retail_divest'],
        );

        $service = app(Week12EconomicEvaluationService::class);
        $first = $service->evaluate($submission, $context['graph']['faculty']);
        $second = $service->evaluate($submission, $context['graph']['faculty']);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Week12EconomicEvaluation::query()->count());
        $this->assertSame(Week12EconomicEvaluation::STATUS_CALCULATED, $first->status);
        $this->assertSame(Week12EconomicEngine::ENGINE_IDENTIFIER, $first->engine_identifier);
        $this->assertSame(Week12EconomicEngine::ENGINE_VERSION, $first->engine_version);
        $this->assertSame(Week12EconomicEvaluationService::PACKAGE_IDENTIFIER, $first->package_identifier);
        $this->assertSame('1.0.0-draft', $first->package_version);
        $this->assertSame(['helix_rotterdam', 'offshore_wind', 'euro_retail_divest'], $first->selected_projects);
        $this->assertSame(['permian_expansion', 'biofuel_conversion'], $first->rejected_projects);
        $this->assertTrue($first->selected_portfolio_feasible);
        $this->assertTrue($first->selected_includes_divestment);
        $this->assertTrue($first->selected_unlocked_by_divestment);
        $this->assertSame([], $first->selected_constraint_failures);
        $this->assertSame('1750.0000', $first->selectedCapitalRequiredValue());
        $this->assertSame('1200.0000', $first->discretionaryEnvelopeValue());
        $this->assertSame(17, $first->feasible_portfolio_count);
        $this->assertSame(3, $first->feasible_with_helix_rotterdam_count);
        $this->assertSame(2, $first->portfolios_unlocked_by_divestment_count);
        $this->assertArrayHasKey('fixtures/week12_golden.json', $first->input_snapshot['reference_package']['source_hashes']);
        $this->assertSame('1750.000000', $first->output_snapshot['selected_portfolio']['capital_required_musd']);
    }

    public function test_selected_projects_change_persisted_portfolio_context(): void
    {
        $context = $this->week12Context();
        $service = app(Week12EconomicEvaluationService::class);

        $helixSubmission = app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['selected_project_keys' => 'helix_rotterdam'],
        );
        $helixEvaluation = $service->evaluate($helixSubmission, $context['graph']['faculty']);

        $secondContext = $this->week12Context('B');
        $portfolioSubmission = app(SubmissionService::class)->submitDecision(
            $secondContext['graph']['student'],
            $secondContext['runtimeWeek'],
            $secondContext['teamSimulation'],
            $secondContext['decisionDefinition'],
            ['selected_project_keys' => 'helix_rotterdam offshore_wind euro_retail_divest'],
        );
        $portfolioEvaluation = $service->evaluate($portfolioSubmission, $secondContext['graph']['faculty']);

        $this->assertSame(['helix_rotterdam'], $helixEvaluation->selected_projects);
        $this->assertSame(['helix_rotterdam', 'offshore_wind', 'euro_retail_divest'], $portfolioEvaluation->selected_projects);
        $this->assertFalse($helixEvaluation->selected_includes_divestment);
        $this->assertTrue($portfolioEvaluation->selected_includes_divestment);
        $this->assertFalse($helixEvaluation->selected_unlocked_by_divestment);
        $this->assertTrue($portfolioEvaluation->selected_unlocked_by_divestment);
        $this->assertNotSame($helixEvaluation->output_snapshot['selected_portfolio'], $portfolioEvaluation->output_snapshot['selected_portfolio']);
    }

    public function test_missing_required_portfolio_input_creates_invalid_evaluation_without_defaults(): void
    {
        $context = $this->week12Context(requiredField: false);
        $submission = app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            [],
        );

        $evaluation = app(Week12EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);

        $this->assertSame(Week12EconomicEvaluation::STATUS_INVALID_SUBMISSION, $evaluation->status);
        $this->assertSame('Week 12 submission must include at least one selected project.', $evaluation->unavailable_reason);
        $this->assertSame([], $evaluation->selected_projects);
        $this->assertNull($evaluation->selected_portfolio_feasible);
        $this->assertSame(Week12EconomicEvaluation::STATUS_INVALID_SUBMISSION, $evaluation->output_snapshot['status']);
    }

    public function test_missing_reference_package_creates_unavailable_evaluation_without_formula_fallback(): void
    {
        $context = $this->week12Context();
        $submission = app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['selected_project_keys' => 'helix_rotterdam'],
        );

        $evaluation = app(Week12EconomicEvaluationService::class)->evaluate(
            $submission,
            $context['graph']['faculty'],
            Week12ReferencePackage::missing(),
        );

        $this->assertSame(Week12EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE, $evaluation->status);
        $this->assertSame(Week12ReferencePackage::MISSING_REASON, $evaluation->unavailable_reason);
        $this->assertNull($evaluation->selected_portfolio_feasible);
        $this->assertSame(Week12EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE, $evaluation->output_snapshot['status']);
    }

    public function test_draft_week12_decision_is_not_evaluated(): void
    {
        $context = $this->week12Context();
        $submission = app(SubmissionService::class)->saveDecisionDraft(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['selected_project_keys' => 'helix_rotterdam'],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a submitted decision');

        app(Week12EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);
    }

    public function test_cross_tenant_actor_cannot_evaluate_week12_submission(): void
    {
        $first = $this->week12Context('A');
        $second = $this->week12Context('B');
        $submission = app(SubmissionService::class)->submitDecision(
            $second['graph']['student'],
            $second['runtimeWeek'],
            $second['teamSimulation'],
            $second['decisionDefinition'],
            ['selected_project_keys' => 'helix_rotterdam'],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('another tenant');

        app(Week12EconomicEvaluationService::class)->evaluate($submission, $first['graph']['faculty']);
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     runtimeWeek: SectionSimulationWeek,
     *     decisionDefinition: DecisionFormDefinition,
     *     teamSimulation: TeamSimulation
     * }
     */
    private function week12Context(string $suffix = 'A', bool $requiredField = true): array
    {
        $graph = $this->tenantGraph($suffix);
        $structure = $this->simulationStructure(12);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $weekDefinition */
        $weekDefinition = $structure['simulationWeeks']->firstWhere('week_number', 12);
        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()->where('simulation_week_id', $weekDefinition->id)->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = $this->week12DecisionDefinition($runtimeWeek, $requiredField);

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        return compact('graph', 'runtimeWeek', 'decisionDefinition', 'teamSimulation');
    }

    private function week12DecisionDefinition(SectionSimulationWeek $runtimeWeek, bool $requiredField = true): DecisionFormDefinition
    {
        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week12_transition_portfolio',
            'name' => 'Week 12 transition portfolio',
            'version' => Week12EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week12EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $definition->id,
            'field_key' => 'selected_project_keys',
            'label' => 'Selected portfolio project keys',
            'field_type' => DecisionFieldType::ShortText,
            'is_required' => $requiredField,
            'display_order' => 1,
            'help_text' => 'Space-separated project keys from the Week 12 package.',
            'validation' => ['max_length' => 255],
        ]);

        return $definition;
    }
}
