<?php

namespace Tests\Feature\Week6;

use App\Domain\Capital\DiscountRateConsequenceService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Content\Week6\Week6ContentPackageManifest;
use App\Domain\Content\Week6\Week6ContentPackageRegistrationService;
use App\Domain\Economics\Resolution\WeekResolutionService;
use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Livewire\FacultyWeekControl;
use App\Models\CapitalAllocationDecision;
use App\Models\CapitalAllocationEvaluation;
use App\Models\CapitalProject;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DiscountRateConsequence;
use App\Models\DiscountRateSchedule;
use App\Models\KpiSnapshot;
use App\Models\MemoDefinition;
use App\Models\RankingSnapshot;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\TeamSimulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week6RuntimeActivationSmokeTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week6_runs_end_to_end_through_student_and_faculty_workspaces(): void
    {
        $context = $this->week6RuntimeContext();
        $golden = $this->week6Golden();

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['week6']))
            ->assertOk()
            ->assertSee('halden_week6.xlsx', false)
            ->assertDontSee('faculty/halden_week6_FACULTY_SOLUTION.xlsx', false)
            ->assertInertia(fn ($page) => $page
                ->where('contentPackage.status', 'active')
                ->where('contentPackage.package_type', Week6ContentPackageManifest::PACKAGE_TYPE)
                ->where('capitalAllocation.status', 'not_started')
                ->where('capitalAllocation.context.status', 'available')
                ->where('capitalAllocation.context.discount_rate_percent', '8.500')
                ->where('capitalAllocation.projects.0.key', 'baton_rouge')
                ->where('memoDefinition.title', 'Week 6 capital allocation memo')
                ->where('status.capital_allocation_status', 'not_started')
                ->where('status.complete', false)
                ->where('status.resolution_status', 'unresolved'));

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.capital-allocation.submit', $context['week6']), [
                'selected_project_keys' => ['baton_rouge', 'helix'],
            ])
            ->assertRedirect();

        $this->actingAs($context['graph']['student'])
            ->post(route('student.submissions.memo.submit', $context['week6']), [
                'definition_ulid' => $context['memoDefinition']->ulid,
                'body' => 'We selected Baton Rouge and Helix to compare near-term refining returns against longer-horizon transition optionality.',
            ])
            ->assertRedirect();

        $decision = CapitalAllocationDecision::query()
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->where('section_simulation_week_id', $context['week6']->id)
            ->firstOrFail();

        $this->assertSame(['baton_rouge', 'helix'], collect($decision->selectedProjectSnapshots())->pluck('key')->all());
        $this->assertSame(['rotterdam'], collect($decision->rejectedProjectSnapshots())->pluck('key')->all());

        Livewire::actingAs($context['graph']['faculty'])
            ->test(FacultyWeekControl::class)
            ->set('sectionSimulationId', $context['sectionSimulation']->id)
            ->set('runtimeWeekId', $context['week6']->id)
            ->call('executeSelectedWeek')
            ->assertSee('completed')
            ->assertSee('deferred');

        $evaluation = CapitalAllocationEvaluation::query()
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->where('section_simulation_week_id', $context['week6']->id)
            ->firstOrFail();

        $this->assertSame(CapitalAllocationEvaluation::STATUS_CALCULATED, $evaluation->status);
        $this->assertSame('week_execution_service', $evaluation->evaluated_by_process);
        $this->assertSame('492.927', $evaluation->portfolioNpvMusdValue());
        $this->assertSame('1520.000', $evaluation->capitalRequiredMusdValue());
        $this->assertFalse($evaluation->capital_envelope_feasible);
        $this->assertSame(number_format((float) $golden['npv_by_cohort']['base']['npv']['baton_rouge'], 2, '.', ''), $evaluation->output_snapshot['project_results']['baton_rouge']['npv_musd']);
        $this->assertSame(number_format((float) $golden['npv_by_cohort']['base']['npv']['helix'], 2, '.', ''), $evaluation->output_snapshot['project_results']['helix']['npv_musd']);
        $this->assertSame(number_format((float) ($golden['irr']['baton_rouge'] * 100), 2, '.', ''), $evaluation->output_snapshot['project_results']['baton_rouge']['irr_percent']);
        $this->assertSame(number_format((float) ($golden['irr']['helix'] * 100), 2, '.', ''), $evaluation->output_snapshot['project_results']['helix']['irr_percent']);
        $this->assertSame(['baton_rouge', 'helix'], $evaluation->output_snapshot['selected_project_keys']);
        $this->assertSame(7, KpiSnapshot::query()->where('section_simulation_week_id', $context['week6']->id)->count());
        $this->assertSame(1, RankingSnapshot::query()->where('section_simulation_week_id', $context['week6']->id)->count());

        $this->actingAs($context['graph']['student'])
            ->get(route('student.submissions.show', $context['week6']))
            ->assertOk()
            ->assertDontSee('faculty/halden_week6_FACULTY_SOLUTION.xlsx', false)
            ->assertInertia(fn ($page) => $page
                ->where('capitalAllocation.status', 'submitted')
                ->where('memoDefinition.status', 'submitted')
                ->where('status.capital_allocation_status', 'submitted')
                ->where('status.complete', true)
                ->where('status.resolution_status', 'resolved'));
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     week4: SectionSimulationWeek,
     *     week6: SectionSimulationWeek,
     *     memoDefinition: MemoDefinition,
     *     teamSimulation: TeamSimulation,
     *     discountRateConsequence: DiscountRateConsequence
     * }
     */
    private function week6RuntimeContext(): array
    {
        $graph = $this->tenantGraph('A');
        $structure = $this->simulationStructure(6);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week4Definition */
        $week4Definition = $structure['simulationWeeks']->firstWhere('week_number', 4);
        /** @var SimulationWeek $week6Definition */
        $week6Definition = $structure['simulationWeeks']->firstWhere('week_number', 6);
        /** @var SectionSimulationWeek $week4 */
        $week4 = $sectionSimulation->weeks()->where('simulation_week_id', $week4Definition->id)->firstOrFail();
        /** @var SectionSimulationWeek $week6 */
        $week6 = $sectionSimulation->weeks()->where('simulation_week_id', $week6Definition->id)->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $week4 = $lifecycle->transitionWeek($week4, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $week4 = $lifecycle->transitionWeek($week4->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());
        $week6 = $lifecycle->transitionWeek($week6, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $week6 = $lifecycle->transitionWeek($week6->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        app(SimulationContentActivationService::class)->activate(
            app(Week6ContentPackageRegistrationService::class)->register($week6Definition, 'week6-runtime-smoke-v1'),
        );

        $decisionDefinition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $week4->simulation_version_id,
            'simulation_week_id' => $week4->simulation_week_id,
            'key' => 'week4_transfer_pricing',
            'name' => 'Week 4 transfer pricing',
            'version' => Week4EconomicEngine::ENGINE_VERSION,
            'metadata' => ['economic_engine' => Week4EconomicEngine::ENGINE_IDENTIFIER],
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
            'field_key' => 'transfer_price',
            'label' => 'Transfer price',
            'field_type' => DecisionFieldType::Currency,
            'is_required' => true,
            'display_order' => 1,
            'validation' => ['min' => 0, 'max' => 250],
        ]);

        /** @var MemoDefinition $memoDefinition */
        $memoDefinition = MemoDefinition::factory()->create([
            'simulation_version_id' => $week6->simulation_version_id,
            'simulation_week_id' => $week6->simulation_week_id,
            'key' => 'week6_capital_allocation_memo',
            'title' => 'Week 6 capital allocation memo',
            'version' => 'week6_reference_package_v1',
            'is_required' => true,
            'character_limit' => 4000,
        ]);

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        $submission = app(SubmissionService::class)->submitDecision(
            $graph['student'],
            $week4,
            $teamSimulation,
            $decisionDefinition,
            ['transfer_price' => '46.20'],
        );
        $resolution = app(WeekResolutionService::class)->resolveSubmittedDecision($submission, $graph['student']);
        $discountRateConsequence = app(DiscountRateConsequenceService::class)->resolve(
            $resolution,
            $this->discountRateSchedule(),
            $graph['faculty'],
        );

        $this->seedWeek6PackageProjects();

        return compact('graph', 'sectionSimulation', 'week4', 'week6', 'memoDefinition', 'teamSimulation', 'discountRateConsequence');
    }

    private function seedWeek6PackageProjects(): void
    {
        foreach ([
            ['key' => 'baton_rouge', 'name' => 'Baton Rouge Upgrade', 'category' => 'refining', 'risk_class' => 'refining_upgrade'],
            ['key' => 'rotterdam', 'name' => 'Rotterdam Upgrade', 'category' => 'refining', 'risk_class' => 'refining_upgrade'],
            ['key' => 'helix', 'name' => 'Project Helix', 'category' => 'transition', 'risk_class' => 'adjacent_transition'],
        ] as $project) {
            CapitalProject::query()->create([
                ...$project,
                'version' => 'week6_reference_package_v1',
                'cash_flow_reference' => 'halden-week6-data-package/data/project_cashflows.csv#'.$project['key'],
                'required_inputs' => ['requires_week6_reference_package' => true],
                'metadata' => ['package_root' => 'halden-week6-data-package'],
                'is_active' => true,
            ]);
        }
    }

    private function discountRateSchedule(): DiscountRateSchedule
    {
        return DiscountRateSchedule::query()->create([
            'key' => 'week4_to_week6_discount_rate',
            'name' => 'Week 4 to Week 6 discount rate',
            'version' => 'discount_rate_v1_'.uniqid(),
            'source_week_number' => 4,
            'target_week_number' => 6,
            'classification_rules' => [
                ['field' => 'geneva_capture_per_bbl', 'operator' => '<=', 'value' => '9.625', 'classification' => 'base'],
            ],
            'classification_outcomes' => [
                'base' => [
                    'discount_rate_percent' => '8.5',
                    'capital_envelope_musd' => '1150',
                ],
            ],
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function week6Golden(): array
    {
        $contents = file_get_contents(base_path('halden-week6-data-package/fixtures/week6_golden.json'));

        $this->assertIsString($contents);

        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($decoded);

        return $decoded;
    }
}
