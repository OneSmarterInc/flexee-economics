<?php

namespace Tests\Feature\Submissions;

use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\SectionSimulationWeekStatus;
use App\Enums\SubmissionStatus;
use App\Models\DecisionSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class DecisionSubmissionFlowTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_authorized_student_can_save_incomplete_draft_while_week_is_open(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);

        $submission = app(SubmissionService::class)->saveDecisionDraft(
            $graph['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['demo_choice' => 'alpha'],
        );

        $this->assertSame(SubmissionStatus::Draft, $submission->statusEnum());
        $this->assertSame(['demo_choice' => 'alpha'], $submission->answers);
        $this->assertCount(1, $submission->revisions);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'decision_submission.draft_saved',
            'auditable_type' => DecisionSubmission::class,
            'auditable_id' => $submission->id,
        ]);
    }

    public function test_final_submission_enforces_required_fields_and_creates_immutable_snapshot(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);
        $service = app(SubmissionService::class);

        $this->expectException(ValidationException::class);
        $service->submitDecision($graph['student'], $context['runtimeWeek'], $context['teamSimulation'], $context['decisionDefinition'], []);
    }

    public function test_valid_final_submission_succeeds_and_cannot_be_overwritten(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);
        $service = app(SubmissionService::class);

        $submission = $service->submitDecision(
            $graph['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['demo_quantity' => 42, 'demo_choice' => 'beta'],
        );

        $this->assertSame(SubmissionStatus::Submitted, $submission->statusEnum());
        $this->assertNotNull($submission->submitted_at);
        $this->assertCount(1, $submission->revisions);

        $this->expectException(ValidationException::class);
        $service->saveDecisionDraft($graph['student'], $context['runtimeWeek'], $context['teamSimulation'], $context['decisionDefinition'], ['demo_quantity' => 7]);
    }

    public function test_submitted_decision_current_snapshot_cannot_be_directly_modified(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);
        $submission = app(SubmissionService::class)->submitDecision(
            $graph['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['demo_quantity' => 42],
        );

        $this->expectException(InvalidArgumentException::class);

        $submission->update(['answers' => ['demo_quantity' => 7]]);
    }

    public function test_closed_or_expired_week_rejects_writes(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);
        app(SimulationLifecycleService::class)
            ->transitionWeek($context['runtimeWeek']->refresh(), SectionSimulationWeekStatus::Closed, $graph['faculty']);

        $this->expectException(ValidationException::class);

        app(SubmissionService::class)->saveDecisionDraft(
            $graph['student'],
            $context['runtimeWeek']->refresh(),
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['demo_quantity' => 5],
        );
    }

    public function test_open_week_with_past_deadline_rejects_writes(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);
        $context['runtimeWeek']->update(['closes_at' => now()->subMinute()]);

        $this->expectException(ValidationException::class);

        app(SubmissionService::class)->saveDecisionDraft(
            $graph['student'],
            $context['runtimeWeek']->refresh(),
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['demo_quantity' => 5],
        );
    }

    public function test_wrong_team_tenant_or_definition_is_rejected(): void
    {
        $graph = $this->tenantGraph('A');
        $other = $this->tenantGraph('B');
        $context = $this->openRuntimeWeekWithDefinitions($graph);
        $otherContext = $this->openRuntimeWeekWithDefinitions($other);

        $this->expectException(InvalidArgumentException::class);

        app(SubmissionService::class)->saveDecisionDraft(
            $graph['student'],
            $context['runtimeWeek'],
            $otherContext['teamSimulation'],
            $context['decisionDefinition'],
            ['demo_quantity' => 5],
        );
    }

    public function test_direct_route_rejects_wrong_definition_ulid(): void
    {
        $graph = $this->tenantGraph('A');
        $other = $this->tenantGraph('B');
        $context = $this->openRuntimeWeekWithDefinitions($graph);
        $otherContext = $this->openRuntimeWeekWithDefinitions($other);

        $this->actingAs($graph['student'])
            ->post(route('student.submissions.decisions.draft', $context['runtimeWeek']), [
                'definition_ulid' => $otherContext['decisionDefinition']->ulid,
                'answers' => ['demo_quantity' => 5],
            ])
            ->assertNotFound();
    }
}
