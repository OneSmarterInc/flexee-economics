<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week9\Week9EconomicEngine;
use App\Domain\Economics\Week9\Week9EconomicEvaluationService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use App\Models\Week9EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week9EconomicEvaluationServiceTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_submitted_week9_decision_creates_idempotent_evaluation(): void
    {
        $context = $this->week9Context();
        $submission = app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            [
                'rebrand_LA_MS_core' => false,
                'rebrand_gulf_secondary' => true,
                'rebrand_southeast_edge' => true,
                'nonfuel_state_key' => 'base',
            ],
        );

        $service = app(Week9EconomicEvaluationService::class);
        $first = $service->evaluate($submission, $context['graph']['faculty']);
        $second = $service->evaluate($submission, $context['graph']['faculty']);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Week9EconomicEvaluation::query()->count());
        $this->assertSame(Week9EconomicEvaluation::STATUS_CALCULATED, $first->status);
        $this->assertSame(Week9EconomicEngine::ENGINE_IDENTIFIER, $first->engine_identifier);
        $this->assertSame(Week9EconomicEngine::ENGINE_VERSION, $first->engine_version);
        $this->assertSame('base', $first->nonfuel_state_key);
        $this->assertSame(['gulf_secondary', 'southeast_edge'], $first->selected_rebrand_markets);
        $this->assertSame('22.6800', $first->partialGainValue());
        $this->assertSame('8.5415', $first->partialPaybackValue());
        $this->assertSame('7.6950', $first->fullNetGainValue());
        $this->assertSame('1.0.0-draft', $first->package_version);
    }

    public function test_draft_week9_decision_is_not_evaluated(): void
    {
        $context = $this->week9Context();
        $submission = app(SubmissionService::class)->saveDecisionDraft(
            $context['graph']['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['rebrand_southeast_edge' => true],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a submitted decision');

        app(Week9EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);
    }

    public function test_cross_tenant_actor_cannot_evaluate_week9_submission(): void
    {
        $first = $this->week9Context('A');
        $second = $this->week9Context('B');
        $submission = app(SubmissionService::class)->submitDecision(
            $second['graph']['student'],
            $second['runtimeWeek'],
            $second['teamSimulation'],
            $second['decisionDefinition'],
            ['rebrand_southeast_edge' => true],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('another tenant');

        app(Week9EconomicEvaluationService::class)->evaluate($submission, $first['graph']['faculty']);
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     runtimeWeek: SectionSimulationWeek,
     *     decisionDefinition: DecisionFormDefinition,
     *     teamSimulation: TeamSimulation
     * }
     */
    private function week9Context(string $suffix = 'A'): array
    {
        $graph = $this->tenantGraph($suffix);
        $structure = $this->simulationStructure(9);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $weekDefinition */
        $weekDefinition = $structure['simulationWeeks']->firstWhere('week_number', 9);
        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()->where('simulation_week_id', $weekDefinition->id)->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = $this->week9DecisionDefinition($runtimeWeek);

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        return compact('graph', 'runtimeWeek', 'decisionDefinition', 'teamSimulation');
    }

    private function week9DecisionDefinition(SectionSimulationWeek $runtimeWeek): DecisionFormDefinition
    {
        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week9_cordell_rebrand',
            'name' => 'Week 9 Cordell rebrand decision',
            'version' => Week9EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week9EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        foreach ([
            ['rebrand_LA_MS_core', 'Rebrand LA/MS core', 1],
            ['rebrand_gulf_secondary', 'Rebrand Gulf secondary', 2],
            ['rebrand_southeast_edge', 'Rebrand Southeast edge', 3],
        ] as [$key, $label, $order]) {
            DecisionFieldDefinition::factory()->create([
                'decision_form_definition_id' => $definition->id,
                'field_key' => $key,
                'label' => $label,
                'field_type' => DecisionFieldType::Boolean,
                'is_required' => false,
                'display_order' => $order,
            ]);
        }

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $definition->id,
            'field_key' => 'nonfuel_state_key',
            'label' => 'Non-fuel margin state',
            'field_type' => DecisionFieldType::Radio,
            'is_required' => false,
            'display_order' => 4,
            'options' => [
                ['value' => 'base', 'label' => 'Base'],
                ['value' => 'price_war', 'label' => 'Price war'],
                ['value' => 'disciplined', 'label' => 'Disciplined'],
            ],
        ]);

        return $definition;
    }
}
