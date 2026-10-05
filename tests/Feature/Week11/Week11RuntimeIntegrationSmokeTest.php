<?php

namespace Tests\Feature\Week11;

use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageRegistrationService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Economics\Week11\Week11EconomicEngine;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\DecisionFieldType;
use App\Enums\KpiSnapshotStatus;
use App\Enums\RankingSnapshotStatus;
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
use App\Models\StandingState;
use App\Models\TeamSimulation;
use App\Models\Week11EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week11RuntimeIntegrationSmokeTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week11_runs_through_student_submission_and_faculty_execution(): void
    {
        $context = $this->week11RuntimeContext();
        $packageType = app(AuthoritativeContentPackageManifest::class)->packageType(11);

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertSee('halden_week11.xlsx', false)
            ->assertDontSee('faculty/halden_week11_FACULTY_SOLUTION.xlsx', false)
            ->assertInertia(fn ($page) => $page
                ->where('contentPackage.status', 'active')
                ->where('contentPackage.package_type', $packageType)
                ->where('decisionDefinition.fields.0.key', 'kessana_position')
                ->where('memoDefinition.title', 'Week 11 Kessana memo')
                ->where('status.resolution_status', 'unresolved'));

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.decisions.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['decisionDefinition']->ulid,
                'answers' => [
                    'kessana_position' => 'accept_demanded_take',
                ],
            ])
            ->assertRedirect();

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.memo.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['memoDefinition']->ulid,
                'body' => 'We accept the demanded take because staying remains strongly above the exit value.',
            ])
            ->assertRedirect();

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyWeekControl::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('runtimeWeekId', $context['runtimeWeek']->id)
            ->call('executeSelectedWeek')
            ->assertSee('completed')
            ->assertSee('deferred');

        $evaluation = Week11EconomicEvaluation::query()
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->where('section_simulation_week_id', $context['runtimeWeek']->id)
            ->firstOrFail();

        $this->assertSame(Week11EconomicEvaluation::STATUS_CALCULATED, $evaluation->status);
        $this->assertSame('week_execution_service', $evaluation->evaluated_by_process);
        $this->assertSame(Week11EconomicEngine::ENGINE_IDENTIFIER, $evaluation->engine_identifier);
        $this->assertSame(Week11EconomicEngine::ENGINE_VERSION, $evaluation->engine_version);
        $this->assertSame('1.0.0-draft', $evaluation->package_version);
        $this->assertSame('67.0000', $evaluation->profitOilValue());
        $this->assertSame('3089.4010', $evaluation->pvStayDemandedValue());
        $this->assertSame('0.984851', $evaluation->indifferenceTakeValue());
        $this->assertSame('4515.278348', $evaluation->take_results['current']['pv_stay_musd']);
        $this->assertSame('372.572983', $evaluation->worked_example_snapshot['pv_after']);

        $kpiSnapshots = KpiSnapshot::query()
            ->with('definition')
            ->where('section_simulation_week_id', $context['runtimeWeek']->id)
            ->orderBy('id')
            ->get();
        $this->assertSame(7, $kpiSnapshots->count());
        $this->assertSame(7, $kpiSnapshots->where('status', KpiSnapshotStatus::Available->value)->whereNotNull('value')->count());
        $this->assertTrue($kpiSnapshots->contains(fn (KpiSnapshot $snapshot): bool => $snapshot->definition->key === 'integrated_margin_per_boe'));
        $this->assertSame($evaluation->id, $kpiSnapshots->first()->input_snapshot['source_id']);
        $this->assertTrue($kpiSnapshots->first()->input_snapshot['source_snapshot']['package_backed_kpi_state']);
        $this->assertArrayHasKey('i11_kessana_margin_change', $kpiSnapshots->first()->input_snapshot['source_snapshot']['inputs']);

        $ranking = RankingSnapshot::query()
            ->where('section_simulation_week_id', $context['runtimeWeek']->id)
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->firstOrFail();
        $this->assertSame(RankingSnapshotStatus::Complete, $ranking->statusEnum());
        $this->assertSame('50.000000', $ranking->composite_score);
        $this->assertSame(1, $ranking->rank);
        $this->assertSame(0, ConsequenceLink::query()->where('source_section_simulation_week_id', $context['runtimeWeek']->id)->count());
        $this->assertSame(0, CohortFeedbackEffect::query()->count());
        $this->assertSame(0, StandingState::query()->count());

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertDontSee('faculty/halden_week11_FACULTY_SOLUTION.xlsx', false)
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
    private function week11RuntimeContext(): array
    {
        $graph = $this->tenantGraph('W11Smoke');
        $structure = $this->simulationStructure(11);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week11Definition */
        $week11Definition = $structure['simulationWeeks']->firstWhere('week_number', 11);

        app(SimulationContentActivationService::class)->activate(
            app(AuthoritativeContentPackageRegistrationService::class)->register($week11Definition, 'week11-runtime-smoke-v1'),
        );

        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week11Definition->id)
            ->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week11_kessana_position',
            'name' => 'Week 11 Kessana position',
            'version' => Week11EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week11EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
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

        $memoDefinition = MemoDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week11_kessana_memo',
            'title' => 'Week 11 Kessana memo',
            'version' => Week11EconomicEngine::ENGINE_VERSION,
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
