<?php

namespace Tests\Feature\Week9;

use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageRegistrationService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Economics\Week9\Week9EconomicEngine;
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
use App\Models\Week9EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week9RuntimeIntegrationSmokeTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week9_runs_through_student_submission_and_faculty_execution(): void
    {
        $context = $this->week9RuntimeContext();
        $packageType = app(AuthoritativeContentPackageManifest::class)->packageType(9);

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertSee('halden_week9.xlsx', false)
            ->assertDontSee('faculty/halden_week9_FACULTY_SOLUTION.xlsx', false)
            ->assertInertia(fn ($page) => $page
                ->where('contentPackage.status', 'active')
                ->where('contentPackage.package_type', $packageType)
                ->where('decisionDefinition.fields.0.key', 'rebrand_LA_MS_core')
                ->where('memoDefinition.title', 'Week 9 Cordell rebrand memo')
                ->where('status.resolution_status', 'unresolved'));

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.decisions.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['decisionDefinition']->ulid,
                'answers' => [
                    'rebrand_LA_MS_core' => false,
                    'rebrand_gulf_secondary' => true,
                    'rebrand_southeast_edge' => true,
                    'nonfuel_state_key' => 'base',
                ],
            ])
            ->assertRedirect();

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.memo.submit', $context['runtimeWeek']), [
                'definition_ulid' => $context['memoDefinition']->ulid,
                'body' => 'We keep Cordell in the core and rebrand the positive-payback secondary and edge markets.',
            ])
            ->assertRedirect();

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyWeekControl::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('runtimeWeekId', $context['runtimeWeek']->id)
            ->call('executeSelectedWeek')
            ->assertSee('completed')
            ->assertSee('deferred');

        $evaluation = Week9EconomicEvaluation::query()
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->where('section_simulation_week_id', $context['runtimeWeek']->id)
            ->firstOrFail();

        $this->assertSame(Week9EconomicEvaluation::STATUS_CALCULATED, $evaluation->status);
        $this->assertSame('week_execution_service', $evaluation->evaluated_by_process);
        $this->assertSame(Week9EconomicEngine::ENGINE_IDENTIFIER, $evaluation->engine_identifier);
        $this->assertSame(Week9EconomicEngine::ENGINE_VERSION, $evaluation->engine_version);
        $this->assertSame('1.0.0-draft', $evaluation->package_version);
        $this->assertSame('base', $evaluation->nonfuel_state_key);
        $this->assertSame(['gulf_secondary', 'southeast_edge'], $evaluation->selected_rebrand_markets);
        $this->assertSame('22.6800', $evaluation->partialGainValue());
        $this->assertSame('8.5415', $evaluation->partialPaybackValue());
        $this->assertSame('7.6950', $evaluation->fullNetGainValue());
        $this->assertSame('9.440591', $evaluation->output_snapshot['pricewar_partial_payback_years']);
        $this->assertSame('180000.000000', $evaluation->input_snapshot['rebrand_parameters']['fills_per_site_year']);

        $this->assertSame(7, KpiSnapshot::query()->where('section_simulation_week_id', $context['runtimeWeek']->id)->count());
        $this->assertSame(1, RankingSnapshot::query()->where('section_simulation_week_id', $context['runtimeWeek']->id)->count());
        $this->assertSame(0, ConsequenceLink::query()->where('source_section_simulation_week_id', $context['runtimeWeek']->id)->count());
        $this->assertSame(0, CohortFeedbackEffect::query()->count());

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertDontSee('faculty/halden_week9_FACULTY_SOLUTION.xlsx', false)
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
    private function week9RuntimeContext(): array
    {
        $graph = $this->tenantGraph('A');
        $structure = $this->simulationStructure(9);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week9Definition */
        $week9Definition = $structure['simulationWeeks']->firstWhere('week_number', 9);

        app(SimulationContentActivationService::class)->activate(
            app(AuthoritativeContentPackageRegistrationService::class)->register($week9Definition, 'week9-runtime-smoke-v1'),
        );

        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week9Definition->id)
            ->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = DecisionFormDefinition::factory()->create([
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
                'decision_form_definition_id' => $decisionDefinition->id,
                'field_key' => $key,
                'label' => $label,
                'field_type' => DecisionFieldType::Boolean,
                'is_required' => false,
                'display_order' => $order,
            ]);
        }

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
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

        $memoDefinition = MemoDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week9_cordell_rebrand_memo',
            'title' => 'Week 9 Cordell rebrand memo',
            'version' => Week9EconomicEngine::ENGINE_VERSION,
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
