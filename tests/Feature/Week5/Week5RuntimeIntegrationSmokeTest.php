<?php

namespace Tests\Feature\Week5;

use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageRegistrationService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Economics\Week5\Week5EconomicEngine;
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
use App\Models\Week5EconomicEvaluation;
use App\Models\WeekExecutionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week5RuntimeIntegrationSmokeTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week5_runs_through_student_submission_and_faculty_execution(): void
    {
        $context = $this->week5RuntimeContext();
        $packageType = app(AuthoritativeContentPackageManifest::class)->packageType(5);

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertSee('halden_week5.xlsx', false)
            ->assertDontSee('faculty/halden_week5_FACULTY_SOLUTION.xlsx', false)
            ->assertInertia(fn ($page) => $page
                ->where('contentPackage.status', 'active')
                ->where('contentPackage.package_type', $packageType)
                ->where('decisionDefinition.fields.0.key', 'crude_hedge_coverage')
                ->where('memoDefinition.title', 'Week 5 currency exposure memo')
                ->where('status.resolution_status', 'unresolved'));

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.decisions.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['decisionDefinition']->ulid,
                'answers' => [
                    'crude_hedge_coverage' => '0.45',
                    'hedging_policy' => 'Keep entity net exposures visible and avoid hedging Rotterdam gross EUR costs.',
                ],
            ])
            ->assertRedirect();

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.memo.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['memoDefinition']->ulid,
                'body' => 'We distinguish translation exposure, transaction exposure, and natural hedge effects before changing coverage.',
            ])
            ->assertRedirect();

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyWeekControl::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('runtimeWeekId', $context['runtimeWeek']->id)
            ->call('executeSelectedWeek')
            ->assertSee('completed')
            ->assertSee('deferred');

        $evaluation = Week5EconomicEvaluation::query()
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->where('section_simulation_week_id', $context['runtimeWeek']->id)
            ->firstOrFail();

        $this->assertSame(Week5EconomicEvaluation::STATUS_CALCULATED, $evaluation->status);
        $this->assertSame('week_execution_service', $evaluation->evaluated_by_process);
        $this->assertSame(Week5EconomicEngine::ENGINE_IDENTIFIER, $evaluation->engine_identifier);
        $this->assertSame(Week5EconomicEngine::ENGINE_VERSION, $evaluation->engine_version);
        $this->assertSame('1.0.0-draft', $evaluation->package_version);
        $this->assertSame('-0.064516', $evaluation->eur_change);
        $this->assertSame('84.615385', $evaluation->norway_benefit_musd);
        $this->assertSame('25.846154', $evaluation->norwayLiftingPostValue());
        $this->assertSame('-145.161290', $evaluation->rotOverhedgeLossValue());
        $this->assertSame('0.450000', $evaluation->output_snapshot['week10_inherited_state']['crude_hedge_coverage']);
        $this->assertSame('Keep entity net exposures visible and avoid hedging Rotterdam gross EUR costs.', $evaluation->decision_snapshot['answers']['hedging_policy']);

        $record = WeekExecutionRecord::query()
            ->where('tenant_id', $context['runtimeWeek']->tenant_id)
            ->where('section_simulation_week_id', $context['runtimeWeek']->id)
            ->firstOrFail();
        $this->assertSame(1, $record->outputs['resolve_decisions']['week5_economic_evaluation_count']);
        $this->assertSame(1, $record->outputs['resolve_decisions']['evaluation_status_counts'][Week5EconomicEvaluation::STATUS_CALCULATED]);

        $this->assertSame(0, KpiSnapshot::query()->where('section_simulation_week_id', $context['runtimeWeek']->id)->count());
        $this->assertSame(0, RankingSnapshot::query()->where('section_simulation_week_id', $context['runtimeWeek']->id)->count());
        $this->assertSame(0, ConsequenceLink::query()->where('source_section_simulation_week_id', $context['runtimeWeek']->id)->count());
        $this->assertSame(0, CohortFeedbackEffect::query()->count());

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertDontSee('faculty/halden_week5_FACULTY_SOLUTION.xlsx', false)
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
    private function week5RuntimeContext(): array
    {
        $graph = $this->tenantGraph('W5');
        $structure = $this->simulationStructure(5);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week5Definition */
        $week5Definition = $structure['simulationWeeks']->firstWhere('week_number', 5);

        app(SimulationContentActivationService::class)->activate(
            app(AuthoritativeContentPackageRegistrationService::class)->register($week5Definition, 'week5-runtime-smoke-v1'),
        );

        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week5Definition->id)
            ->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week5_currency_exposure',
            'name' => 'Week 5 currency exposure decision',
            'version' => Week5EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week5EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
            'field_key' => 'crude_hedge_coverage',
            'label' => 'Crude hedge coverage',
            'field_type' => DecisionFieldType::Decimal,
            'is_required' => true,
            'display_order' => 1,
            'validation' => ['min' => 0, 'max' => 1],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
            'field_key' => 'hedging_policy',
            'label' => 'Hedging policy',
            'field_type' => DecisionFieldType::ShortText,
            'is_required' => false,
            'display_order' => 2,
            'validation' => ['max_length' => 200],
        ]);

        $memoDefinition = MemoDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week5_currency_exposure_memo',
            'title' => 'Week 5 currency exposure memo',
            'version' => Week5EconomicEngine::ENGINE_VERSION,
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
