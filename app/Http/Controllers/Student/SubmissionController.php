<?php

namespace App\Http\Controllers\Student;

use App\Domain\Capital\CapitalAllocationService;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\SimulationContentResolver;
use App\Domain\Content\Week6\Week6ContentPackageManifest;
use App\Domain\Content\Week8\Week8ContentPackageManifest;
use App\Domain\Submissions\SubmissionCompletenessService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\SectionSimulationWeekStatus;
use App\Http\Controllers\Controller;
use App\Models\CapitalAllocationDecision;
use App\Models\CapitalAllocationEvaluation;
use App\Models\CapitalProject;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\EconomicResolution;
use App\Models\MemoDefinition;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationContentActivation;
use App\Models\SimulationContentPackage;
use App\Models\TeamSimulation;
use App\Models\User;
use App\Models\Week10EconomicEvaluation;
use App\Models\Week11EconomicEvaluation;
use App\Models\Week12EconomicEvaluation;
use App\Models\Week13EconomicEvaluation;
use App\Models\Week1EconomicEvaluation;
use App\Models\Week2EconomicEvaluation;
use App\Models\Week3EconomicEvaluation;
use App\Models\Week5EconomicEvaluation;
use App\Models\Week7EconomicEvaluation;
use App\Models\Week8EconomicEvaluation;
use App\Models\Week9EconomicEvaluation;
use App\Models\WeekExecutionRecord;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SubmissionController extends Controller
{
    public function show(
        Request $request,
        SectionSimulationWeek $sectionSimulationWeek,
        SubmissionService $submissions,
        SubmissionCompletenessService $completeness,
        SimulationContentResolver $content,
    ): Response {
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
        $contentState = $this->contentState($content, $user, $sectionSimulationWeek);
        $resolutionState = $this->resolutionState($sectionSimulationWeek, $teamSimulation);
        $capitalAllocationState = $this->capitalAllocationState($sectionSimulationWeek, $teamSimulation);

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
            'contentPackage' => $contentState['package'],
            'artifacts' => $contentState['artifacts'],
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
            'status' => [
                ...$completeness->statusFor($sectionSimulationWeek, $teamSimulation),
                'resolution_status' => $resolutionState['status'],
                'resolved_at' => $resolutionState['resolved_at'],
            ],
            'capitalAllocation' => $capitalAllocationState,
            'routes' => [
                'decisionDraft' => route('student.submissions.decisions.draft', $sectionSimulationWeek),
                'decisionSubmit' => route('student.submissions.decisions.submit', $sectionSimulationWeek),
                'capitalAllocationSubmit' => route('student.submissions.capital-allocation.submit', $sectionSimulationWeek),
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

    public function submitCapitalAllocation(Request $request, SectionSimulationWeek $sectionSimulationWeek, SubmissionService $submissions, CapitalAllocationService $capitalAllocations): RedirectResponse
    {
        $sectionSimulationWeek->loadMissing('definition');

        if ($sectionSimulationWeek->definition->week_number !== 6) {
            abort(404);
        }

        $payload = $request->validate([
            'selected_project_keys' => ['required', 'array', 'min:1'],
            'selected_project_keys.*' => ['required', 'string'],
            'week10_inherited_state' => ['sometimes', 'array'],
            'week10_inherited_state.cancellable_capex_musd' => ['sometimes', 'numeric', 'min:0'],
            'week10_inherited_state.crude_hedge_coverage' => ['sometimes', 'numeric', 'min:0', 'max:1'],
        ]);

        $teamSimulation = $submissions->resolveTeamSimulationForActor($request->user(), $sectionSimulationWeek);
        $selected = array_values(array_unique($payload['selected_project_keys']));
        $activeKeys = $capitalAllocations->activeProjects()->pluck('key')->all();
        $rejected = array_values(array_diff($activeKeys, $selected));

        try {
            $capitalAllocations->submitAllocation(
                $request->user(),
                $teamSimulation,
                $sectionSimulationWeek,
                $selected,
                $rejected,
                contextExtensions: [
                    'week10_inherited_state' => $payload['week10_inherited_state'] ?? [],
                ],
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['capital_allocation' => $exception->getMessage()]);
        }

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

    /**
     * @return array{
     *     package: array{status: string, version: string|null, package_type: string, validation_status: string|null, message: string|null},
     *     artifacts: list<array{key: string, label: string, type: string, visibility: string|null, version: string|null, reference: string}>
     * }
     */
    private function contentState(SimulationContentResolver $content, User $user, SectionSimulationWeek $runtimeWeek): array
    {
        try {
            $packageType = $this->packageTypeFor($runtimeWeek);
            $package = $content->activePackageFor($runtimeWeek, $packageType);

            $artifacts = [];
            foreach ($content->authorizedArtifactsFor($user, $runtimeWeek, $packageType) as $artifact) {
                $artifacts[] = [
                    'key' => $artifact->artifact_key,
                    'label' => $this->artifactLabel($artifact->artifact_key, $artifact->artifact_type),
                    'type' => $artifact->artifact_type,
                    'visibility' => $artifact->visibility,
                    'version' => $artifact->version,
                    'reference' => $artifact->path_reference,
                ];
            }

            return [
                'package' => $this->packagePayload($package, 'active', null),
                'artifacts' => $artifacts,
            ];
        } catch (InvalidArgumentException $exception) {
            return [
                'package' => [
                    'status' => 'unavailable',
                    'version' => null,
                    'package_type' => $this->packageTypeFor($runtimeWeek),
                    'validation_status' => null,
                    'message' => $exception->getMessage(),
                ],
                'artifacts' => [],
            ];
        }
    }

    private function artifactLabel(string $key, string $type): string
    {
        return match ($type) {
            'workbook' => 'Student workbook',
            'notebook' => 'Student analysis notebook',
            'manifest' => 'Package guide',
            'dataset' => str($key)
                ->after('_data_')
                ->replace('_csv', '')
                ->replace(['_', '-'], ' ')
                ->title()
                ->append(' dataset')
                ->toString(),
            default => str($key)->replace(['_', '-'], ' ')->title()->toString(),
        };
    }

    /**
     * @return array{status: string, resolved_at: string|null}
     */
    private function resolutionState(SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation): array
    {
        if ($runtimeWeek->definition->week_number === 1) {
            $evaluation = Week1EconomicEvaluation::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->latest('evaluated_at')
                ->first();
            $evaluatedAt = $evaluation?->getAttribute('evaluated_at');

            return [
                'status' => $evaluation instanceof Week1EconomicEvaluation ? 'resolved' : 'unresolved',
                'resolved_at' => $evaluatedAt instanceof Carbon ? $evaluatedAt->toIso8601String() : null,
            ];
        }

        if ($runtimeWeek->definition->week_number === 2) {
            $evaluation = Week2EconomicEvaluation::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->latest('evaluated_at')
                ->first();
            $evaluatedAt = $evaluation?->getAttribute('evaluated_at');

            return [
                'status' => $evaluation instanceof Week2EconomicEvaluation ? 'resolved' : 'unresolved',
                'resolved_at' => $evaluatedAt instanceof Carbon ? $evaluatedAt->toIso8601String() : null,
            ];
        }

        if ($runtimeWeek->definition->week_number === 6) {
            $evaluation = CapitalAllocationEvaluation::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->latest('evaluated_at')
                ->first();
            $evaluatedAt = $evaluation?->getAttribute('evaluated_at');

            return [
                'status' => $evaluation instanceof CapitalAllocationEvaluation ? 'resolved' : 'unresolved',
                'resolved_at' => $evaluatedAt instanceof Carbon ? $evaluatedAt->toIso8601String() : null,
            ];
        }

        if ($runtimeWeek->definition->week_number === 5) {
            $evaluation = Week5EconomicEvaluation::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->latest('evaluated_at')
                ->first();
            $evaluatedAt = $evaluation?->getAttribute('evaluated_at');

            return [
                'status' => $evaluation instanceof Week5EconomicEvaluation ? 'resolved' : 'unresolved',
                'resolved_at' => $evaluatedAt instanceof Carbon ? $evaluatedAt->toIso8601String() : null,
            ];
        }

        if ($runtimeWeek->definition->week_number === 3) {
            $evaluation = Week3EconomicEvaluation::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->latest('evaluated_at')
                ->first();
            $evaluatedAt = $evaluation?->getAttribute('evaluated_at');

            return [
                'status' => $evaluation instanceof Week3EconomicEvaluation ? 'resolved' : 'unresolved',
                'resolved_at' => $evaluatedAt instanceof Carbon ? $evaluatedAt->toIso8601String() : null,
            ];
        }

        if ($runtimeWeek->definition->week_number === 7) {
            $evaluation = Week7EconomicEvaluation::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->latest('evaluated_at')
                ->first();
            $evaluatedAt = $evaluation?->getAttribute('evaluated_at');

            return [
                'status' => $evaluation instanceof Week7EconomicEvaluation ? 'resolved' : 'unresolved',
                'resolved_at' => $evaluatedAt instanceof Carbon ? $evaluatedAt->toIso8601String() : null,
            ];
        }

        if ($runtimeWeek->definition->week_number === 8) {
            $evaluation = Week8EconomicEvaluation::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->latest('evaluated_at')
                ->first();
            $evaluatedAt = $evaluation?->getAttribute('evaluated_at');

            return [
                'status' => $evaluation instanceof Week8EconomicEvaluation ? 'resolved' : 'unresolved',
                'resolved_at' => $evaluatedAt instanceof Carbon ? $evaluatedAt->toIso8601String() : null,
            ];
        }

        if ($runtimeWeek->definition->week_number === 9) {
            $evaluation = Week9EconomicEvaluation::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->latest('evaluated_at')
                ->first();
            $evaluatedAt = $evaluation?->getAttribute('evaluated_at');

            return [
                'status' => $evaluation instanceof Week9EconomicEvaluation ? 'resolved' : 'unresolved',
                'resolved_at' => $evaluatedAt instanceof Carbon ? $evaluatedAt->toIso8601String() : null,
            ];
        }

        if ($runtimeWeek->definition->week_number === 10) {
            $evaluation = Week10EconomicEvaluation::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->latest('evaluated_at')
                ->first();
            $evaluatedAt = $evaluation?->getAttribute('evaluated_at');

            return [
                'status' => $evaluation instanceof Week10EconomicEvaluation ? 'resolved' : 'unresolved',
                'resolved_at' => $evaluatedAt instanceof Carbon ? $evaluatedAt->toIso8601String() : null,
            ];
        }

        if ($runtimeWeek->definition->week_number === 11) {
            $evaluation = Week11EconomicEvaluation::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->latest('evaluated_at')
                ->first();
            $evaluatedAt = $evaluation?->getAttribute('evaluated_at');

            return [
                'status' => $evaluation instanceof Week11EconomicEvaluation ? 'resolved' : 'unresolved',
                'resolved_at' => $evaluatedAt instanceof Carbon ? $evaluatedAt->toIso8601String() : null,
            ];
        }

        if ($runtimeWeek->definition->week_number === 12) {
            $evaluation = Week12EconomicEvaluation::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->latest('evaluated_at')
                ->first();
            $evaluatedAt = $evaluation?->getAttribute('evaluated_at');

            return [
                'status' => $evaluation instanceof Week12EconomicEvaluation ? 'resolved' : 'unresolved',
                'resolved_at' => $evaluatedAt instanceof Carbon ? $evaluatedAt->toIso8601String() : null,
            ];
        }

        if ($runtimeWeek->definition->week_number === 13) {
            $evaluation = Week13EconomicEvaluation::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->latest('evaluated_at')
                ->first();
            $evaluatedAt = $evaluation?->getAttribute('evaluated_at');

            return [
                'status' => $evaluation instanceof Week13EconomicEvaluation ? 'resolved' : 'unresolved',
                'resolved_at' => $evaluatedAt instanceof Carbon ? $evaluatedAt->toIso8601String() : null,
            ];
        }

        $resolution = EconomicResolution::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->latest('resolved_at')
            ->first();

        $resolvedAt = $resolution?->getAttribute('resolved_at');

        if (! $resolution instanceof EconomicResolution && ! $this->hasEconomicEvaluation($runtimeWeek->definition->week_number)) {
            $execution = $this->completedExecution($runtimeWeek);

            if ($execution instanceof WeekExecutionRecord) {
                $completedAt = $execution->completed_at ?? $execution->created_at;

                return [
                    'status' => 'resolved',
                    'resolved_at' => $completedAt instanceof DateTimeInterface ? $completedAt->format(DateTimeInterface::ATOM) : null,
                ];
            }
        }

        return [
            'status' => $resolution instanceof EconomicResolution ? 'resolved' : 'unresolved',
            'resolved_at' => $resolvedAt instanceof Carbon ? $resolvedAt->toIso8601String() : null,
        ];
    }

    private function hasEconomicEvaluation(int $weekNumber): bool
    {
        return in_array($weekNumber, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13], true);
    }

    private function completedExecution(SectionSimulationWeek $runtimeWeek): ?WeekExecutionRecord
    {
        return WeekExecutionRecord::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('status', WeekExecutionRecord::STATUS_COMPLETED)
            ->latest('completed_at')
            ->first();
    }

    /**
     * @return array{status: string, version: string|null, package_type: string, validation_status: string|null, message: string|null}
     */
    private function packagePayload(SimulationContentPackage $package, string $status, ?string $message): array
    {
        return [
            'status' => $status,
            'version' => $package->version,
            'package_type' => $package->package_type,
            'validation_status' => $package->status,
            'message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function capitalAllocationState(SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation): ?array
    {
        if ($runtimeWeek->definition->week_number !== 6) {
            return null;
        }

        $decision = CapitalAllocationDecision::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->first();

        return [
            'status' => $decision instanceof CapitalAllocationDecision ? 'submitted' : 'not_started',
            'selected_project_keys' => $decision instanceof CapitalAllocationDecision
                ? collect($decision->selectedProjectSnapshots())->pluck('key')->values()->all()
                : [],
            'context' => app(CapitalAllocationService::class)->contextFor($teamSimulation, $runtimeWeek)->snapshot,
            'projects' => app(CapitalAllocationService::class)->activeProjects()->map(fn (CapitalProject $project): array => [
                'key' => $project->key,
                'name' => $project->name,
                'category' => $project->category,
                'risk_class' => $project->risk_class,
                'cash_flow_reference' => $project->cash_flow_reference,
                'metadata' => is_array($project->getAttribute('metadata')) ? $project->getAttribute('metadata') : [],
            ])->values()->all(),
        ];
    }

    private function packageTypeFor(SectionSimulationWeek $runtimeWeek): string
    {
        $weekNumber = $runtimeWeek->definition->week_number;
        $authoritativePackages = app(AuthoritativeContentPackageManifest::class);

        if (in_array($weekNumber, $authoritativePackages->registrableWeeks(), true)) {
            $authoritativeType = $authoritativePackages->packageType($weekNumber);

            if ($this->hasActivePackage($runtimeWeek, $authoritativeType)) {
                return $authoritativeType;
            }
        }

        return match ($weekNumber) {
            6 => Week6ContentPackageManifest::PACKAGE_TYPE,
            8 => Week8ContentPackageManifest::PACKAGE_TYPE,
            default => 'reference_package',
        };
    }

    private function hasActivePackage(SectionSimulationWeek $runtimeWeek, string $packageType): bool
    {
        return SimulationContentActivation::query()
            ->where('simulation_week_id', $runtimeWeek->simulation_week_id)
            ->where('package_type', $packageType)
            ->where('status', SimulationContentActivation::STATUS_ACTIVE)
            ->exists();
    }
}
