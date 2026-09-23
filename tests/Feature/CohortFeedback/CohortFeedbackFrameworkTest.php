<?php

namespace Tests\Feature\CohortFeedback;

use App\Domain\CohortFeedback\CohortFeedbackService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\CohortDecisionAggregate;
use App\Models\CohortFeedbackEffect;
use App\Models\CohortResponseFunction;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\Enrollment;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class CohortFeedbackFrameworkTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_closed_source_week_aggregates_cohort_decisions_and_applies_future_effect(): void
    {
        $context = $this->cohortContext();
        $this->submitRunRate($context['graph']['student'], $context['sourceWeek'], $context['teamSimulation'], $context['decisionDefinition'], '80');
        $this->submitRunRate($context['otherStudent'], $context['sourceWeek'], $context['otherTeamSimulation'], $context['decisionDefinition'], '100');
        $this->closeSourceWeek($context);

        $aggregate = app(CohortFeedbackService::class)->resolveForSectionWeek(
            $context['function'],
            $context['sourceWeek']->refresh(),
            $context['graph']['faculty'],
        );
        $effect = CohortFeedbackEffect::query()->firstOrFail();

        $this->assertSame(2, $aggregate->aggregate_snapshot['decision_count']);
        $this->assertSame('90.000000', $aggregate->aggregate_snapshot['value']);
        $this->assertSame('10.000000', $aggregate->response_snapshot['bounded_value']);
        $this->assertSame($context['targetWeek']->id, $effect->target_section_simulation_week_id);
        $this->assertSame('nwe_crack_adjustment', $effect->effect_key);
        $this->assertSame('10.000000', $effect->effect_snapshot['response']['bounded_value']);
        $this->assertNotNull($effect->revealed_at);
    }

    public function test_cohort_state_is_hidden_during_decision_window(): void
    {
        $context = $this->cohortContext();
        $this->submitRunRate($context['graph']['student'], $context['sourceWeek'], $context['teamSimulation'], $context['decisionDefinition'], '80');

        $this->assertCount(0, app(CohortFeedbackService::class)->visibleEffectsForStudent($context['graph']['student'], $context['targetWeek']));
        $this->expectException(InvalidArgumentException::class);

        app(CohortFeedbackService::class)->resolveForSectionWeek(
            $context['function'],
            $context['sourceWeek']->refresh(),
            $context['graph']['faculty'],
        );
    }

    public function test_student_can_view_revealed_effect_but_not_individual_peer_decisions(): void
    {
        $context = $this->cohortContext();
        $this->submitRunRate($context['graph']['student'], $context['sourceWeek'], $context['teamSimulation'], $context['decisionDefinition'], '80');
        $this->submitRunRate($context['otherStudent'], $context['sourceWeek'], $context['otherTeamSimulation'], $context['decisionDefinition'], '100');
        $this->closeSourceWeek($context);
        app(CohortFeedbackService::class)->resolveForSectionWeek(
            $context['function'],
            $context['sourceWeek']->refresh(),
            $context['graph']['faculty'],
        );

        $effects = app(CohortFeedbackService::class)->visibleEffectsForStudent($context['graph']['student'], $context['targetWeek']);

        $this->assertCount(1, $effects);
        $this->assertSame('10.000000', $effects[0]->effect_snapshot['response']['bounded_value']);
        $this->assertArrayNotHasKey('individual_decisions_snapshot', $effects[0]->effect_snapshot);
    }

    public function test_duplicate_resolution_returns_existing_aggregate_and_effect(): void
    {
        $context = $this->cohortContext();
        $this->submitRunRate($context['graph']['student'], $context['sourceWeek'], $context['teamSimulation'], $context['decisionDefinition'], '80');
        $this->submitRunRate($context['otherStudent'], $context['sourceWeek'], $context['otherTeamSimulation'], $context['decisionDefinition'], '100');
        $this->closeSourceWeek($context);
        $service = app(CohortFeedbackService::class);

        $first = $service->resolveForSectionWeek($context['function'], $context['sourceWeek']->refresh(), $context['graph']['faculty']);
        $second = $service->resolveForSectionWeek($context['function'], $context['sourceWeek']->refresh(), $context['graph']['faculty']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CohortDecisionAggregate::query()->count());
        $this->assertSame(1, CohortFeedbackEffect::query()->count());
    }

    public function test_tenant_and_section_isolation_are_enforced(): void
    {
        $first = $this->cohortContext('A');
        $second = $this->cohortContext('B');
        $this->submitRunRate($second['graph']['student'], $second['sourceWeek'], $second['teamSimulation'], $second['decisionDefinition'], '90');
        $this->closeSourceWeek($second);

        $this->expectException(InvalidArgumentException::class);

        app(CohortFeedbackService::class)->resolveForSectionWeek(
            $second['function'],
            $second['sourceWeek']->refresh(),
            $first['graph']['faculty'],
        );
    }

    public function test_aggregate_history_is_immutable(): void
    {
        $context = $this->cohortContext();
        $this->submitRunRate($context['graph']['student'], $context['sourceWeek'], $context['teamSimulation'], $context['decisionDefinition'], '80');
        $this->closeSourceWeek($context);
        $aggregate = app(CohortFeedbackService::class)->resolveForSectionWeek(
            $context['function'],
            $context['sourceWeek']->refresh(),
            $context['graph']['faculty'],
        );

        $this->expectException(InvalidArgumentException::class);

        $aggregate->update(['function_key' => 'changed']);
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     otherStudent: User,
     *     sectionSimulation: SectionSimulation,
     *     sourceWeek: SectionSimulationWeek,
     *     targetWeek: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation,
     *     otherTeamSimulation: TeamSimulation,
     *     decisionDefinition: DecisionFormDefinition,
     *     function: CohortResponseFunction
     * }
     */
    private function cohortContext(string $suffix = 'A'): array
    {
        $graph = $this->tenantGraph($suffix);
        [$otherStudent] = $this->addSecondTeam($graph);
        $structure = $this->simulationStructure(5);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week3 */
        $week3 = $structure['simulationWeeks']->firstWhere('week_number', 3);
        /** @var SimulationWeek $week5 */
        $week5 = $structure['simulationWeeks']->firstWhere('week_number', 5);
        $sourceWeek = $sectionSimulation->weeks()->where('simulation_week_id', $week3->id)->firstOrFail();
        $targetWeek = $sectionSimulation->weeks()->where('simulation_week_id', $week5->id)->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $sourceWeek = $lifecycle->transitionWeek($sourceWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $sourceWeek = $lifecycle->transitionWeek($sourceWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $sourceWeek->simulation_version_id,
            'simulation_week_id' => $sourceWeek->simulation_week_id,
            'key' => 'week3_run_rates',
            'name' => 'Week 3 run rates',
        ]);

        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $decisionDefinition->id,
            'field_key' => 'run_rate',
            'label' => 'Run rate',
            'field_type' => DecisionFieldType::Integer,
            'is_required' => true,
            'display_order' => 1,
            'validation' => ['min' => 0, 'max' => 120],
        ]);

        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();
        $otherTeamSimulation = TeamSimulation::query()
            ->where('tenant_id', $graph['tenant']->id)
            ->where('section_simulation_id', $sectionSimulation->id)
            ->whereHas('team.members', fn ($query) => $query->whereKey($otherStudent->id))
            ->firstOrFail();

        $function = CohortResponseFunction::query()->create([
            'key' => 'fixture_week3_to_week5_run_rate',
            'name' => 'Fixture Week 3 to Week 5 run-rate response',
            'description' => 'Framework-only fixture for cohort feedback tests.',
            'version' => 'fixture_v1_'.$suffix,
            'source_week_number' => 3,
            'target_week_number' => 5,
            'input_definition' => [
                'decision_field' => 'run_rate',
                'unit' => 'percent_capacity',
            ],
            'output_definition' => [
                'key' => 'nwe_crack_adjustment',
                'unit' => 'usd_bbl',
            ],
            'bounds' => [
                'min' => '-10',
                'max' => '10',
            ],
            'parameters' => [
                'aggregate' => 'average',
                'intercept' => '1',
                'slope' => '0.1',
            ],
            'is_active' => true,
        ]);

        return compact('graph', 'otherStudent', 'sectionSimulation', 'sourceWeek', 'targetWeek', 'teamSimulation', 'otherTeamSimulation', 'decisionDefinition', 'function');
    }

    private function submitRunRate(User $student, SectionSimulationWeek $sourceWeek, TeamSimulation $teamSimulation, DecisionFormDefinition $definition, string $runRate): void
    {
        app(SubmissionService::class)->submitDecision(
            $student,
            $sourceWeek,
            $teamSimulation,
            $definition,
            ['run_rate' => $runRate],
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function closeSourceWeek(array $context): void
    {
        app(SimulationLifecycleService::class)->transitionWeek(
            $context['sourceWeek']->refresh(),
            SectionSimulationWeekStatus::Closed,
            $context['graph']['faculty'],
        );
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array{User, Team}
     */
    private function addSecondTeam(array $graph): array
    {
        $student = User::factory()->student()->create([
            'tenant_id' => $graph['tenant']->id,
            'email' => 'student-second-cohort-'.$graph['tenant']->id.'@example.test',
        ]);
        $team = Team::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'name' => 'Team second cohort',
            'slug' => 'team-second-cohort-'.$graph['tenant']->id,
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

        return [$student, $team];
    }
}
