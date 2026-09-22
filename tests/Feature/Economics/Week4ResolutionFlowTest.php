<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Resolution\WeekResolutionService;
use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
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
    private function openWeek4Context(array $graph, bool $transferPriceRequired = true): array
    {
        $structure = $this->simulationStructure(4);
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
        $student = User::factory()->student()->create([
            'tenant_id' => $graph['tenant']->id,
            'email' => 'student-second@example.test',
        ]);
        $team = Team::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'name' => 'Team second',
            'slug' => 'team-second',
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
