<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week2\Week2EconomicEngine;
use App\Domain\Economics\Week2\Week2EconomicEvaluationService;
use App\Enums\SubmissionStatus;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\MemoDefinition;
use App\Models\Week2EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week2EconomicEvaluationServiceTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week2_evaluation_service_persists_package_backed_outputs_idempotently(): void
    {
        $context = $this->week2SubmissionContext();

        $first = app(Week2EconomicEvaluationService::class)->evaluate($context['submission'], $context['graph']['faculty']);
        $second = app(Week2EconomicEvaluationService::class)->evaluate($context['submission'], $context['graph']['faculty']);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Week2EconomicEvaluation::query()->count());
        $this->assertSame(Week2EconomicEvaluation::STATUS_CALCULATED, $first->status);
        $this->assertSame(Week2EconomicEngine::ENGINE_IDENTIFIER, $first->engine_identifier);
        $this->assertSame(Week2EconomicEngine::ENGINE_VERSION, $first->engine_version);
        $this->assertSame('1.0.0-draft', $first->package_version);
        $this->assertSame('-0.059364', $first->cordell_weighted_est);
        $this->assertSame('-0.083805', $first->europe_weighted_est);
        $this->assertSame('0.603500', $first->cordell_weighted_passthrough);
        $this->assertSame('0.140146', $first->urban_high_comp_volume_response_pct);
        $this->assertSame('0.012814', $first->rural_low_comp_volume_response_pct);
        $this->assertSame('-0.079964', $first->worked_elasticity);
        $this->assertSame('0.074967', $first->worked_vol_response_pct);
        $this->assertSame('-0.1094', $first->decision_snapshot['answers']['est_urban_high_comp']);
    }

    public function test_students_cannot_evaluate_week2_economics(): void
    {
        $context = $this->week2SubmissionContext();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only authorized faculty can evaluate Week 2 economics.');

        app(Week2EconomicEvaluationService::class)->evaluate($context['submission'], $context['graph']['student']);
    }

    /**
     * @return array<string, mixed>
     */
    private function week2SubmissionContext(): array
    {
        $graph = $this->tenantGraph('Week2Service');
        $structure = $this->simulationStructure(2);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        $weekDefinition = $structure['simulationWeeks']->firstWhere('week_number', 2);
        $runtimeWeek = $sectionSimulation->weeks()->where('simulation_week_id', $weekDefinition->id)->firstOrFail();
        $teamSimulation = $sectionSimulation->teamSimulations()->where('team_id', $graph['team']->id)->firstOrFail();

        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week2_elasticity_estimation',
            'name' => 'Week 2 elasticity estimation',
            'version' => Week2EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week2EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        MemoDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week2_elasticity_estimation_memo',
            'title' => 'Week 2 elasticity memo',
            'version' => Week2EconomicEngine::ENGINE_VERSION,
        ]);

        $submission = DecisionSubmission::query()->create([
            'tenant_id' => $runtimeWeek->tenant_id,
            'section_simulation_id' => $runtimeWeek->section_simulation_id,
            'section_simulation_week_id' => $runtimeWeek->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_form_definition_id' => $definition->id,
            'status' => SubmissionStatus::Submitted,
            'answers' => [
                'est_urban_high_comp' => '-0.1094',
                'pricing_strategy' => 'Cut urban rack prices while protecting rural dealer economics.',
            ],
            'submitted_at' => now(),
        ]);

        return compact('graph', 'runtimeWeek', 'teamSimulation', 'submission');
    }
}
