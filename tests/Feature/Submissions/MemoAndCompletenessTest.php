<?php

namespace Tests\Feature\Submissions;

use App\Domain\Submissions\SubmissionCompletenessService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\SubmissionStatus;
use App\Models\MemoSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class MemoAndCompletenessTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_memo_draft_and_final_submission_create_revisions(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);
        $service = app(SubmissionService::class);

        $draft = $service->saveMemoDraft($graph['student'], $context['runtimeWeek'], $context['teamSimulation'], $context['memoDefinition'], 'Draft text');

        $this->assertSame(SubmissionStatus::Draft, $draft->statusEnum());
        $this->assertSame(2, $draft->word_count);

        $submitted = $service->submitMemo($graph['student'], $context['runtimeWeek'], $context['teamSimulation'], $context['memoDefinition'], 'Final memo text');

        $this->assertSame(SubmissionStatus::Submitted, $submitted->statusEnum());
        $this->assertCount(2, $submitted->revisions);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'memo_submission.submitted',
            'auditable_type' => MemoSubmission::class,
            'auditable_id' => $submitted->id,
        ]);
    }

    public function test_memo_length_is_validated(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);

        $this->expectException(ValidationException::class);

        app(SubmissionService::class)->submitMemo(
            $graph['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['memoDefinition'],
            str_repeat('x', 101),
        );
    }

    public function test_submitted_memo_current_snapshot_cannot_be_directly_modified(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);
        $submission = app(SubmissionService::class)->submitMemo(
            $graph['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['memoDefinition'],
            'Final memo text',
        );

        $this->expectException(InvalidArgumentException::class);

        $submission->update(['body' => 'Changed body']);
    }

    public function test_completeness_requires_both_required_decision_and_memo(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);
        $service = app(SubmissionService::class);
        $completeness = app(SubmissionCompletenessService::class);

        $this->assertFalse($completeness->statusFor($context['runtimeWeek'], $context['teamSimulation'])['complete']);

        $service->submitDecision($graph['student'], $context['runtimeWeek'], $context['teamSimulation'], $context['decisionDefinition'], ['demo_quantity' => 5]);
        $this->assertFalse($completeness->statusFor($context['runtimeWeek'], $context['teamSimulation'])['complete']);

        $service->submitMemo($graph['student'], $context['runtimeWeek'], $context['teamSimulation'], $context['memoDefinition'], 'Done');
        $this->assertTrue($completeness->statusFor($context['runtimeWeek'], $context['teamSimulation'])['complete']);
    }
}
