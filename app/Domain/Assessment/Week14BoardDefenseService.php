<?php

namespace App\Domain\Assessment;

use App\Domain\Assignments\EffectiveSeatAssignmentService;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\BoardDefenseAssessment;
use App\Models\BoardDefenseAssessmentFeedback;
use App\Models\BoardDefenseSubmission;
use App\Models\SectionSimulationWeek;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class Week14BoardDefenseService
{
    public const ARTIFACT_TYPES = [
        'board_presentation',
        'defense_material',
    ];

    public const DIMENSIONS = [
        'strategic_coherence' => [
            'label' => 'Strategic coherence',
            'description' => 'Did the team decisions add up to a coherent worldview.',
        ],
        'decision_quality' => [
            'label' => 'Decision quality',
            'description' => 'Were decisions sound given what the team knew at the time.',
        ],
        'self_understanding' => [
            'label' => 'Self-understanding',
            'description' => 'Does the team understand its own arc, including mistakes.',
        ],
    ];

    public const TIERS = [
        'strong_reasoning_strong_outcomes',
        'strong_reasoning_weaker_outcomes',
        'weak_reasoning_strong_outcomes',
        'weak_reasoning_weak_outcomes',
    ];

    public function __construct(
        private readonly EffectiveSeatAssignmentService $seatAssignments,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $artifactReferences
     */
    public function saveSubmissionDraft(
        User $actor,
        SectionSimulationWeek $runtimeWeek,
        string $finalSynthesisMemo,
        array $artifactReferences,
    ): BoardDefenseSubmission {
        $teamSimulation = $this->resolveStudentTeamSimulation($actor, $runtimeWeek);
        $this->assertStudentCanSubmit($actor, $runtimeWeek, $teamSimulation);

        return $this->persistSubmission(
            $actor,
            $runtimeWeek,
            $teamSimulation,
            $finalSynthesisMemo,
            $this->validatedArtifacts($artifactReferences),
            BoardDefenseSubmission::STATUS_DRAFT,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $artifactReferences
     */
    public function submitDefense(
        User $actor,
        SectionSimulationWeek $runtimeWeek,
        string $finalSynthesisMemo,
        array $artifactReferences,
    ): BoardDefenseSubmission {
        $teamSimulation = $this->resolveStudentTeamSimulation($actor, $runtimeWeek);
        $this->assertStudentCanSubmit($actor, $runtimeWeek, $teamSimulation);

        if (trim($finalSynthesisMemo) === '') {
            throw ValidationException::withMessages(['final_synthesis_memo' => 'Final synthesis memo is required.']);
        }

        return $this->persistSubmission(
            $actor,
            $runtimeWeek,
            $teamSimulation,
            $finalSynthesisMemo,
            $this->validatedArtifacts($artifactReferences),
            BoardDefenseSubmission::STATUS_SUBMITTED,
        );
    }

    /**
     * @param  array<string, array<string, string|null>>  $dimensions
     */
    public function saveAssessment(
        User $actor,
        SectionSimulationWeek $runtimeWeek,
        TeamSimulation $teamSimulation,
        array $dimensions,
        ?string $reasoningOutcomeTier,
        ?string $facultyPrivateNotes,
        ?string $feedbackBody,
        bool $complete = false,
    ): BoardDefenseAssessment {
        $this->assertFacultyCanAssess($actor, $runtimeWeek, $teamSimulation);

        if ($reasoningOutcomeTier !== null && ! in_array($reasoningOutcomeTier, self::TIERS, true)) {
            throw ValidationException::withMessages(['reasoning_outcome_tier' => 'Unknown Week 14 assessment tier.']);
        }

        $validatedDimensions = $this->validatedDimensions($dimensions);

        return DB::transaction(function () use ($actor, $runtimeWeek, $teamSimulation, $validatedDimensions, $reasoningOutcomeTier, $facultyPrivateNotes, $feedbackBody, $complete): BoardDefenseAssessment {
            $submission = BoardDefenseSubmission::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->first();

            $assessment = BoardDefenseAssessment::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->where('rubric_version', BoardDefenseAssessment::RUBRIC_VERSION)
                ->lockForUpdate()
                ->first();

            $assessment ??= new BoardDefenseAssessment([
                'tenant_id' => $runtimeWeek->tenant_id,
                'section_simulation_id' => $runtimeWeek->section_simulation_id,
                'section_simulation_week_id' => $runtimeWeek->id,
                'team_simulation_id' => $teamSimulation->id,
                'team_id' => $teamSimulation->team_id,
                'rubric_version' => BoardDefenseAssessment::RUBRIC_VERSION,
            ]);

            $assessment->fill([
                'board_defense_submission_id' => $submission?->id,
                'reviewer_user_id' => $actor->id,
                'status' => $complete ? BoardDefenseAssessment::STATUS_COMPLETED : BoardDefenseAssessment::STATUS_DRAFT,
                'reasoning_outcome_tier' => $reasoningOutcomeTier,
                'faculty_private_notes' => $facultyPrivateNotes,
                'history_packet_snapshot' => $this->historyPacketSnapshot($runtimeWeek, $teamSimulation),
                'completed_at' => $complete ? Carbon::now() : $assessment->completed_at,
            ]);
            $assessment->save();

            foreach ($validatedDimensions as $key => $payload) {
                $definition = self::DIMENSIONS[$key];
                $assessment->dimensions()->updateOrCreate([
                    'tenant_id' => $assessment->tenant_id,
                    'dimension_key' => $key,
                ], [
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'faculty_evaluation' => $payload['faculty_evaluation'],
                    'comments' => $payload['comments'],
                ]);
            }

            $existingFeedback = BoardDefenseAssessmentFeedback::query()
                ->where('tenant_id', $assessment->tenant_id)
                ->where('board_defense_assessment_id', $assessment->id)
                ->first();

            $assessment->feedback()->updateOrCreate([
                'tenant_id' => $assessment->tenant_id,
            ], [
                'feedback_body' => $feedbackBody,
                'is_published' => $existingFeedback instanceof BoardDefenseAssessmentFeedback ? $existingFeedback->is_published : false,
                'published_by_user_id' => $existingFeedback instanceof BoardDefenseAssessmentFeedback ? $existingFeedback->published_by_user_id : null,
                'published_at' => $existingFeedback instanceof BoardDefenseAssessmentFeedback ? $existingFeedback->published_at : null,
            ]);

            return $assessment->refresh()->load(['dimensions', 'feedback', 'submission']);
        });
    }

    public function publishFeedback(User $actor, BoardDefenseAssessment $assessment): BoardDefenseAssessment
    {
        $assessment->loadMissing('runtimeWeek', 'teamSimulation');
        $runtimeWeek = $assessment->runtimeWeek;
        $teamSimulation = $assessment->teamSimulation;

        if (! $runtimeWeek instanceof SectionSimulationWeek || ! $teamSimulation instanceof TeamSimulation) {
            throw new InvalidArgumentException('Assessment context is incomplete.');
        }

        $this->assertFacultyCanAssess($actor, $runtimeWeek, $teamSimulation);

        $feedback = $assessment->feedback;

        if (! $feedback instanceof BoardDefenseAssessmentFeedback || trim((string) $feedback->feedback_body) === '') {
            throw ValidationException::withMessages(['feedback' => 'Feedback must exist before publication.']);
        }

        $feedback->fill([
            'is_published' => true,
            'published_by_user_id' => $actor->id,
            'published_at' => Carbon::now(),
        ]);
        $feedback->save();

        return $assessment->refresh()->load(['dimensions', 'feedback', 'submission']);
    }

    /**
     * @return array<string, mixed>
     */
    public function studentView(User $actor, SectionSimulationWeek $runtimeWeek): array
    {
        $teamSimulation = $this->resolveStudentTeamSimulation($actor, $runtimeWeek);
        $this->assertWeek14($runtimeWeek);

        $submission = BoardDefenseSubmission::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->first();

        $assessmentId = BoardDefenseAssessment::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->value('id');
        $assessmentPayload = null;
        if (is_int($assessmentId)) {
            $assessment = BoardDefenseAssessment::query()->with(['dimensions', 'feedback'])->findOrFail($assessmentId);
            $feedback = $assessment->feedback;

            if ($feedback instanceof BoardDefenseAssessmentFeedback && $feedback->is_published) {
                $assessmentPayload = $this->publishedAssessmentPayload($assessment);
            }
        }

        return [
            'submission' => $submission ? $this->submissionPayload($submission) : null,
            'assessment' => $assessmentPayload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function facultyView(User $actor, SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation): array
    {
        $this->assertFacultyCanAssess($actor, $runtimeWeek, $teamSimulation);

        $submission = BoardDefenseSubmission::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->first();

        $assessment = BoardDefenseAssessment::query()
            ->with(['dimensions', 'feedback', 'submission'])
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->first();

        return [
            'submission' => $submission ? $this->submissionPayload($submission) : null,
            'assessment' => $assessment ? $this->facultyAssessmentPayload($assessment) : null,
            'rubric' => self::DIMENSIONS,
            'tiers' => self::TIERS,
            'history_packet' => $this->historyPacketSnapshot($runtimeWeek, $teamSimulation),
        ];
    }

    public function resolveStudentTeamSimulation(User $actor, SectionSimulationWeek $runtimeWeek): TeamSimulation
    {
        $teamSimulation = TeamSimulation::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_id', $runtimeWeek->section_simulation_id)
            ->whereHas('team.members', fn ($query) => $query->whereKey($actor->id))
            ->first();

        if (! $teamSimulation instanceof TeamSimulation) {
            throw new AuthorizationException('Actor is not a participant in this runtime week.');
        }

        return $teamSimulation;
    }

    private function assertStudentCanSubmit(User $actor, SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation): void
    {
        $this->assertWeek14($runtimeWeek);

        if ($actor->tenant_id !== $runtimeWeek->tenant_id || $teamSimulation->tenant_id !== $runtimeWeek->tenant_id) {
            throw new AuthorizationException('Board defense context must belong to one tenant.');
        }

        $isMember = TeamMember::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('team_id', $teamSimulation->team_id)
            ->where('user_id', $actor->id)
            ->exists();

        if (! $isMember) {
            throw new AuthorizationException('Only team members can submit board defense materials.');
        }

        if ($runtimeWeek->statusEnum() !== SectionSimulationWeekStatus::Open) {
            throw ValidationException::withMessages(['week' => 'Week 14 is not open for board defense submissions.']);
        }
    }

    private function assertFacultyCanAssess(User $actor, SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation): void
    {
        $this->assertWeek14($runtimeWeek);

        if ($actor->tenant_id !== $runtimeWeek->tenant_id || $teamSimulation->tenant_id !== $runtimeWeek->tenant_id) {
            throw new AuthorizationException('Assessment context must belong to one tenant.');
        }

        if ($teamSimulation->section_simulation_id !== $runtimeWeek->section_simulation_id) {
            throw new AuthorizationException('Team simulation does not belong to this runtime week.');
        }

        if ($actor->isAdministrator()) {
            return;
        }

        $authorized = $actor->isFaculty()
            && $actor->facultySections()
                ->wherePivot('tenant_id', $actor->tenant_id)
                ->whereKey($teamSimulation->section_id)
                ->exists();

        if (! $authorized) {
            throw new AuthorizationException('Faculty assessment is limited to assigned sections.');
        }
    }

    private function assertWeek14(SectionSimulationWeek $runtimeWeek): void
    {
        $runtimeWeek->loadMissing('definition');

        if ($runtimeWeek->definition->week_number !== 14) {
            throw new InvalidArgumentException('Board defense workflow is only available for Week 14.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $artifacts
     * @return list<array{type: string, label: string|null, reference: string}>
     */
    private function validatedArtifacts(array $artifacts): array
    {
        $validated = [];

        foreach ($artifacts as $artifact) {
            $type = (string) ($artifact['type'] ?? '');
            $reference = trim((string) ($artifact['reference'] ?? ''));
            $label = isset($artifact['label']) ? trim((string) $artifact['label']) : null;

            if (! in_array($type, self::ARTIFACT_TYPES, true)) {
                throw ValidationException::withMessages(['artifact_references' => 'Unsupported board defense artifact type.']);
            }

            if ($reference === '') {
                throw ValidationException::withMessages(['artifact_references' => 'Artifact reference is required.']);
            }

            $validated[] = [
                'type' => $type,
                'label' => $label !== '' ? $label : null,
                'reference' => $reference,
            ];
        }

        return $validated;
    }

    /**
     * @param  array<string, array<string, string|null>>  $dimensions
     * @return array<string, array{faculty_evaluation: string|null, comments: string|null}>
     */
    private function validatedDimensions(array $dimensions): array
    {
        $validated = [];

        foreach (array_keys(self::DIMENSIONS) as $key) {
            $payload = $dimensions[$key] ?? [];
            $validated[$key] = [
                'faculty_evaluation' => isset($payload['faculty_evaluation']) ? trim((string) $payload['faculty_evaluation']) : null,
                'comments' => isset($payload['comments']) ? trim((string) $payload['comments']) : null,
            ];
        }

        return $validated;
    }

    /**
     * @param  list<array{type: string, label: string|null, reference: string}>  $artifacts
     */
    private function persistSubmission(
        User $actor,
        SectionSimulationWeek $runtimeWeek,
        TeamSimulation $teamSimulation,
        string $finalSynthesisMemo,
        array $artifacts,
        string $status,
    ): BoardDefenseSubmission {
        return DB::transaction(function () use ($actor, $runtimeWeek, $teamSimulation, $finalSynthesisMemo, $artifacts, $status): BoardDefenseSubmission {
            $submission = BoardDefenseSubmission::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->lockForUpdate()
                ->first();

            if ($submission?->isSubmitted()) {
                throw ValidationException::withMessages(['submission' => 'Board defense submission has already been submitted.']);
            }

            $submission ??= new BoardDefenseSubmission([
                'tenant_id' => $runtimeWeek->tenant_id,
                'section_simulation_id' => $runtimeWeek->section_simulation_id,
                'section_simulation_week_id' => $runtimeWeek->id,
                'team_simulation_id' => $teamSimulation->id,
                'team_id' => $teamSimulation->team_id,
            ]);

            $now = Carbon::now();
            $submission->fill([
                'status' => $status,
                'final_synthesis_memo' => $finalSynthesisMemo,
                'artifact_references' => $artifacts,
                'lock_version' => ((int) $submission->lock_version) + 1,
                'updated_by_user_id' => $actor->id,
                'draft_saved_at' => $now,
                'submitted_by_user_id' => $status === BoardDefenseSubmission::STATUS_SUBMITTED ? $actor->id : $submission->submitted_by_user_id,
                'submitted_at' => $status === BoardDefenseSubmission::STATUS_SUBMITTED ? $now : $submission->submitted_at,
            ]);
            $submission->save();

            $submission->revisions()->create([
                'tenant_id' => $submission->tenant_id,
                'revision_number' => $submission->revisions()->count() + 1,
                'status' => $status,
                'final_synthesis_memo' => $finalSynthesisMemo,
                'artifact_references' => $artifacts,
                'actor_user_id' => $actor->id,
                'submitted_at' => $status === BoardDefenseSubmission::STATUS_SUBMITTED ? $submission->submitted_at : null,
            ]);

            return $submission->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function historyPacketSnapshot(SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation): array
    {
        return [
            'section_simulation_id' => $runtimeWeek->section_simulation_id,
            'section_simulation_week_id' => $runtimeWeek->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'required_sources' => [
                'decisions',
                'alternatives',
                'memos',
                'economic_evaluations',
                'kpi_rankings',
                'standing_history',
                'consequence_links',
                'advisor_history',
                'reasoning_versus_luck',
                'counterfactuals_where_available',
            ],
            'seat_history' => $this->seatAssignments->teamHistory($teamSimulation),
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function submissionPayload(BoardDefenseSubmission $submission): array
    {
        return [
            'ulid' => $submission->ulid,
            'status' => $submission->status,
            'final_synthesis_memo' => $submission->final_synthesis_memo,
            'artifact_references' => $submission->artifact_references ?? [],
            'submitted_at' => $this->isoTimestamp($submission->submitted_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function publishedAssessmentPayload(BoardDefenseAssessment $assessment): array
    {
        return [
            'ulid' => $assessment->ulid,
            'rubric_version' => $assessment->rubric_version,
            'status' => $assessment->status,
            'reasoning_outcome_tier' => $assessment->reasoning_outcome_tier,
            'dimensions' => $assessment->dimensions
                ->map(fn ($dimension): array => [
                    'key' => $dimension->dimension_key,
                    'label' => $dimension->label,
                    'faculty_evaluation' => $dimension->faculty_evaluation,
                    'comments' => $dimension->comments,
                ])
                ->values()
                ->all(),
            'feedback' => [
                'body' => $assessment->feedback?->feedback_body,
                'published_at' => $this->isoTimestamp($assessment->feedback?->published_at),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function facultyAssessmentPayload(BoardDefenseAssessment $assessment): array
    {
        return [
            ...$this->publishedAssessmentPayload($assessment),
            'faculty_private_notes' => $assessment->faculty_private_notes,
            'history_packet_snapshot' => $assessment->history_packet_snapshot,
            'feedback' => [
                'body' => $assessment->feedback?->feedback_body,
                'is_published' => (bool) $assessment->feedback?->is_published,
                'published_at' => $this->isoTimestamp($assessment->feedback?->published_at),
            ],
        ];
    }

    private function isoTimestamp(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
