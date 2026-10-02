<?php

namespace Tests\Feature\Week1;

use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageRegistrationService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Economics\Week1\Week1EconomicEngine;
use App\Domain\Economics\Week1\Week1EconomicEvaluationService;
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
use App\Models\Week1EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week1RuntimeIntegrationSmokeTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week1_runs_through_student_submission_and_faculty_execution(): void
    {
        $context = $this->week1RuntimeContext();
        $packageType = app(AuthoritativeContentPackageManifest::class)->packageType(1);

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertSee('halden_week1.xlsx', false)
            ->assertDontSee('faculty/halden_week1_FACULTY_SOLUTION.xlsx', false)
            ->assertDontSee('fixtures/week1_golden.json', false)
            ->assertInertia(fn ($page) => $page
                ->where('contentPackage.status', 'active')
                ->where('contentPackage.package_type', $packageType)
                ->where('decisionDefinition.fields.0.key', 'permian_rig_count')
                ->where('decisionDefinition.fields.1.key', 'rotterdam_review_posture')
                ->where('decisionDefinition.fields.2.key', 'first_meeting_choice')
                ->where('memoDefinition.title', 'Week 1 asset register memo')
                ->where('status.resolution_status', 'unresolved'));

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.decisions.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['decisionDefinition']->ulid,
                'answers' => [
                    'permian_rig_count' => 12,
                    'rotterdam_review_posture' => 'accelerate_review',
                    'first_meeting_choice' => 'vestergaard',
                ],
            ])
            ->assertRedirect();

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.memo.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['memoDefinition']->ulid,
                'body' => 'We rank the asset base by economic contribution, keep Rotterdam running short-run, and meet finance first.',
            ])
            ->assertRedirect();

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyWeekControl::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('runtimeWeekId', $context['runtimeWeek']->id)
            ->call('executeSelectedWeek')
            ->assertSee('completed')
            ->assertSee('deferred');

        $evaluation = Week1EconomicEvaluation::query()
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->where('section_simulation_week_id', $context['runtimeWeek']->id)
            ->firstOrFail();

        $this->assertSame(Week1EconomicEvaluation::STATUS_CALCULATED, $evaluation->status);
        $this->assertSame('week_execution_service', $evaluation->evaluated_by_process);
        $this->assertSame(Week1EconomicEngine::ENGINE_IDENTIFIER, $evaluation->engine_identifier);
        $this->assertSame(Week1EconomicEngine::ENGINE_VERSION, $evaluation->engine_version);
        $this->assertSame(Week1EconomicEvaluationService::PACKAGE_IDENTIFIER, $evaluation->package_identifier);
        $this->assertSame('1.0.0-draft', $evaluation->package_version);
        $this->assertSame('56.4000', $evaluation->permianMarginValue());
        $this->assertSame('-0.3000', $evaluation->rotterdamNetValue());
        $this->assertSame('Permian > Kessana > Baton Rouge > Norwegian > Singapore > Rotterdam', $evaluation->economic_rank);
        $this->assertSame('Permian > Baton Rouge > Kessana > Norwegian > Singapore > Rotterdam', $evaluation->reported_rank);
        $this->assertSame('12.000000', $evaluation->worked_example_snapshot['x_posttax']);
        $this->assertSame('vestergaard', $evaluation->input_snapshot['decision_submission']['answers']['first_meeting_choice']);
        $this->assertSame(Week1EconomicEngine::ENGINE_VERSION, $evaluation->input_snapshot['decision_submission']['definition_version']);

        $this->assertSame(0, KpiSnapshot::query()->count());
        $this->assertSame(0, RankingSnapshot::query()->count());
        $this->assertSame(0, ConsequenceLink::query()->count());
        $this->assertSame(0, CohortFeedbackEffect::query()->count());
        $this->assertSame(0, StandingState::query()->count());

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertDontSee('faculty/halden_week1_FACULTY_SOLUTION.xlsx', false)
            ->assertInertia(fn ($page) => $page
                ->where('decisionDefinition.status', 'submitted')
                ->where('memoDefinition.status', 'submitted')
                ->where('status.resolution_status', 'resolved'));
    }

    public function test_week1_can_activate_in_seven_week_variant_without_window_two_effects(): void
    {
        $context = $this->week1RuntimeContext('SevenWeek', sevenWeekVariant: true);

        $this->assertSame(7, $context['sectionSimulation']->version->variant->duration_weeks);
        $this->assertSame(1, $context['runtimeWeek']->definition->week_number);

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.decisions.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['decisionDefinition']->ulid,
                'answers' => [
                    'permian_rig_count' => 10,
                    'rotterdam_review_posture' => 'hold_review',
                    'first_meeting_choice' => 'delacroix',
                ],
            ])
            ->assertRedirect();

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.memo.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['memoDefinition']->ulid,
                'body' => 'Seven-week Week 1 still uses the authoritative Week 1 economics package.',
            ])
            ->assertRedirect();

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyWeekControl::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('runtimeWeekId', $context['runtimeWeek']->id)
            ->call('executeSelectedWeek')
            ->assertSee('completed');

        $this->assertSame(1, Week1EconomicEvaluation::query()->count());
        $this->assertSame(0, CohortFeedbackEffect::query()->count());
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
    private function week1RuntimeContext(string $suffix = 'Smoke', bool $sevenWeekVariant = false): array
    {
        $graph = $this->tenantGraph('W1'.$suffix);
        $structure = $this->simulationStructure(1);

        if ($sevenWeekVariant) {
            $structure['variant']->forceFill([
                'slug' => 'seven-week-w1-'.uniqid(),
                'name' => 'Seven-week executive variant',
                'duration_weeks' => 7,
            ])->save();
        }

        $sectionSimulation = $this->assignSimulation($graph, $structure['version']->refresh());
        /** @var SimulationWeek $week1Definition */
        $week1Definition = $structure['simulationWeeks']->firstWhere('week_number', 1);

        app(SimulationContentActivationService::class)->activate(
            app(AuthoritativeContentPackageRegistrationService::class)->register($week1Definition, 'week1-runtime-smoke-v1'),
        );

        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week1Definition->id)
            ->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week1_asset_register',
            'name' => 'Week 1 asset register decisions',
            'version' => Week1EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week1EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
            'field_key' => 'permian_rig_count',
            'label' => 'Permian rig count',
            'field_type' => DecisionFieldType::Integer,
            'is_required' => true,
            'display_order' => 1,
            'validation' => ['min' => 0],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
            'field_key' => 'rotterdam_review_posture',
            'label' => 'Rotterdam review posture',
            'field_type' => DecisionFieldType::Radio,
            'is_required' => true,
            'display_order' => 2,
            'options' => [
                ['value' => 'accelerate_review', 'label' => 'Accelerate review'],
                ['value' => 'hold_review', 'label' => 'Hold current posture'],
                ['value' => 'slow_review', 'label' => 'Slow review'],
            ],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
            'field_key' => 'first_meeting_choice',
            'label' => 'First meeting choice',
            'field_type' => DecisionFieldType::Radio,
            'is_required' => true,
            'display_order' => 3,
            'options' => [
                ['value' => 'delacroix', 'label' => 'COO Delacroix'],
                ['value' => 'vestergaard', 'label' => 'CFO Vestergaard'],
                ['value' => 'other', 'label' => 'Other stakeholder'],
            ],
        ]);

        $memoDefinition = MemoDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week1_asset_register_memo',
            'title' => 'Week 1 asset register memo',
            'version' => Week1EconomicEngine::ENGINE_VERSION,
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
