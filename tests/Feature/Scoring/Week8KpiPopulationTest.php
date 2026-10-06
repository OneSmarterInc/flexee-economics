<?php

namespace Tests\Feature\Scoring;

use App\Domain\Economics\Week8\Week8EconomicEngine;
use App\Domain\Economics\Week8\Week8EconomicEvaluationService;
use App\Domain\Scoring\Week8KpiPopulationService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\KpiSnapshotStatus;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\KpiSnapshot;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use App\Models\Week8EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week8KpiPopulationTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week8_evaluation_populates_package_backed_kpi_snapshots(): void
    {
        $evaluation = $this->evaluatedWeek8Decision();

        $snapshots = app(Week8KpiPopulationService::class)->populate($evaluation);
        $refining = collect($snapshots)->firstOrFail(fn (KpiSnapshot $snapshot): bool => $snapshot->definition->key === 'refining_net_margin_vs_benchmark');
        $integrated = collect($snapshots)->firstOrFail(fn (KpiSnapshot $snapshot): bool => $snapshot->definition->key === 'integrated_margin_per_boe');
        $roace = collect($snapshots)->firstOrFail(fn (KpiSnapshot $snapshot): bool => $snapshot->definition->key === 'roace');

        $this->assertCount(7, $snapshots);
        $this->assertSame(KpiSnapshotStatus::Available, $refining->statusEnum());
        $this->assertSame('0.0000', $refining->value);
        $this->assertNull($refining->economic_resolution_id);
        $this->assertSame($evaluation->id, $refining->input_snapshot['source_id']);
        $this->assertTrue($refining->input_snapshot['source_snapshot']['package_backed_kpi_state']);
        $this->assertSame('14.00', $refining->input_snapshot['source_snapshot']['inputs']['i8_dwti']);
        $this->assertSame('830.375000', $refining->input_snapshot['source_snapshot']['inputs']['i8_ebitda_effect_musd']);
        $this->assertSame('interim_week8_ebitda_bridge_v0', $refining->input_snapshot['source_snapshot']['inputs']['i8_ebitda_bridge_identifier']);
        $this->assertSame('0.00', $refining->input_snapshot['source_snapshot']['inputs']['i8_window2_shift']);

        $this->assertSame(KpiSnapshotStatus::Available, $integrated->statusEnum());
        $this->assertSame('85.8500', $integrated->value);
        $this->assertSame(KpiSnapshotStatus::Available, $roace->statusEnum());
        $this->assertNotNull($roace->value);
    }

    public function test_week8_kpi_population_is_idempotent(): void
    {
        $evaluation = $this->evaluatedWeek8Decision();
        $service = app(Week8KpiPopulationService::class);

        $first = $service->populate($evaluation);
        $second = $service->populate($evaluation);

        $this->assertCount(7, $first);
        $this->assertCount(7, $second);
        $this->assertSame(7, KpiSnapshot::query()->count());
        $this->assertSame(
            collect($first)->pluck('id')->all(),
            collect($second)->pluck('id')->all(),
        );
    }

    private function evaluatedWeek8Decision(): Week8EconomicEvaluation
    {
        $graph = $this->tenantGraph('A');
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

        $submission = app(SubmissionService::class)->submitDecision(
            $graph['student'],
            $runtimeWeek,
            $teamSimulation,
            $decisionDefinition,
            [
                'probability_holds_full' => '0.10',
                'probability_holds_partial' => '0.20',
                'probability_fails' => '0.70',
                'realized_scenario_key' => 'holds_full',
            ],
        );

        return app(Week8EconomicEvaluationService::class)->evaluate($submission, $graph['faculty']);
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
