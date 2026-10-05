<?php

namespace Tests\Feature\Week13;

use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageRegistrationService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Economics\Week13\Week13EconomicEngine;
use App\Domain\Economics\Week13\Week13EconomicEvaluationService;
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
use App\Models\StandingState;
use App\Models\TeamSimulation;
use App\Models\Week13EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week13RuntimeIntegrationSmokeTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week13_runs_through_student_submission_and_faculty_execution(): void
    {
        $context = $this->week13RuntimeContext();
        $packageType = app(AuthoritativeContentPackageManifest::class)->packageType(13);

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertSee('halden_week13.xlsx', false)
            ->assertDontSee('faculty/halden_week13_FACULTY_SOLUTION.xlsx', false)
            ->assertInertia(fn ($page) => $page
                ->where('contentPackage.status', 'active')
                ->where('contentPackage.package_type', $packageType)
                ->where('decisionDefinition.fields.0.key', 'factor_market_strategy')
                ->where('memoDefinition.title', 'Week 13 factor markets memo')
                ->where('status.resolution_status', 'unresolved'));

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.decisions.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['decisionDefinition']->ulid,
                'answers' => [
                    'factor_market_strategy' => 'accept_norway_concession_compete_permian_delay_turnaround',
                ],
            ])
            ->assertRedirect();

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.memo.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['memoDefinition']->ulid,
                'body' => 'We compare Norway tax shield economics, Permian MRP retention, and the turnaround timing tradeoff.',
            ])
            ->assertRedirect();

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyWeekControl::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('runtimeWeekId', $context['runtimeWeek']->id)
            ->call('executeSelectedWeek')
            ->assertSee('completed')
            ->assertSee('deferred');

        $evaluation = Week13EconomicEvaluation::query()
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->where('section_simulation_week_id', $context['runtimeWeek']->id)
            ->firstOrFail();

        $this->assertSame(Week13EconomicEvaluation::STATUS_CALCULATED, $evaluation->status);
        $this->assertSame('week_execution_service', $evaluation->evaluated_by_process);
        $this->assertSame(Week13EconomicEngine::ENGINE_IDENTIFIER, $evaluation->engine_identifier);
        $this->assertSame(Week13EconomicEngine::ENGINE_VERSION, $evaluation->engine_version);
        $this->assertSame(Week13EconomicEvaluationService::PACKAGE_IDENTIFIER, $evaluation->package_identifier);
        $this->assertSame('1.0.0-draft', $evaluation->package_version);
        $this->assertSame('7.3920', $evaluation->norwayAfterTaxCostValue());
        $this->assertSame('3.500690', $evaluation->mrpToWageValue());
        $this->assertSame('0.037037', $evaluation->delaySavingPctValue());
        $this->assertSame('81.000000', $evaluation->turnaround_peak_cost_musd);
        $this->assertSame('78.000000', $evaluation->delay_expected_cost_musd);
        $this->assertSame('3.000000', $evaluation->asset_health_penalty_pts);
        $this->assertSame('50.000000', $evaluation->worked_example_snapshot['after_tax_wage_increase']);
        $this->assertTrue($evaluation->ordering_assertions['delaying_carries_asset_health_penalty']);

        $this->assertSame(7, KpiSnapshot::query()->count());
        $this->assertSame(1, RankingSnapshot::query()->count());
        $this->assertSame(0, ConsequenceLink::query()->count());
        $this->assertSame(0, CohortFeedbackEffect::query()->count());
        $this->assertSame(0, StandingState::query()->count());

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertDontSee('faculty/halden_week13_FACULTY_SOLUTION.xlsx', false)
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
    private function week13RuntimeContext(): array
    {
        $graph = $this->tenantGraph('W13Smoke');
        $structure = $this->simulationStructure(13);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week13Definition */
        $week13Definition = $structure['simulationWeeks']->firstWhere('week_number', 13);

        app(SimulationContentActivationService::class)->activate(
            app(AuthoritativeContentPackageRegistrationService::class)->register($week13Definition, 'week13-runtime-smoke-v1'),
        );

        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week13Definition->id)
            ->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week13_factor_markets',
            'name' => 'Week 13 factor markets',
            'version' => Week13EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week13EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
            'field_key' => 'factor_market_strategy',
            'label' => 'Factor market strategy',
            'field_type' => DecisionFieldType::ShortText,
            'is_required' => true,
            'display_order' => 1,
            'help_text' => 'Package-backed Week 13 factor-market strategy note.',
            'validation' => ['max_length' => 255],
        ]);

        $memoDefinition = MemoDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week13_factor_markets_memo',
            'title' => 'Week 13 factor markets memo',
            'version' => Week13EconomicEngine::ENGINE_VERSION,
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
