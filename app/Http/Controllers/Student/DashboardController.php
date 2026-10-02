<?php

namespace App\Http\Controllers\Student;

use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\SimulationContentResolver;
use App\Domain\Content\Week6\Week6ContentPackageManifest;
use App\Domain\Content\Week8\Week8ContentPackageManifest;
use App\Domain\Submissions\SubmissionCompletenessService;
use App\Enums\SectionSimulationWeekStatus;
use App\Http\Controllers\Controller;
use App\Models\CapitalAllocationEvaluation;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\KpiSnapshot;
use App\Models\MemoDefinition;
use App\Models\MemoSubmission;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationContentActivation;
use App\Models\TeamSimulation;
use App\Models\User;
use App\Models\Week10EconomicEvaluation;
use App\Models\Week11EconomicEvaluation;
use App\Models\Week12EconomicEvaluation;
use App\Models\Week13EconomicEvaluation;
use App\Models\Week1EconomicEvaluation;
use App\Models\Week5EconomicEvaluation;
use App\Models\Week8EconomicEvaluation;
use App\Models\Week9EconomicEvaluation;
use App\Models\WeekExecutionRecord;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        SimulationContentResolver $content,
        SubmissionCompletenessService $completeness,
    ): Response {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isStudent(), 403);

        $teamSimulations = $this->studentTeamSimulations($user);

        return Inertia::render('Student/Dashboard', [
            'journey' => [
                'tenant' => $user->tenant?->only(['name', 'slug']),
                'simulations' => $teamSimulations
                    ->map(fn (TeamSimulation $teamSimulation): array => $this->simulationPayload($teamSimulation, $user, $content, $completeness))
                    ->values(),
            ],
        ]);
    }

    /**
     * @return EloquentCollection<int, TeamSimulation>
     */
    private function studentTeamSimulations(User $user): EloquentCollection
    {
        return TeamSimulation::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereHas('team.members', fn ($query) => $query->whereKey($user->id))
            ->with([
                'team',
                'sectionSimulation.section.course',
                'sectionSimulation.simulation',
                'sectionSimulation.variant',
                'sectionSimulation.version',
                'sectionSimulation.weeks.definition.decisionFormDefinitions',
                'sectionSimulation.weeks.definition.memoDefinitions',
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function simulationPayload(
        TeamSimulation $teamSimulation,
        User $student,
        SimulationContentResolver $content,
        SubmissionCompletenessService $completeness,
    ): array {
        $sectionSimulation = $teamSimulation->sectionSimulation;
        $weeks = $this->orderedWeeks($teamSimulation);
        $currentWeek = $this->currentWeek($weeks, $teamSimulation);

        return [
            'course' => $sectionSimulation->section->course->name,
            'section' => $sectionSimulation->section->name,
            'team' => $teamSimulation->team->name,
            'simulation' => $sectionSimulation->simulation->name,
            'variant' => $sectionSimulation->variant->name,
            'version' => $sectionSimulation->version->version,
            'current_week' => $currentWeek instanceof SectionSimulationWeek
                ? $this->weekPayload($currentWeek, $teamSimulation, $student, $completeness)
                : null,
            'current_content' => $currentWeek instanceof SectionSimulationWeek
                ? $this->contentPayload($currentWeek, $student, $content)
                : null,
            'progress' => $this->progressPayload($weeks, $teamSimulation),
            'timeline' => $weeks
                ->map(fn (SectionSimulationWeek $runtimeWeek): array => $this->timelinePayload($runtimeWeek, $teamSimulation, $currentWeek))
                ->values(),
            'history' => $weeks
                ->filter(fn (SectionSimulationWeek $runtimeWeek): bool => $this->isStudentVisible($runtimeWeek))
                ->map(fn (SectionSimulationWeek $runtimeWeek): array => $this->historyPayload($runtimeWeek, $teamSimulation))
                ->values(),
        ];
    }

    /**
     * @return Collection<int, SectionSimulationWeek>
     */
    private function orderedWeeks(TeamSimulation $teamSimulation): Collection
    {
        return $teamSimulation->sectionSimulation->weeks
            ->sortBy(fn (SectionSimulationWeek $runtimeWeek): int => $runtimeWeek->definition->week_number)
            ->values();
    }

    /**
     * @param  Collection<int, SectionSimulationWeek>  $weeks
     */
    private function currentWeek(Collection $weeks, TeamSimulation $teamSimulation): ?SectionSimulationWeek
    {
        return $weeks->first(fn (SectionSimulationWeek $week): bool => $week->statusEnum() === SectionSimulationWeekStatus::Open)
            ?? $weeks->first(fn (SectionSimulationWeek $week): bool => $week->statusEnum() === SectionSimulationWeekStatus::Released)
            ?? $weeks->first(fn (SectionSimulationWeek $week): bool => $this->isStudentVisible($week) && $this->resolutionState($week, $teamSimulation)['status'] !== 'resolved')
            ?? $weeks->filter(fn (SectionSimulationWeek $week): bool => $this->isStudentVisible($week))->last();
    }

    /**
     * @return array<string, mixed>
     */
    private function weekPayload(
        SectionSimulationWeek $runtimeWeek,
        TeamSimulation $teamSimulation,
        User $student,
        SubmissionCompletenessService $completeness,
    ): array {
        $submissionStatus = $completeness->statusFor($runtimeWeek, $teamSimulation);
        $resolution = $this->resolutionState($runtimeWeek, $teamSimulation);

        return [
            'number' => $runtimeWeek->definition->week_number,
            'title' => $runtimeWeek->definition->title,
            'status' => $runtimeWeek->statusValue(),
            'timeline_status' => $this->timelineState($runtimeWeek, $teamSimulation, $runtimeWeek),
            'url' => $this->isStudentVisible($runtimeWeek) ? route('student.submissions.show', $runtimeWeek) : null,
            'can_write' => $runtimeWeek->statusEnum() === SectionSimulationWeekStatus::Open,
            'decision_status' => $submissionStatus['decision_status'],
            'memo_status' => $submissionStatus['memo_status'],
            'capital_allocation_status' => $submissionStatus['capital_allocation_status'] ?? null,
            'complete' => $submissionStatus['complete'],
            'ready_for_evaluation' => $submissionStatus['ready_for_evaluation'],
            'resolution_status' => $resolution['status'],
            'resolved_at' => $resolution['resolved_at'],
            'student_name' => $student->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contentPayload(SectionSimulationWeek $runtimeWeek, User $student, SimulationContentResolver $content): array
    {
        if (! $this->isStudentVisible($runtimeWeek)) {
            return [
                'package' => ['status' => 'locked', 'message' => 'Content is not available for this week yet.'],
                'artifacts' => [],
            ];
        }

        $packageType = $this->packageTypeFor($runtimeWeek);

        try {
            $package = $content->activePackageFor($runtimeWeek, $packageType);
            $artifacts = $content->authorizedArtifactsFor($student, $runtimeWeek, $packageType)
                ->map(fn ($artifact): array => [
                    'key' => $artifact->artifact_key,
                    'type' => $artifact->artifact_type,
                    'visibility' => $artifact->visibility,
                    'version' => $artifact->version,
                    'reference' => $artifact->path_reference,
                ])
                ->values();

            return [
                'package' => [
                    'status' => 'active',
                    'package_type' => $package->package_type,
                    'version' => $package->version,
                    'validation_status' => $package->status,
                    'message' => null,
                ],
                'artifacts' => $artifacts,
            ];
        } catch (InvalidArgumentException $exception) {
            return [
                'package' => [
                    'status' => 'unavailable',
                    'package_type' => $packageType,
                    'version' => null,
                    'validation_status' => null,
                    'message' => $exception->getMessage(),
                ],
                'artifacts' => [],
            ];
        }
    }

    /**
     * @param  Collection<int, SectionSimulationWeek>  $weeks
     * @return array<string, int>
     */
    private function progressPayload(Collection $weeks, TeamSimulation $teamSimulation): array
    {
        $completed = $weeks
            ->filter(fn (SectionSimulationWeek $runtimeWeek): bool => $this->resolutionState($runtimeWeek, $teamSimulation)['status'] === 'resolved')
            ->count();
        $visible = $weeks
            ->filter(fn (SectionSimulationWeek $runtimeWeek): bool => $this->isStudentVisible($runtimeWeek))
            ->count();

        return [
            'completed' => $completed,
            'visible' => $visible,
            'total' => $weeks->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function timelinePayload(SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation, ?SectionSimulationWeek $currentWeek): array
    {
        $state = $this->timelineState($runtimeWeek, $teamSimulation, $currentWeek);

        return [
            'number' => $runtimeWeek->definition->week_number,
            'title' => $runtimeWeek->definition->title,
            'state' => $state,
            'status' => $runtimeWeek->statusValue(),
            'url' => $this->isStudentVisible($runtimeWeek) ? route('student.submissions.show', $runtimeWeek) : null,
        ];
    }

    private function timelineState(SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation, ?SectionSimulationWeek $currentWeek): string
    {
        if ($this->resolutionState($runtimeWeek, $teamSimulation)['status'] === 'resolved') {
            return 'completed';
        }

        if ($runtimeWeek->statusEnum() === SectionSimulationWeekStatus::Open) {
            return 'active';
        }

        if (in_array($runtimeWeek->statusEnum(), [SectionSimulationWeekStatus::Released, SectionSimulationWeekStatus::Closed, SectionSimulationWeekStatus::Published], true)) {
            return 'available';
        }

        if ($currentWeek instanceof SectionSimulationWeek && $runtimeWeek->definition->week_number > $currentWeek->definition->week_number) {
            return 'upcoming';
        }

        return 'locked';
    }

    /**
     * @return array<string, mixed>
     */
    private function historyPayload(SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation): array
    {
        $decisionDefinition = $runtimeWeek->definition->decisionFormDefinitions->first();
        $memoDefinition = $runtimeWeek->definition->memoDefinitions->first();
        $decision = $decisionDefinition instanceof DecisionFormDefinition
            ? $this->decisionSubmission($runtimeWeek, $teamSimulation, $decisionDefinition)
            : null;
        $memo = $memoDefinition instanceof MemoDefinition
            ? $this->memoSubmission($runtimeWeek, $teamSimulation, $memoDefinition)
            : null;
        $resolution = $this->resolutionState($runtimeWeek, $teamSimulation);

        return [
            'number' => $runtimeWeek->definition->week_number,
            'title' => $runtimeWeek->definition->title,
            'url' => route('student.submissions.show', $runtimeWeek),
            'decision_status' => $decision?->statusValue() ?? 'not_started',
            'decision_submitted_at' => $this->formatTimestamp($decision?->getAttribute('submitted_at')),
            'memo_status' => $memo?->statusValue() ?? 'not_started',
            'memo_submitted_at' => $this->formatTimestamp($memo?->getAttribute('submitted_at')),
            'resolution_status' => $resolution['status'],
            'resolved_at' => $resolution['resolved_at'],
            'result_summary' => $resolution['status'] === 'resolved'
                ? [
                    'label' => $resolution['label'],
                    'status' => 'available',
                    'detail' => 'Your team result is available for this week.',
                    'available_kpis' => KpiSnapshot::query()
                        ->where('tenant_id', $runtimeWeek->tenant_id)
                        ->where('section_simulation_week_id', $runtimeWeek->id)
                        ->where('team_simulation_id', $teamSimulation->id)
                        ->where('status', 'available')
                        ->count(),
                ]
                : null,
        ];
    }

    private function decisionSubmission(
        SectionSimulationWeek $runtimeWeek,
        TeamSimulation $teamSimulation,
        DecisionFormDefinition $definition,
    ): ?DecisionSubmission {
        return DecisionSubmission::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('decision_form_definition_id', $definition->id)
            ->first();
    }

    private function memoSubmission(
        SectionSimulationWeek $runtimeWeek,
        TeamSimulation $teamSimulation,
        MemoDefinition $definition,
    ): ?MemoSubmission {
        return MemoSubmission::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('memo_definition_id', $definition->id)
            ->first();
    }

    /**
     * @return array{status: string, resolved_at: string|null, label: string}
     */
    private function resolutionState(SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation): array
    {
        $evaluation = match ($runtimeWeek->definition->week_number) {
            1 => $this->latestEvaluation(Week1EconomicEvaluation::class, $runtimeWeek, $teamSimulation),
            5 => $this->latestEvaluation(Week5EconomicEvaluation::class, $runtimeWeek, $teamSimulation),
            6 => $this->latestEvaluation(CapitalAllocationEvaluation::class, $runtimeWeek, $teamSimulation),
            8 => $this->latestEvaluation(Week8EconomicEvaluation::class, $runtimeWeek, $teamSimulation),
            9 => $this->latestEvaluation(Week9EconomicEvaluation::class, $runtimeWeek, $teamSimulation),
            10 => $this->latestEvaluation(Week10EconomicEvaluation::class, $runtimeWeek, $teamSimulation),
            11 => $this->latestEvaluation(Week11EconomicEvaluation::class, $runtimeWeek, $teamSimulation),
            12 => $this->latestEvaluation(Week12EconomicEvaluation::class, $runtimeWeek, $teamSimulation),
            13 => $this->latestEvaluation(Week13EconomicEvaluation::class, $runtimeWeek, $teamSimulation),
            default => $this->latestEvaluation(EconomicResolution::class, $runtimeWeek, $teamSimulation),
        };

        if ($evaluation === null && ! $this->hasEconomicEvaluation($runtimeWeek->definition->week_number)) {
            $execution = $this->completedExecution($runtimeWeek);

            if ($execution instanceof WeekExecutionRecord) {
                return [
                    'status' => 'resolved',
                    'resolved_at' => $this->formatTimestamp($execution->completed_at ?? $execution->created_at),
                    'label' => 'Execution completed',
                ];
            }
        }

        return [
            'status' => $evaluation === null ? 'unresolved' : 'resolved',
            'resolved_at' => $evaluation === null ? null : $this->formatTimestamp(
                $evaluation->getAttribute('evaluated_at')
                    ?? $evaluation->getAttribute('resolved_at')
                    ?? $evaluation->getAttribute('created_at'),
            ),
            'label' => $this->resultLabel($runtimeWeek->definition->week_number),
        ];
    }

    /**
     * @param  class-string  $model
     */
    private function latestEvaluation(string $model, SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation): ?Model
    {
        return $model::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->latest('id')
            ->first();
    }

    private function hasEconomicEvaluation(int $weekNumber): bool
    {
        return in_array($weekNumber, [1, 4, 5, 6, 8, 9, 10, 11, 12, 13], true);
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

    private function isStudentVisible(SectionSimulationWeek $runtimeWeek): bool
    {
        return in_array($runtimeWeek->statusEnum(), [
            SectionSimulationWeekStatus::Released,
            SectionSimulationWeekStatus::Open,
            SectionSimulationWeekStatus::Closed,
            SectionSimulationWeekStatus::Published,
        ], true);
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

    private function resultLabel(int $weekNumber): string
    {
        return match ($weekNumber) {
            4 => 'Economic resolution',
            5 => 'Currency evaluation',
            6 => 'Capital allocation evaluation',
            8 => 'Scenario evaluation',
            9 => 'Retail evaluation',
            10 => 'Convergence evaluation',
            11 => 'Kessana evaluation',
            12 => 'Portfolio evaluation',
            13 => 'Factor-market evaluation',
            default => 'Result',
        };
    }

    private function formatTimestamp(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
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
