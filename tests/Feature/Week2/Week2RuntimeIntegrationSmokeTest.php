<?php

namespace Tests\Feature\Week2;

use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageRegistrationService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Economics\Week2\Week2EconomicEngine;
use App\Domain\Economics\Week2\Week2EconomicEvaluationService;
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
use App\Models\Week2EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week2RuntimeIntegrationSmokeTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week2_runs_through_student_submission_and_faculty_execution(): void
    {
        $context = $this->week2RuntimeContext();
        $packageType = app(AuthoritativeContentPackageManifest::class)->packageType(2);

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertSee('halden_week2.xlsx', false)
            ->assertDontSee('faculty/halden_week2_FACULTY_SOLUTION.xlsx', false)
            ->assertInertia(fn ($page) => $page
                ->where('contentPackage.status', 'active')
                ->where('contentPackage.package_type', $packageType)
                ->where('decisionDefinition.fields.0.key', 'est_urban_high_comp')
                ->where('decisionDefinition.fields.9.key', 'pricing_strategy')
                ->where('memoDefinition.title', 'Week 2 elasticity memo')
                ->where('status.resolution_status', 'unresolved'));

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.decisions.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['decisionDefinition']->ulid,
                'answers' => [
                    'est_urban_high_comp' => '-0.1094',
                    'est_suburban_mid_comp' => '-0.0605',
                    'est_rural_low_comp' => '-0.0195',
                    'est_interstate' => '-0.0494',
                    'est_netherlands_urban' => '-0.0678',
                    'est_belgium_mixed' => '-0.0499',
                    'est_germany_border' => '-0.1289',
                    'predicted_volume_response_urban_high_comp' => '0.1401',
                    'predicted_volume_response_rural_low_comp' => '0.0128',
                    'pricing_strategy' => 'Target elastic urban stations and avoid broad rural cuts.',
                ],
            ])
            ->assertRedirect();

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.memo.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['memoDefinition']->ulid,
                'body' => 'We estimate elasticity by cluster from log price/log volume and use pass-through to forecast the rack-cut volume response.',
            ])
            ->assertRedirect();

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyWeekControl::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('runtimeWeekId', $context['runtimeWeek']->id)
            ->call('executeSelectedWeek')
            ->assertSee('completed')
            ->assertSee('deferred');

        $evaluation = Week2EconomicEvaluation::query()
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->where('section_simulation_week_id', $context['runtimeWeek']->id)
            ->firstOrFail();

        $this->assertSame(Week2EconomicEvaluation::STATUS_CALCULATED, $evaluation->status);
        $this->assertSame('week_execution_service', $evaluation->evaluated_by_process);
        $this->assertSame(Week2EconomicEngine::ENGINE_IDENTIFIER, $evaluation->engine_identifier);
        $this->assertSame(Week2EconomicEngine::ENGINE_VERSION, $evaluation->engine_version);
        $this->assertSame(Week2EconomicEvaluationService::PACKAGE_IDENTIFIER, $evaluation->package_identifier);
        $this->assertSame('1.0.0-draft', $evaluation->package_version);
        $this->assertSame('-0.059364', $evaluation->cordell_weighted_est);
        $this->assertSame('-0.083805', $evaluation->europe_weighted_est);
        $this->assertSame('0.603500', $evaluation->cordell_weighted_passthrough);
        $this->assertSame('0.140146', $evaluation->urban_high_comp_volume_response_pct);
        $this->assertSame('0.012814', $evaluation->rural_low_comp_volume_response_pct);
        $this->assertSame('-0.079964', $evaluation->worked_elasticity);
        $this->assertSame('0.074967', $evaluation->worked_vol_response_pct);
        $this->assertSame('Target elastic urban stations and avoid broad rural cuts.', $evaluation->decision_snapshot['answers']['pricing_strategy']);

        $this->assertSame(7, KpiSnapshot::query()->count());
        $this->assertSame(1, RankingSnapshot::query()->count());
        $this->assertSame(0, ConsequenceLink::query()->count());
        $this->assertSame(0, CohortFeedbackEffect::query()->count());

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertDontSee('faculty/halden_week2_FACULTY_SOLUTION.xlsx', false)
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
    private function week2RuntimeContext(): array
    {
        $graph = $this->tenantGraph('W2Smoke');
        $structure = $this->simulationStructure(2);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week2Definition */
        $week2Definition = $structure['simulationWeeks']->firstWhere('week_number', 2);

        app(SimulationContentActivationService::class)->activate(
            app(AuthoritativeContentPackageRegistrationService::class)->register($week2Definition, 'week2-runtime-smoke-v1'),
        );

        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week2Definition->id)
            ->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week2_elasticity_estimation',
            'name' => 'Week 2 elasticity estimation',
            'version' => Week2EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week2EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        foreach ([
            ['est_urban_high_comp', 'Urban high-competition elasticity estimate', 1],
            ['est_suburban_mid_comp', 'Suburban mid-competition elasticity estimate', 2],
            ['est_rural_low_comp', 'Rural low-competition elasticity estimate', 3],
            ['est_interstate', 'Interstate elasticity estimate', 4],
            ['est_netherlands_urban', 'Netherlands urban elasticity estimate', 5],
            ['est_belgium_mixed', 'Belgium mixed elasticity estimate', 6],
            ['est_germany_border', 'Germany border elasticity estimate', 7],
            ['predicted_volume_response_urban_high_comp', 'Predicted urban volume response', 8],
            ['predicted_volume_response_rural_low_comp', 'Predicted rural volume response', 9],
        ] as [$key, $label, $order]) {
            DecisionFieldDefinition::factory()->create([
                'decision_form_definition_id' => $decisionDefinition->id,
                'field_key' => $key,
                'label' => $label,
                'field_type' => DecisionFieldType::Decimal,
                'is_required' => true,
                'display_order' => $order,
                'validation' => ['min' => -1, 'max' => 10],
            ]);
        }

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
            'field_key' => 'pricing_strategy',
            'label' => 'Pricing strategy',
            'field_type' => DecisionFieldType::ShortText,
            'is_required' => false,
            'display_order' => 10,
            'validation' => ['max_length' => 255],
        ]);

        $memoDefinition = MemoDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week2_elasticity_estimation_memo',
            'title' => 'Week 2 elasticity memo',
            'version' => Week2EconomicEngine::ENGINE_VERSION,
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
