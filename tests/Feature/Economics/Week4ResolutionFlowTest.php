<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Resolution\WeekResolutionService;
use App\Domain\Economics\Week10\Week10InheritedStateAssembler;
use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Scoring\KpiFinancialStateService;
use App\Domain\Scoring\Week4KpiPopulationService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\KpiSnapshotStatus;
use App\Enums\SectionSimulationWeekStatus;
use App\Enums\StandingValue;
use App\Models\ConsequenceLink;
use App\Models\Counterparty;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\Enrollment;
use App\Models\KpiSnapshot;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\StandingState;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week4ResolutionFlowTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_valid_submitted_week4_decision_resolves(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openWeek4Context($graph);
        $submission = $this->submitWeek4Decision($graph['student'], $context['runtimeWeek'], $context['teamSimulation'], $context['decisionDefinition'], '46.20');

        $resolution = app(WeekResolutionService::class)->resolveSubmittedDecision($submission, $graph['student']);

        $this->assertSame($submission->id, $resolution->decision_submission_id);
        $this->assertSame(Week4EconomicEngine::ENGINE_IDENTIFIER, $resolution->economic_engine);
        $this->assertNotNull($resolution->resolved_at);
        $this->assertSame('week_resolution_service', $resolution->resolved_by_process);
    }

    public function test_draft_decision_does_not_resolve(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openWeek4Context($graph);
        $draft = app(SubmissionService::class)->saveDecisionDraft(
            $graph['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['transfer_price' => '46.20'],
        );

        $this->expectException(InvalidArgumentException::class);

        app(WeekResolutionService::class)->resolveSubmittedDecision($draft, $graph['student']);
    }

    public function test_submitted_decision_without_required_economic_decision_is_rejected(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openWeek4Context($graph, transferPriceRequired: false);
        $submission = app(SubmissionService::class)->submitDecision(
            $graph['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            [],
        );

        $this->expectException(InvalidArgumentException::class);

        app(WeekResolutionService::class)->resolveSubmittedDecision($submission, $graph['student']);
    }

    public function test_submission_outputs_match_week4_engine_result_and_are_stored(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openWeek4Context($graph);
        $submission = $this->submitWeek4Decision($graph['student'], $context['runtimeWeek'], $context['teamSimulation'], $context['decisionDefinition'], '46.20');

        $resolution = app(WeekResolutionService::class)->resolveSubmittedDecision($submission, $graph['student'])->refresh();

        $this->assertSame('46.200', $resolution->transfer_price);
        $this->assertSame('76.750', $resolution->integrated_margin);
        $this->assertSame('32.100', $resolution->upstream_margin);
        $this->assertSame('44.650', $resolution->refining_margin);
        $this->assertSame('-12.900', $resolution->upstream_vs_target);
        $this->assertSame('14.650', $resolution->refining_vs_target);
        $this->assertSame('9.625', $resolution->geneva_capture_per_bbl);
        $this->assertSame('76.75', $resolution->output_snapshot['integrated_margin']);
        $this->assertSame('9.625', $resolution->output_snapshot['geneva_arbitrage']['capture_per_bbl']);
        $this->assertSame('9.625', $resolution->output_snapshot['worked_example_geneva_arbitrage']['capture_per_bbl']);
    }

    public function test_week4_geneva_capture_uses_team_transfer_price_through_runtime(): void
    {
        $graph = $this->tenantGraph('Geneva');
        [$marketStudent, $marketTeam] = $this->addTeamMember($graph, 'market');
        [$lazyStudent, $lazyTeam] = $this->addTeamMember($graph, 'lazy');
        $context = $this->openWeek4Context($graph, weeks: 14);
        $week10 = $context['sectionSimulation']->weeks()
            ->whereHas('definition', fn ($query) => $query->where('week_number', 10))
            ->firstOrFail();
        $week10Definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $week10->simulation_version_id,
            'simulation_week_id' => $week10->simulation_week_id,
            'key' => 'week10_regression',
            'name' => 'Week 10 regression',
        ]);

        $cases = [
            'marginal' => [
                'student' => $graph['student'],
                'team_id' => $graph['team']->id,
                'price' => '18.70',
                'capture' => '0.000',
                'chain_leak' => '0.000000',
                'standing' => StandingValue::Cooperative,
                'hedge' => '0.700000',
                'integrated_margin' => '76.7500',
            ],
            'market' => [
                'student' => $marketStudent,
                'team_id' => $marketTeam->id,
                'price' => '73.70',
                'capture' => '0.000',
                'chain_leak' => '0.000000',
                'standing' => StandingValue::Cooperative,
                'hedge' => '0.700000',
                'integrated_margin' => '76.7500',
            ],
            'lazy' => [
                'student' => $lazyStudent,
                'team_id' => $lazyTeam->id,
                'price' => '46.20',
                'capture' => '9.625',
                'chain_leak' => '1.540000',
                'standing' => StandingValue::Guarded,
                'hedge' => '0.450000',
                'integrated_margin' => '75.2100',
            ],
        ];

        foreach ($cases as $case) {
            $teamSimulation = $context['sectionSimulation']->teamSimulations()
                ->where('team_id', $case['team_id'])
                ->firstOrFail();
            $submission = $this->submitWeek4Decision(
                $case['student'],
                $context['runtimeWeek'],
                $teamSimulation,
                $context['decisionDefinition'],
                $case['price'],
            );
            $resolution = app(WeekResolutionService::class)->resolveSubmittedDecision($submission, $case['student'])->refresh();
            $snapshots = collect(app(Week4KpiPopulationService::class)->populate($resolution))
                ->keyBy(fn (KpiSnapshot $snapshot): string => $snapshot->definition->key);
            $state = app(KpiFinancialStateService::class)->forTeamWeek($teamSimulation, $context['runtimeWeek']);
            $standing = $this->standingState($teamSimulation, 'whitaker');
            $hedge = ConsequenceLink::query()
                ->where('team_simulation_id', $teamSimulation->id)
                ->where('definition_key', 'week5_hedge_coverage')
                ->firstOrFail();
            $week10Submission = DecisionSubmission::query()->create([
                'tenant_id' => $teamSimulation->tenant_id,
                'section_simulation_id' => $teamSimulation->section_simulation_id,
                'section_simulation_week_id' => $week10->id,
                'team_simulation_id' => $teamSimulation->id,
                'team_id' => $teamSimulation->team_id,
                'decision_form_definition_id' => $week10Definition->id,
                'status' => 'submitted',
                'answers' => ['operating_posture' => 'regression'],
                'lock_version' => 1,
                'submitted_at' => now(),
            ]);
            $week10State = app(Week10InheritedStateAssembler::class)->assemble($week10Submission);

            $this->assertSame($case['capture'], $resolution->geneva_capture_per_bbl);
            $this->assertSame($case['capture'] === '0.000' ? '0' : $case['capture'], $resolution->output_snapshot['geneva_arbitrage']['capture_per_bbl']);
            $this->assertSame('9.625', $resolution->output_snapshot['worked_example_geneva_arbitrage']['capture_per_bbl']);
            $this->assertSame($case['chain_leak'], $state->inputs['i4_geneva_leak_per_chain_bbl']);
            $this->assertSame($case['standing'], $standing->stateEnum());
            $this->assertSame($case['hedge'], $hedge->metadata['target_value']);
            $this->assertSame($case['hedge'], (string) $week10State->crudeHedgeCoverage?->toScale(6));
            $this->assertSame(KpiSnapshotStatus::Available, $snapshots['integrated_margin_per_boe']->statusEnum());
            $this->assertSame($case['integrated_margin'], $snapshots['integrated_margin_per_boe']->value);
            $this->assertArrayNotHasKey('geneva_capture_per_bbl', $resolution->input_snapshot['submission']['answers']);
            $this->assertArrayNotHasKey('crude_hedge_coverage', $resolution->input_snapshot['submission']['answers']);
            $this->assertArrayNotHasKey('week10_inherited_state', $resolution->input_snapshot['submission']['answers']);
        }
    }

    public function test_week4_rejects_student_supplied_derived_values(): void
    {
        $graph = $this->tenantGraph('Security');
        $context = $this->openWeek4Context($graph);

        $this->expectException(ValidationException::class);

        app(SubmissionService::class)->submitDecision(
            $graph['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            [
                'transfer_price' => '18.70',
                'geneva_capture_per_bbl' => '99',
                'crude_hedge_coverage' => '1',
                'week10_inherited_state' => ['cash_cushion_musd' => '999'],
            ],
        );
    }

    public function test_duplicate_resolution_returns_existing_record(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openWeek4Context($graph);
        $submission = $this->submitWeek4Decision($graph['student'], $context['runtimeWeek'], $context['teamSimulation'], $context['decisionDefinition'], '46.20');
        $service = app(WeekResolutionService::class);

        $first = $service->resolveSubmittedDecision($submission, $graph['student']);
        $second = $service->resolveSubmittedDecision($submission, $graph['student']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, EconomicResolution::query()->count());
    }

    public function test_student_cannot_resolve_or_view_another_team_resolution(): void
    {
        $graph = $this->tenantGraph('A');
        [$otherStudent] = $this->addSecondTeam($graph);
        $context = $this->openWeek4Context($graph);
        $otherTeamSimulation = TeamSimulation::query()
            ->where('tenant_id', $graph['tenant']->id)
            ->where('section_simulation_id', $context['sectionSimulation']->id)
            ->whereHas('team.members', fn ($query) => $query->whereKey($otherStudent->id))
            ->firstOrFail();
        $submission = $this->submitWeek4Decision($otherStudent, $context['runtimeWeek'], $otherTeamSimulation, $context['decisionDefinition'], '46.20');
        $service = app(WeekResolutionService::class);

        try {
            $service->resolveSubmittedDecision($submission, $graph['student']);
            $this->fail('Expected another team member to be unable to resolve this team.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, EconomicResolution::query()->count());
        }

        $resolution = $service->resolveSubmittedDecision($submission, $otherStudent);

        $this->expectException(InvalidArgumentException::class);
        $service->assertCanView($graph['student'], $resolution);
    }

    public function test_resolution_stores_engine_version_for_historical_interpretation(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openWeek4Context($graph);
        $submission = $this->submitWeek4Decision($graph['student'], $context['runtimeWeek'], $context['teamSimulation'], $context['decisionDefinition'], '46.20');

        $resolution = app(WeekResolutionService::class)->resolveSubmittedDecision($submission, $graph['student']);

        $this->assertSame(Week4EconomicEngine::ENGINE_VERSION, $resolution->engine_version);
        $this->assertSame(Week4EconomicEngine::ENGINE_VERSION, $resolution->input_snapshot['engine_version']);
        $this->assertSame($submission->answers, $resolution->input_snapshot['submission']['answers']);
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array{sectionSimulation: SectionSimulation, runtimeWeek: SectionSimulationWeek, decisionDefinition: DecisionFormDefinition, teamSimulation: TeamSimulation}
     */
    private function openWeek4Context(array $graph, bool $transferPriceRequired = true, int $weeks = 4): array
    {
        $structure = $this->simulationStructure($weeks);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week4 */
        $week4 = $structure['simulationWeeks']->firstWhere('week_number', 4);
        $runtimeWeek = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week4->id)
            ->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addDay());

        $decisionDefinition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
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
            'is_required' => $transferPriceRequired,
            'display_order' => 1,
            'validation' => ['min' => 0, 'max' => 250],
        ]);

        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        return compact('sectionSimulation', 'runtimeWeek', 'decisionDefinition', 'teamSimulation');
    }

    private function submitWeek4Decision(User $student, SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation, DecisionFormDefinition $definition, string $transferPrice): DecisionSubmission
    {
        return app(SubmissionService::class)->submitDecision(
            $student,
            $runtimeWeek,
            $teamSimulation,
            $definition,
            ['transfer_price' => $transferPrice],
        );
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array{User, Team}
     */
    private function addSecondTeam(array $graph): array
    {
        return $this->addTeamMember($graph, 'second');
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array{User, Team}
     */
    private function addTeamMember(array $graph, string $suffix): array
    {
        $student = User::factory()->student()->create([
            'tenant_id' => $graph['tenant']->id,
            'email' => 'student-'.$suffix.'-'.uniqid().'@example.test',
        ]);
        $team = Team::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'name' => 'Team '.$suffix,
            'slug' => 'team-'.$suffix.'-'.uniqid(),
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

    private function standingState(TeamSimulation $teamSimulation, string $counterpartyKey): StandingState
    {
        $counterparty = Counterparty::query()->where('key', $counterpartyKey)->firstOrFail();

        return StandingState::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('counterparty_id', $counterparty->id)
            ->firstOrFail();
    }
}
