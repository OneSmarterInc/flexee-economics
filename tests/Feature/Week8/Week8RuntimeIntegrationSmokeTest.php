<?php

namespace Tests\Feature\Week8;

use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Content\Week8\Week8ContentPackageManifest;
use App\Domain\Content\Week8\Week8ContentPackageRegistrationService;
use App\Domain\Economics\Week8\Week8EconomicEngine;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Livewire\FacultyWeekControl;
use App\Models\CohortFeedbackEffect;
use App\Models\ConsequenceLink;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\KpiSnapshot;
use App\Models\MemoDefinition;
use App\Models\RankingSnapshot;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use App\Models\Week8EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week8RuntimeIntegrationSmokeTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week8_runs_through_student_submission_and_faculty_execution(): void
    {
        $context = $this->week8RuntimeContext();

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertSee('halden_week8.xlsx', false)
            ->assertDontSee('faculty/halden_week8_FACULTY_SOLUTION.xlsx', false)
            ->assertInertia(fn ($page) => $page
                ->where('contentPackage.status', 'active')
                ->where('contentPackage.package_type', Week8ContentPackageManifest::PACKAGE_TYPE)
                ->where('decisionDefinition.fields.0.key', 'probability_holds_full')
                ->where('memoDefinition.title', 'Week 8 OPEC scenario memo')
                ->where('status.resolution_status', 'unresolved'));

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.decisions.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['decisionDefinition']->ulid,
                'answers' => [
                    'probability_holds_full' => '0.10',
                    'probability_holds_partial' => '0.20',
                    'probability_fails' => '0.70',
                    'realized_scenario_key' => 'holds_full',
                ],
            ])
            ->assertRedirect();

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.memo.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['memoDefinition']->ulid,
                'body' => 'We weighted OPEC compliance risk toward failure while recognizing that a full hold would help upstream and compress refining.',
            ])
            ->assertRedirect();

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyWeekControl::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('runtimeWeekId', $context['runtimeWeek']->id)
            ->call('executeSelectedWeek')
            ->assertSee('completed')
            ->assertSee('deferred');

        $evaluation = Week8EconomicEvaluation::query()
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->where('section_simulation_week_id', $context['runtimeWeek']->id)
            ->firstOrFail();

        $this->assertSame(Week8EconomicEvaluation::STATUS_CALCULATED, $evaluation->status);
        $this->assertSame('week_execution_service', $evaluation->evaluated_by_process);
        $this->assertSame(Week8EconomicEngine::ENGINE_IDENTIFIER, $evaluation->engine_identifier);
        $this->assertSame(Week8EconomicEngine::ENGINE_VERSION, $evaluation->engine_version);
        $this->assertSame('80.70', $evaluation->expectedWtiValue());
        $this->assertSame('74.00', $evaluation->predictionExpectedWtiValue());
        $this->assertSame('88.00', $evaluation->realizedWtiValue());
        $this->assertSame('holds_full', $evaluation->realized_scenario_key);
        $this->assertSame('16.60', $evaluation->realization_snapshot['refining_crack']);
        $this->assertSame('0.700000', $evaluation->prediction_snapshot['probability_distribution']['fails']);
        $this->assertSame('1.0.0-draft', $evaluation->package_version);

        $this->assertSame(0, KpiSnapshot::query()->where('section_simulation_week_id', $context['runtimeWeek']->id)->count());
        $this->assertSame(0, RankingSnapshot::query()->where('section_simulation_week_id', $context['runtimeWeek']->id)->count());
        $this->assertSame(0, ConsequenceLink::query()->where('source_section_simulation_week_id', $context['runtimeWeek']->id)->count());
        $this->assertSame(0, CohortFeedbackEffect::query()->count());

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertDontSee('faculty/halden_week8_FACULTY_SOLUTION.xlsx', false)
            ->assertInertia(fn ($page) => $page
                ->where('decisionDefinition.status', 'submitted')
                ->where('memoDefinition.status', 'submitted')
                ->where('status.resolution_status', 'resolved'));
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     runtimeWeek: SectionSimulationWeek,
     *     decisionDefinition: DecisionFormDefinition,
     *     memoDefinition: MemoDefinition,
     *     teamSimulation: TeamSimulation
     * }
     */
    private function week8RuntimeContext(): array
    {
        $graph = $this->tenantGraph('A');
        $structure = $this->simulationStructure(8);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week8Definition */
        $week8Definition = $structure['simulationWeeks']->firstWhere('week_number', 8);

        app(SimulationContentActivationService::class)->activate(
            app(Week8ContentPackageRegistrationService::class)->register($week8Definition, 'week8-runtime-smoke-v1'),
        );

        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week8Definition->id)
            ->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = DecisionFormDefinition::factory()->create([
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
                'decision_form_definition_id' => $decisionDefinition->id,
                'field_key' => $key,
                'label' => $label,
                'field_type' => DecisionFieldType::Decimal,
                'is_required' => true,
                'display_order' => $order,
                'validation' => ['min' => 0, 'max' => 1],
            ]);
        }

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
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

        $memoDefinition = MemoDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week8_opec_scenario_memo',
            'title' => 'Week 8 OPEC scenario memo',
            'version' => Week8EconomicEngine::ENGINE_VERSION,
            'is_required' => true,
            'character_limit' => 4000,
        ]);

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        return compact('graph', 'sectionSimulation', 'runtimeWeek', 'decisionDefinition', 'memoDefinition', 'teamSimulation');
    }
}
