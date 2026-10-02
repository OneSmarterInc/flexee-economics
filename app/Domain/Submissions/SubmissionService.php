<?php

namespace App\Domain\Submissions;

use App\Enums\SectionSimulationWeekStatus;
use App\Enums\SubmissionStatus;
use App\Models\AuditEvent;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\MemoDefinition;
use App\Models\MemoSubmission;
use App\Models\SectionSimulationWeek;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SubmissionService
{
    public function __construct(
        private readonly DefinitionInputValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     */
    public function saveDecisionDraft(User $actor, SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation, DecisionFormDefinition $definition, array $answers): DecisionSubmission
    {
        $this->assertCanWrite($actor, $runtimeWeek, $teamSimulation, $definition);
        $answers = $this->validator->validateDecisionAnswers($definition, $answers, final: false);

        return $this->persistDecision($actor, $runtimeWeek, $teamSimulation, $definition, $answers, SubmissionStatus::Draft);
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    public function submitDecision(User $actor, SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation, DecisionFormDefinition $definition, array $answers): DecisionSubmission
    {
        $this->assertCanWrite($actor, $runtimeWeek, $teamSimulation, $definition);
        $answers = $this->validator->validateDecisionAnswers($definition, $answers, final: true);

        return $this->persistDecision($actor, $runtimeWeek, $teamSimulation, $definition, $answers, SubmissionStatus::Submitted);
    }

    public function saveMemoDraft(User $actor, SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation, MemoDefinition $definition, ?string $body): MemoSubmission
    {
        $this->assertCanWrite($actor, $runtimeWeek, $teamSimulation, $definition);
        $body = $this->validator->validateMemoBody($definition, $body, final: false);

        return $this->persistMemo($actor, $runtimeWeek, $teamSimulation, $definition, $body, SubmissionStatus::Draft);
    }

    public function submitMemo(User $actor, SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation, MemoDefinition $definition, ?string $body): MemoSubmission
    {
        $this->assertCanWrite($actor, $runtimeWeek, $teamSimulation, $definition);
        $body = $this->validator->validateMemoBody($definition, $body, final: true);

        return $this->persistMemo($actor, $runtimeWeek, $teamSimulation, $definition, $body, SubmissionStatus::Submitted);
    }

    public function resolveTeamSimulationForActor(User $actor, SectionSimulationWeek $runtimeWeek): TeamSimulation
    {
        $runtimeWeek->loadMissing('sectionSimulation');

        $teamSimulation = TeamSimulation::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_id', $runtimeWeek->section_simulation_id)
            ->whereHas('team.members', fn ($query) => $query->whereKey($actor->id))
            ->first();

        if (! $teamSimulation) {
            throw new InvalidArgumentException('Actor is not a participant in this runtime week.');
        }

        return $teamSimulation;
    }

    private function assertCanWrite(User $actor, SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation, DecisionFormDefinition|MemoDefinition $definition): void
    {
        if ($actor->tenant_id !== $runtimeWeek->tenant_id || $teamSimulation->tenant_id !== $runtimeWeek->tenant_id) {
            throw new InvalidArgumentException('Submission context must belong to one tenant.');
        }

        if ($teamSimulation->section_simulation_id !== $runtimeWeek->section_simulation_id || $definition->simulation_week_id !== $runtimeWeek->simulation_week_id) {
            throw new InvalidArgumentException('Submission context does not match the runtime week.');
        }

        $isMember = TeamMember::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('team_id', $teamSimulation->team_id)
            ->where('user_id', $actor->id)
            ->exists();

        if (! $isMember) {
            throw new InvalidArgumentException('Only team members can submit for the team.');
        }

        if ($runtimeWeek->statusEnum() !== SectionSimulationWeekStatus::Open) {
            throw ValidationException::withMessages(['week' => 'The runtime week is not open for submissions.']);
        }

        if ($runtimeWeek->closes_at !== null && Carbon::now()->greaterThan($runtimeWeek->closes_at)) {
            throw ValidationException::withMessages(['week' => 'The submission deadline has passed.']);
        }
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function persistDecision(User $actor, SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation, DecisionFormDefinition $definition, array $answers, SubmissionStatus $status): DecisionSubmission
    {
        return DB::transaction(function () use ($actor, $runtimeWeek, $teamSimulation, $definition, $answers, $status): DecisionSubmission {
            $submission = DecisionSubmission::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->where('decision_form_definition_id', $definition->id)
                ->lockForUpdate()
                ->first();

            if ($submission?->statusEnum() === SubmissionStatus::Submitted) {
                throw ValidationException::withMessages(['submission' => 'Decision submission has already been submitted.']);
            }

            $submission ??= new DecisionSubmission([
                'tenant_id' => $runtimeWeek->tenant_id,
                'section_simulation_id' => $runtimeWeek->section_simulation_id,
                'section_simulation_week_id' => $runtimeWeek->id,
                'team_simulation_id' => $teamSimulation->id,
                'team_id' => $teamSimulation->team_id,
                'decision_form_definition_id' => $definition->id,
            ]);

            $now = Carbon::now();
            $submission->fill([
                'status' => $status->value,
                'answers' => $answers,
                'lock_version' => ((int) $submission->lock_version) + 1,
                'updated_by_user_id' => $actor->id,
                'draft_saved_at' => $now,
                'submitted_by_user_id' => $status === SubmissionStatus::Submitted ? $actor->id : $submission->submitted_by_user_id,
                'submitted_at' => $status === SubmissionStatus::Submitted ? $now : $submission->submitted_at,
            ]);
            $submission->save();

            $revision = $submission->revisions()->create([
                'tenant_id' => $submission->tenant_id,
                'decision_form_definition_id' => $definition->id,
                'revision_number' => $submission->revisions()->count() + 1,
                'status' => $status->value,
                'answers' => $answers,
                'actor_user_id' => $actor->id,
                'submitted_at' => $status === SubmissionStatus::Submitted ? $submission->submitted_at : null,
            ]);

            $this->recordAudit(
                $submission->tenant_id,
                $actor,
                $status === SubmissionStatus::Submitted ? 'decision_submission.submitted' : 'decision_submission.draft_saved',
                $submission,
                [
                    'team_simulation_id' => $teamSimulation->id,
                    'section_simulation_week_id' => $runtimeWeek->id,
                    'revision_id' => $revision->id,
                    'status' => $status->value,
                ],
            );

            return $submission->refresh();
        });
    }

    private function persistMemo(User $actor, SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation, MemoDefinition $definition, string $body, SubmissionStatus $status): MemoSubmission
    {
        return DB::transaction(function () use ($actor, $runtimeWeek, $teamSimulation, $definition, $body, $status): MemoSubmission {
            $submission = MemoSubmission::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->where('memo_definition_id', $definition->id)
                ->lockForUpdate()
                ->first();

            if ($submission?->statusEnum() === SubmissionStatus::Submitted) {
                throw ValidationException::withMessages(['submission' => 'Memo has already been submitted.']);
            }

            $submission ??= new MemoSubmission([
                'tenant_id' => $runtimeWeek->tenant_id,
                'section_simulation_id' => $runtimeWeek->section_simulation_id,
                'section_simulation_week_id' => $runtimeWeek->id,
                'team_simulation_id' => $teamSimulation->id,
                'team_id' => $teamSimulation->team_id,
                'memo_definition_id' => $definition->id,
            ]);

            $now = Carbon::now();
            $wordCount = $this->validator->wordCount($body);
            $characterCount = mb_strlen($body);

            $submission->fill([
                'status' => $status->value,
                'body' => $body,
                'word_count' => $wordCount,
                'character_count' => $characterCount,
                'lock_version' => ((int) $submission->lock_version) + 1,
                'updated_by_user_id' => $actor->id,
                'draft_saved_at' => $now,
                'submitted_by_user_id' => $status === SubmissionStatus::Submitted ? $actor->id : $submission->submitted_by_user_id,
                'submitted_at' => $status === SubmissionStatus::Submitted ? $now : $submission->submitted_at,
            ]);
            $submission->save();

            $revision = $submission->revisions()->create([
                'tenant_id' => $submission->tenant_id,
                'memo_definition_id' => $definition->id,
                'revision_number' => $submission->revisions()->count() + 1,
                'status' => $status->value,
                'body' => $body,
                'word_count' => $wordCount,
                'character_count' => $characterCount,
                'actor_user_id' => $actor->id,
                'submitted_at' => $status === SubmissionStatus::Submitted ? $submission->submitted_at : null,
            ]);

            $this->recordAudit(
                $submission->tenant_id,
                $actor,
                $status === SubmissionStatus::Submitted ? 'memo_submission.submitted' : 'memo_submission.draft_saved',
                $submission,
                [
                    'team_simulation_id' => $teamSimulation->id,
                    'section_simulation_week_id' => $runtimeWeek->id,
                    'revision_id' => $revision->id,
                    'status' => $status->value,
                ],
            );

            return $submission->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordAudit(int $tenantId, User $actor, string $action, Model $auditable, array $metadata): void
    {
        AuditEvent::query()->create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'metadata' => $metadata,
            'occurred_at' => Carbon::now(),
        ]);
    }
}
