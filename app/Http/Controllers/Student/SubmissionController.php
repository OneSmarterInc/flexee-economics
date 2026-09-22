<?php

namespace App\Http\Controllers\Student;

use App\Domain\Submissions\SubmissionCompletenessService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\SectionSimulationWeekStatus;
use App\Http\Controllers\Controller;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\MemoDefinition;
use App\Models\SectionSimulationWeek;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionController extends Controller
{
    public function show(Request $request, SectionSimulationWeek $sectionSimulationWeek, SubmissionService $submissions, SubmissionCompletenessService $completeness): Response
    {
        $this->authorize('view', $sectionSimulationWeek);

        $user = $request->user();
        $teamSimulation = $submissions->resolveTeamSimulationForActor($user, $sectionSimulationWeek);

        $sectionSimulationWeek->loadMissing([
            'sectionSimulation.section.course',
            'definition.decisionFormDefinitions.fields',
            'definition.memoDefinitions',
        ]);

        $decisionDefinition = $sectionSimulationWeek->definition->decisionFormDefinitions->first();
        $memoDefinition = $sectionSimulationWeek->definition->memoDefinitions->first();
        $decisionSubmission = $decisionDefinition
            ? $teamSimulation->decisionSubmissions()
                ->where('section_simulation_week_id', $sectionSimulationWeek->id)
                ->where('decision_form_definition_id', $decisionDefinition->id)
                ->first()
            : null;
        $memoSubmission = $memoDefinition
            ? $teamSimulation->memoSubmissions()
                ->where('section_simulation_week_id', $sectionSimulationWeek->id)
                ->where('memo_definition_id', $memoDefinition->id)
                ->first()
            : null;

        $canWrite = $sectionSimulationWeek->statusEnum() === SectionSimulationWeekStatus::Open
            && ($sectionSimulationWeek->closes_at === null || now()->lessThanOrEqualTo($sectionSimulationWeek->closes_at));
        $closesAt = $sectionSimulationWeek->getAttribute('closes_at');
        $decisionAnswers = is_array($decisionSubmission?->getAttribute('answers')) ? $decisionSubmission->getAttribute('answers') : [];
        $memoBody = $memoSubmission?->getAttribute('body');

        return Inertia::render('Submissions/Show', [
            'week' => [
                'ulid' => $sectionSimulationWeek->ulid,
                'title' => $sectionSimulationWeek->definition->title,
                'number' => $sectionSimulationWeek->definition->week_number,
                'status' => $sectionSimulationWeek->statusValue(),
                'closes_at' => $closesAt instanceof Carbon ? $closesAt->toIso8601String() : null,
                'course' => $sectionSimulationWeek->sectionSimulation->section->course->name,
                'section' => $sectionSimulationWeek->sectionSimulation->section->name,
                'can_write' => $canWrite,
            ],
            'team' => [
                'name' => $teamSimulation->team->name,
            ],
            'decisionDefinition' => $decisionDefinition ? [
                'ulid' => $decisionDefinition->ulid,
                'name' => $decisionDefinition->name,
                'required' => $decisionDefinition->is_required,
                'fields' => $decisionDefinition->fields->map(fn (DecisionFieldDefinition $field) => [
                    'key' => $field->field_key,
                    'label' => $field->label,
                    'type' => $field->typeEnum()->value,
                    'required' => $field->is_required,
                    'help_text' => $field->help_text,
                    'unit' => $field->unit,
                    'options' => $field->options ?? [],
                ])->values(),
                'answers' => $decisionAnswers,
                'status' => $decisionSubmission?->statusValue() ?? 'not_started',
            ] : null,
            'memoDefinition' => $memoDefinition ? [
                'ulid' => $memoDefinition->ulid,
                'title' => $memoDefinition->title,
                'instructions' => $memoDefinition->instructions,
                'required' => $memoDefinition->is_required,
                'word_limit' => $memoDefinition->word_limit,
                'character_limit' => $memoDefinition->character_limit,
                'body' => is_string($memoBody) ? $memoBody : '',
                'status' => $memoSubmission?->statusValue() ?? 'not_started',
            ] : null,
            'status' => $completeness->statusFor($sectionSimulationWeek, $teamSimulation),
            'routes' => [
                'decisionDraft' => route('student.submissions.decisions.draft', $sectionSimulationWeek),
                'decisionSubmit' => route('student.submissions.decisions.submit', $sectionSimulationWeek),
                'memoDraft' => route('student.submissions.memo.draft', $sectionSimulationWeek),
                'memoSubmit' => route('student.submissions.memo.submit', $sectionSimulationWeek),
            ],
        ]);
    }

    public function saveDecisionDraft(Request $request, SectionSimulationWeek $sectionSimulationWeek, SubmissionService $submissions): RedirectResponse
    {
        $payload = $request->validate([
            'definition_ulid' => ['required', 'string'],
            'answers' => ['array'],
        ]);

        $definition = $this->decisionDefinition($sectionSimulationWeek, $payload['definition_ulid']);
        $teamSimulation = $submissions->resolveTeamSimulationForActor($request->user(), $sectionSimulationWeek);
        $submissions->saveDecisionDraft($request->user(), $sectionSimulationWeek, $teamSimulation, $definition, $payload['answers'] ?? []);

        return back();
    }

    public function submitDecision(Request $request, SectionSimulationWeek $sectionSimulationWeek, SubmissionService $submissions): RedirectResponse
    {
        $payload = $request->validate([
            'definition_ulid' => ['required', 'string'],
            'answers' => ['array'],
        ]);

        $definition = $this->decisionDefinition($sectionSimulationWeek, $payload['definition_ulid']);
        $teamSimulation = $submissions->resolveTeamSimulationForActor($request->user(), $sectionSimulationWeek);
        $submissions->submitDecision($request->user(), $sectionSimulationWeek, $teamSimulation, $definition, $payload['answers'] ?? []);

        return back();
    }

    public function saveMemoDraft(Request $request, SectionSimulationWeek $sectionSimulationWeek, SubmissionService $submissions): RedirectResponse
    {
        $payload = $request->validate([
            'definition_ulid' => ['required', 'string'],
            'body' => ['nullable', 'string'],
        ]);

        $definition = $this->memoDefinition($sectionSimulationWeek, $payload['definition_ulid']);
        $teamSimulation = $submissions->resolveTeamSimulationForActor($request->user(), $sectionSimulationWeek);
        $submissions->saveMemoDraft($request->user(), $sectionSimulationWeek, $teamSimulation, $definition, $payload['body'] ?? '');

        return back();
    }

    public function submitMemo(Request $request, SectionSimulationWeek $sectionSimulationWeek, SubmissionService $submissions): RedirectResponse
    {
        $payload = $request->validate([
            'definition_ulid' => ['required', 'string'],
            'body' => ['nullable', 'string'],
        ]);

        $definition = $this->memoDefinition($sectionSimulationWeek, $payload['definition_ulid']);
        $teamSimulation = $submissions->resolveTeamSimulationForActor($request->user(), $sectionSimulationWeek);
        $submissions->submitMemo($request->user(), $sectionSimulationWeek, $teamSimulation, $definition, $payload['body'] ?? '');

        return back();
    }

    private function decisionDefinition(SectionSimulationWeek $runtimeWeek, string $ulid): DecisionFormDefinition
    {
        return DecisionFormDefinition::query()
            ->where('ulid', $ulid)
            ->where('simulation_version_id', $runtimeWeek->simulation_version_id)
            ->where('simulation_week_id', $runtimeWeek->simulation_week_id)
            ->firstOrFail();
    }

    private function memoDefinition(SectionSimulationWeek $runtimeWeek, string $ulid): MemoDefinition
    {
        return MemoDefinition::query()
            ->where('ulid', $ulid)
            ->where('simulation_version_id', $runtimeWeek->simulation_version_id)
            ->where('simulation_week_id', $runtimeWeek->simulation_week_id)
            ->firstOrFail();
    }
}
