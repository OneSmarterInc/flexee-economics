<?php

namespace Tests\Feature\CohortFeedback;

use App\Domain\Capital\CapitalAllocationService;
use App\Domain\CohortFeedback\CohortFeedbackService;
use App\Domain\CohortFeedback\Window2CohortPackage;
use App\Domain\CohortFeedback\Window2CohortResponseFunctionCatalog;
use App\Domain\Economics\Week8\Week8EconomicEvaluationService;
use App\Domain\Execution\WeekExecutionService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\SectionSimulationWeekStatus;
use App\Enums\SubmissionStatus;
use App\Models\CapitalProject;
use App\Models\CohortFeedbackEffect;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\Enrollment;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use App\Models\Week8EconomicEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Window2CohortFeedbackTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    #[DataProvider('cohortStateProvider')]
    public function test_window2_cohort_states_match_golden_targets(string $state, array $batonRougeSelections, string $expectedShift, string $expectedMargin): void
    {
        $context = $this->window2Context($state, count($batonRougeSelections));
        $this->submitWeek6Allocations($context, $batonRougeSelections);
        $this->closeWeek($context['week6'], $context['graph']['faculty']);

        $aggregate = app(CohortFeedbackService::class)->resolveForSectionWeek($context['function'], $context['week6']->refresh(), $context['graph']['faculty']);
        $effect = CohortFeedbackEffect::query()
            ->where('section_simulation_id', $context['sectionSimulation']->id)
            ->firstOrFail();

        $this->assertSame((string) count($batonRougeSelections), (string) $aggregate->aggregate_snapshot['decision_count']);
        $this->assertSame($expectedShift, $effect->effect_snapshot['response']['bounded_value']);
        $this->assertSame('window2_pivoted_share_v1', $effect->effect_snapshot['response']['calculation']);
        $this->assertSame('21.50', $effect->effect_snapshot['response']['parallel_universe_baseline']);
        $this->assertSame(Window2CohortResponseFunctionCatalog::EFFECT_KEY, $effect->effect_key);

        $actualMargin = number_format(20.35 + (float) $expectedShift, 2, '.', '');
        $this->assertSame($expectedMargin, $actualMargin);
    }

    public function test_window2_package_provenance_and_registration_are_authoritative(): void
    {
        $package = Window2CohortPackage::fromRepository();
        $function = Window2CohortResponseFunctionCatalog::fromRepository()->register();

        $this->assertSame([], $package->validateProvenance());
        $this->assertSame('1.0.0-draft', $package->version());
        $this->assertSame(6, $function->source_week_number);
        $this->assertSame(8, $function->target_week_number);
        $this->assertSame('capital_allocation', $function->inputDefinition()['source']);
        $this->assertSame('selected_indicator', $function->inputDefinition()['project_metric']);
        $this->assertSame('-3.23', $function->boundsDefinition()['min']);
        $this->assertSame('3.23', $function->boundsDefinition()['max']);
        $this->assertSame([7], $function->parameterDefinition()['excluded_variant_duration_weeks']);
    }

    public function test_week8_evaluation_applies_window2_shift_separately_from_opec_shock(): void
    {
        $context = $this->window2Context('Week8Shift', 4);
        $this->submitWeek6Allocations($context, [true, true, true, true]);
        $this->closeWeek($context['week6'], $context['graph']['faculty']);

        app(CohortFeedbackService::class)->resolveForSectionWeek($context['function'], $context['week6']->refresh(), $context['graph']['faculty']);

        $submission = $this->week8Submission($context, $context['teams'][0]['teamSimulation'], $context['teams'][0]['student']);
        $evaluation = app(Week8EconomicEvaluationService::class)->evaluate($submission, $context['graph']['faculty']);

        $this->assertSame(Week8EconomicEvaluation::STATUS_CALCULATED, $evaluation->status);
        $this->assertSame('16.16', (string) $evaluation->expected_refining_crack);
        $this->assertSame('13.60', (string) $evaluation->realized_refining_crack);
        $this->assertSame('-3.00', $evaluation->input_snapshot['cohort_adjustment']['refining_crack_shift']);
        $this->assertSame('16.60', $evaluation->realization_snapshot['opec_refining_crack']);
        $this->assertSame('-3.00', $evaluation->realization_snapshot['cohort_refining_crack_shift']);
        $this->assertSame('13.60', $evaluation->realization_snapshot['refining_crack']);
    }

    public function test_window2_effect_is_market_wide_but_section_and_tenant_isolated(): void
    {
        $first = $this->window2Context('IsolationA', 2);
        $second = $this->window2Context('IsolationB', 2);

        $this->submitWeek6Allocations($first, [true, true]);
        $this->submitWeek6Allocations($second, [false, false]);
        $this->closeWeek($first['week6'], $first['graph']['faculty']);
        $this->closeWeek($second['week6'], $second['graph']['faculty']);

        app(CohortFeedbackService::class)->resolveForSectionWeek($first['function'], $first['week6']->refresh(), $first['graph']['faculty']);
        app(CohortFeedbackService::class)->resolveForSectionWeek($second['function'], $second['week6']->refresh(), $second['graph']['faculty']);

        $firstEvaluation = app(Week8EconomicEvaluationService::class)->evaluate(
            $this->week8Submission($first, $first['teams'][0]['teamSimulation'], $first['teams'][0]['student']),
            $first['graph']['faculty'],
        );
        $secondEvaluation = app(Week8EconomicEvaluationService::class)->evaluate(
            $this->week8Submission($second, $second['teams'][0]['teamSimulation'], $second['teams'][0]['student']),
            $second['graph']['faculty'],
        );
        $firstPeerEvaluation = app(Week8EconomicEvaluationService::class)->evaluate(
            $this->week8Submission($first, $first['teams'][1]['teamSimulation'], $first['teams'][1]['student']),
            $first['graph']['faculty'],
        );

        $this->assertSame('-3.00', $firstEvaluation->input_snapshot['cohort_adjustment']['refining_crack_shift']);
        $this->assertSame('-3.00', $firstPeerEvaluation->input_snapshot['cohort_adjustment']['refining_crack_shift']);
        $this->assertSame('1.50', $secondEvaluation->input_snapshot['cohort_adjustment']['refining_crack_shift']);
        $this->assertNotSame($firstEvaluation->tenant_id, $secondEvaluation->tenant_id);
    }

    public function test_window2_effect_is_hidden_until_week8_visibility_context(): void
    {
        $context = $this->window2Context('Hidden', 2);
        $this->submitWeek6Allocations($context, [true, false]);
        $this->closeWeek($context['week6'], $context['graph']['faculty']);
        app(CohortFeedbackService::class)->resolveForSectionWeek($context['function'], $context['week6']->refresh(), $context['graph']['faculty']);

        $this->assertCount(0, app(CohortFeedbackService::class)->visibleEffectsForStudent($context['teams'][0]['student'], $context['week6']));
        $this->assertCount(0, app(CohortFeedbackService::class)->visibleEffectsForStudent($context['teams'][0]['student'], $context['week7']));
        $this->assertCount(1, app(CohortFeedbackService::class)->visibleEffectsForStudent($context['teams'][0]['student'], $context['week8']));
    }

    public function test_window2_response_function_is_excluded_from_seven_week_variant(): void
    {
        $context = $this->window2Context('SevenWeek', 2, 7);
        $method = new ReflectionMethod(WeekExecutionService::class, 'cohortFunctionExcludedForRuntime');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(app(WeekExecutionService::class), $context['function'], $context['week6']));
    }

    /**
     * @return iterable<string, array{string, list<bool>, string, string}>
     */
    public static function cohortStateProvider(): iterable
    {
        yield 'all add' => ['all_add', [true, true, true, true], '-3.000000', '17.35'];
        yield 'most add' => ['most_add', [true, true, true, false], '-1.500000', '18.85'];
        yield 'split' => ['split', [true, true, false, false], '0.000000', '20.35'];
        yield 'few add' => ['few_add', [true, false, false, false], '0.750000', '21.10'];
        yield 'none add' => ['none_add', [false, false, false, false], '1.500000', '21.85'];
    }

    /**
     * @return array<string, mixed>
     */
    private function window2Context(string $suffix, int $teamCount, ?int $variantDurationWeeks = null): array
    {
        $this->seedCapitalProjects();
        $graph = $this->tenantGraph($suffix);
        $teams = [['student' => $graph['student'], 'team' => $graph['team']]];

        foreach (range(2, $teamCount) as $index) {
            $teams[] = $this->addTeam($graph, $suffix, $index);
        }

        $structure = $this->simulationStructure(8);

        if ($variantDurationWeeks !== null) {
            $structure['variant']->forceFill(['duration_weeks' => $variantDurationWeeks])->save();
        }

        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week6Definition */
        $week6Definition = $structure['simulationWeeks']->firstWhere('week_number', 6);
        /** @var SimulationWeek $week7Definition */
        $week7Definition = $structure['simulationWeeks']->firstWhere('week_number', 7);
        /** @var SimulationWeek $week8Definition */
        $week8Definition = $structure['simulationWeeks']->firstWhere('week_number', 8);
        $week6 = $this->runtimeWeek($sectionSimulation, $week6Definition);
        $week7 = $this->runtimeWeek($sectionSimulation, $week7Definition);
        $week8 = $this->runtimeWeek($sectionSimulation, $week8Definition);
        $lifecycle = app(SimulationLifecycleService::class);
        $week6 = $lifecycle->transitionWeek($week6, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $week6 = $lifecycle->transitionWeek($week6->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        foreach ($teams as $key => $team) {
            $teams[$key]['teamSimulation'] = TeamSimulation::query()
                ->where('section_simulation_id', $sectionSimulation->id)
                ->where('team_id', $team['team']->id)
                ->firstOrFail();
        }

        $function = Window2CohortResponseFunctionCatalog::fromRepository()->register();

        return compact('graph', 'sectionSimulation', 'week6', 'week7', 'week8', 'teams', 'function');
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<bool>  $batonRougeSelections
     */
    private function submitWeek6Allocations(array $context, array $batonRougeSelections): void
    {
        foreach ($batonRougeSelections as $index => $fundBatonRouge) {
            $team = $context['teams'][$index];
            $selected = $fundBatonRouge ? ['baton_rouge', 'helix'] : ['helix'];
            $rejected = $fundBatonRouge ? ['rotterdam'] : ['baton_rouge', 'rotterdam'];

            app(CapitalAllocationService::class)->submitAllocation(
                $team['student'],
                $team['teamSimulation'],
                $context['week6'],
                $selected,
                $rejected,
            );
        }
    }

    private function closeWeek(SectionSimulationWeek $week, User $faculty): void
    {
        app(SimulationLifecycleService::class)->transitionWeek($week->refresh(), SectionSimulationWeekStatus::Closed, $faculty);
    }

    private function week8Submission(array $context, TeamSimulation $teamSimulation, User $student): DecisionSubmission
    {
        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $context['week8']->simulation_version_id,
            'simulation_week_id' => $context['week8']->simulation_week_id,
            'key' => 'week8_opec_prediction_'.$teamSimulation->id,
        ]);

        return DecisionSubmission::query()->create([
            'tenant_id' => $teamSimulation->tenant_id,
            'section_simulation_id' => $teamSimulation->section_simulation_id,
            'section_simulation_week_id' => $context['week8']->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_form_definition_id' => $definition->id,
            'status' => SubmissionStatus::Submitted->value,
            'answers' => [
                'probability_holds_full' => '0.35',
                'probability_holds_partial' => '0.40',
                'probability_fails' => '0.25',
                'realized_scenario_key' => 'holds_full',
            ],
            'lock_version' => 1,
            'submitted_by_user_id' => $student->id,
            'submitted_at' => now(),
        ]);
    }

    /**
     * @return array{student: User, team: Team}
     */
    private function addTeam(array $graph, string $suffix, int $index): array
    {
        $student = User::factory()->student()->create([
            'tenant_id' => $graph['tenant']->id,
            'email' => 'window2-'.$suffix.'-'.$index.'@example.test',
        ]);
        $team = Team::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'name' => 'Window 2 team '.$suffix.' '.$index,
            'slug' => 'window2-'.strtolower($suffix).'-'.$index,
        ]);

        Enrollment::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'user_id' => $student->id,
            'status' => 'active',
        ]);

        TeamMember::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'team_id' => $team->id,
            'user_id' => $student->id,
        ]);

        return compact('student', 'team');
    }

    private function runtimeWeek(SectionSimulation $sectionSimulation, SimulationWeek $definition): SectionSimulationWeek
    {
        return $sectionSimulation->weeks()
            ->where('simulation_week_id', $definition->id)
            ->firstOrFail();
    }

    private function seedCapitalProjects(): void
    {
        foreach ([
            ['key' => 'baton_rouge', 'name' => 'Baton Rouge Crude Flexibility Upgrade', 'category' => 'refining', 'risk_class' => 'refining_upgrade'],
            ['key' => 'rotterdam', 'name' => 'Rotterdam Upgrade', 'category' => 'refining', 'risk_class' => 'refining_upgrade'],
            ['key' => 'helix', 'name' => 'Project Helix', 'category' => 'transition', 'risk_class' => 'adjacent_transition'],
        ] as $project) {
            CapitalProject::query()->firstOrCreate([
                'key' => $project['key'],
                'version' => 'week6_reference_package_v1',
            ], [
                'name' => $project['name'],
                'category' => $project['category'],
                'cash_flow_reference' => 'halden-week6-data-package/data/project_cashflows.csv#'.$project['key'],
                'risk_class' => $project['risk_class'],
                'required_inputs' => ['requires_week6_reference_package' => true],
                'metadata' => ['package_root' => 'halden-week6-data-package'],
                'is_active' => true,
            ]);
        }
    }
}
