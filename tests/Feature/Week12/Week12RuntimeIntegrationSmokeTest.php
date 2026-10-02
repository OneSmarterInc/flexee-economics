<?php

namespace Tests\Feature\Week12;

use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageRegistrationService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Economics\Week12\Week12EconomicEngine;
use App\Domain\Economics\Week12\Week12EconomicEvaluationService;
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
use App\Models\Week12EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week12RuntimeIntegrationSmokeTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week12_runs_through_student_submission_and_faculty_execution(): void
    {
        $context = $this->week12RuntimeContext();
        $packageType = app(AuthoritativeContentPackageManifest::class)->packageType(12);

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertSee('halden_week12.xlsx', false)
            ->assertDontSee('faculty/halden_week12_FACULTY_SOLUTION.xlsx', false)
            ->assertInertia(fn ($page) => $page
                ->where('contentPackage.status', 'active')
                ->where('contentPackage.package_type', $packageType)
                ->where('decisionDefinition.fields.0.key', 'selected_project_keys')
                ->where('memoDefinition.title', 'Week 12 transition portfolio memo')
                ->where('status.resolution_status', 'unresolved'));

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.decisions.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['decisionDefinition']->ulid,
                'answers' => [
                    'selected_project_keys' => 'helix_rotterdam offshore_wind euro_retail_divest',
                ],
            ])
            ->assertRedirect();

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.memo.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['memoDefinition']->ulid,
                'body' => 'We divest European retail to fund Helix Rotterdam plus offshore wind without breaching the capital envelope.',
            ])
            ->assertRedirect();

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyWeekControl::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('runtimeWeekId', $context['runtimeWeek']->id)
            ->call('executeSelectedWeek')
            ->assertSee('completed')
            ->assertSee('deferred');

        $evaluation = Week12EconomicEvaluation::query()
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->where('section_simulation_week_id', $context['runtimeWeek']->id)
            ->firstOrFail();

        $this->assertSame(Week12EconomicEvaluation::STATUS_CALCULATED, $evaluation->status);
        $this->assertSame('week_execution_service', $evaluation->evaluated_by_process);
        $this->assertSame(Week12EconomicEngine::ENGINE_IDENTIFIER, $evaluation->engine_identifier);
        $this->assertSame(Week12EconomicEngine::ENGINE_VERSION, $evaluation->engine_version);
        $this->assertSame(Week12EconomicEvaluationService::PACKAGE_IDENTIFIER, $evaluation->package_identifier);
        $this->assertSame('1.0.0-draft', $evaluation->package_version);
        $this->assertSame(['helix_rotterdam', 'offshore_wind', 'euro_retail_divest'], $evaluation->selected_projects);
        $this->assertSame(['permian_expansion', 'biofuel_conversion'], $evaluation->rejected_projects);
        $this->assertTrue($evaluation->selected_portfolio_feasible);
        $this->assertTrue($evaluation->selected_includes_divestment);
        $this->assertTrue($evaluation->selected_unlocked_by_divestment);
        $this->assertSame('1750.0000', $evaluation->selectedCapitalRequiredValue());
        $this->assertSame(17, $evaluation->feasible_portfolio_count);
        $this->assertSame(3, $evaluation->feasible_with_helix_rotterdam_count);
        $this->assertSame(2, $evaluation->portfolios_unlocked_by_divestment_count);
        $this->assertSame('1750.000000', $evaluation->output_snapshot['selected_portfolio']['capital_required_musd']);
        $this->assertSame('90.000000', $evaluation->worked_example_snapshot['p1_high']);

        $this->assertSame(0, KpiSnapshot::query()->count());
        $this->assertSame(0, RankingSnapshot::query()->count());
        $this->assertSame(0, ConsequenceLink::query()->count());
        $this->assertSame(0, CohortFeedbackEffect::query()->count());
        $this->assertSame(0, StandingState::query()->count());

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertDontSee('faculty/halden_week12_FACULTY_SOLUTION.xlsx', false)
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
    private function week12RuntimeContext(): array
    {
        $graph = $this->tenantGraph('W12Smoke');
        $structure = $this->simulationStructure(12);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week12Definition */
        $week12Definition = $structure['simulationWeeks']->firstWhere('week_number', 12);

        app(SimulationContentActivationService::class)->activate(
            app(AuthoritativeContentPackageRegistrationService::class)->register($week12Definition, 'week12-runtime-smoke-v1'),
        );

        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week12Definition->id)
            ->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week12_transition_portfolio',
            'name' => 'Week 12 transition portfolio',
            'version' => Week12EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week12EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
            'field_key' => 'selected_project_keys',
            'label' => 'Selected portfolio project keys',
            'field_type' => DecisionFieldType::ShortText,
            'is_required' => true,
            'display_order' => 1,
            'help_text' => 'Space-separated project keys from the Week 12 package.',
            'validation' => ['max_length' => 255],
        ]);

        $memoDefinition = MemoDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week12_transition_portfolio_memo',
            'title' => 'Week 12 transition portfolio memo',
            'version' => Week12EconomicEngine::ENGINE_VERSION,
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
