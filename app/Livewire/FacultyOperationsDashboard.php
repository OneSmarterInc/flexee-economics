<?php

namespace App\Livewire;

use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\SimulationContentResolver;
use App\Domain\Submissions\SubmissionCompletenessService;
use App\Enums\KpiSnapshotStatus;
use App\Enums\RankingSnapshotStatus;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\CapitalAllocationEvaluation;
use App\Models\ConsequenceLink;
use App\Models\EconomicResolution;
use App\Models\KpiSnapshot;
use App\Models\RankingSnapshot;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Component;

class FacultyOperationsDashboard extends Component
{
    public ?int $sectionSimulationId = null;

    public ?int $runtimeWeekId = null;

    public function updatedSectionSimulationId(): void
    {
        $this->runtimeWeekId = null;
    }

    public function render(
        SimulationContentResolver $contentResolver,
        SubmissionCompletenessService $submissionCompleteness,
        AuthoritativeContentPackageManifest $authoritativePackages,
    ): View {
        $actor = Auth::user();

        abort_unless($actor instanceof User && ($actor->isFaculty() || $actor->isAdministrator()), 403);

        $sectionSimulations = $this->authorizedSectionSimulations($actor);
        $selectedSection = $this->selectedSectionSimulation($sectionSimulations);
        $selectedWeek = $selectedSection instanceof SectionSimulation
            ? $this->selectedRuntimeWeek($selectedSection)
            : null;

        return view('livewire.faculty-operations-dashboard', [
            'sectionSimulations' => $sectionSimulations,
            'selectedSection' => $selectedSection,
            'selectedWeek' => $selectedWeek,
            'sectionSummary' => $selectedSection instanceof SectionSimulation ? $this->sectionSummary($selectedSection) : null,
            'currentWeekSummary' => $selectedWeek instanceof SectionSimulationWeek
                ? $this->currentWeekSummary($selectedWeek, $contentResolver, $actor, $authoritativePackages)
                : null,
            'readiness' => $selectedWeek instanceof SectionSimulationWeek
                ? $this->readinessSummary($selectedWeek, $selectedSection, $submissionCompleteness)
                : null,
            'timeline' => $selectedSection instanceof SectionSimulation
                ? $this->timeline($selectedSection, $contentResolver, $actor, $authoritativePackages)
                : collect(),
            'operations' => $selectedWeek instanceof SectionSimulationWeek
                ? $this->operationsSummary($selectedWeek, $contentResolver, $actor, $authoritativePackages)
                : null,
            'results' => $selectedWeek instanceof SectionSimulationWeek
                ? $this->resultsSummary($selectedWeek)
                : null,
        ]);
    }

    /**
     * @return EloquentCollection<int, SectionSimulation>
     */
    private function authorizedSectionSimulations(User $actor): EloquentCollection
    {
        return SectionSimulation::query()
            ->where('tenant_id', $actor->tenant_id)
            ->when(
                $actor->isFaculty() && ! $actor->isAdministrator(),
                fn (Builder $query): Builder => $query->whereIn(
                    'section_id',
                    $actor->facultySections()
                        ->wherePivot('tenant_id', $actor->tenant_id)
                        ->select('sections.id'),
                ),
            )
            ->with([
                'section.course',
                'variant',
                'version',
                'weeks.definition',
                'teamSimulations.team.members',
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, SectionSimulation>  $sectionSimulations
     */
    private function selectedSectionSimulation(EloquentCollection $sectionSimulations): ?SectionSimulation
    {
        if ($sectionSimulations->isEmpty()) {
            $this->sectionSimulationId = null;

            return null;
        }

        $selected = $this->sectionSimulationId !== null
            ? $sectionSimulations->firstWhere('id', $this->sectionSimulationId)
            : null;

        if (! $selected instanceof SectionSimulation) {
            $selected = $sectionSimulations->first();
            $this->sectionSimulationId = $selected->id;
        }

        return $selected;
    }

    private function selectedRuntimeWeek(SectionSimulation $sectionSimulation): ?SectionSimulationWeek
    {
        $weeks = $this->orderedWeeks($sectionSimulation);

        if ($weeks->isEmpty()) {
            $this->runtimeWeekId = null;

            return null;
        }

        $selected = $this->runtimeWeekId !== null
            ? $weeks->firstWhere('id', $this->runtimeWeekId)
            : null;

        if (! $selected instanceof SectionSimulationWeek) {
            $selected = $weeks->first(fn (SectionSimulationWeek $week): bool => $week->statusEnum() === SectionSimulationWeekStatus::Open)
                ?? $weeks->first(fn (SectionSimulationWeek $week): bool => $week->statusEnum() === SectionSimulationWeekStatus::Released)
                ?? $weeks->first(fn (SectionSimulationWeek $week): bool => $week->statusEnum() !== SectionSimulationWeekStatus::Published)
                ?? $weeks->first();

            $this->runtimeWeekId = $selected->id;
        }

        return $selected;
    }

    /**
     * @return Collection<int, SectionSimulationWeek>
     */
    private function orderedWeeks(SectionSimulation $sectionSimulation): Collection
    {
        return $sectionSimulation->weeks
            ->sortBy(fn (SectionSimulationWeek $week): int => $week->definition->week_number)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionSummary(SectionSimulation $sectionSimulation): array
    {
        $teamCount = $sectionSimulation->teamSimulations->count();
        $studentCount = $sectionSimulation->teamSimulations
            ->flatMap(fn ($teamSimulation): Collection => $teamSimulation->team->members)
            ->unique('id')
            ->count();

        return [
            'name' => $sectionSimulation->name,
            'course' => $sectionSimulation->section->course->name,
            'section' => $sectionSimulation->section->name,
            'variant' => $sectionSimulation->variant->name,
            'version' => $sectionSimulation->version->version,
            'teams' => $teamCount,
            'students' => $studentCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentWeekSummary(
        SectionSimulationWeek $runtimeWeek,
        SimulationContentResolver $contentResolver,
        User $actor,
        AuthoritativeContentPackageManifest $authoritativePackages,
    ): array {
        $content = $this->contentStatus($runtimeWeek, $contentResolver, $actor, $authoritativePackages);
        $execution = $this->latestExecution($runtimeWeek);

        return [
            'number' => $runtimeWeek->definition->week_number,
            'title' => $runtimeWeek->definition->title,
            'status' => $runtimeWeek->statusValue(),
            'content_status' => $content['status'],
            'content_detail' => $content['detail'],
            'execution_status' => $execution->status ?? 'not_run',
            'last_execution_time' => $this->executionTimestamp($execution),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function readinessSummary(
        SectionSimulationWeek $runtimeWeek,
        SectionSimulation $sectionSimulation,
        SubmissionCompletenessService $submissionCompleteness,
    ): array {
        $complete = 0;
        $ready = 0;

        foreach ($sectionSimulation->teamSimulations as $teamSimulation) {
            $status = $submissionCompleteness->statusFor($runtimeWeek, $teamSimulation);

            if ($status['complete'] === true) {
                $complete++;
            }

            if ($status['ready_for_evaluation'] === true) {
                $ready++;
            }
        }

        $total = $sectionSimulation->teamSimulations->count();
        $pending = max(0, $total - $complete);

        return [
            'teams' => $total,
            'complete' => $complete,
            'ready' => $ready,
            'pending' => $pending,
        ];
    }

    /**
     * @return Collection<int, array{id: int, number: int, title: string, status: string, state: string, content_status: string, execution_status: string}>
     */
    private function timeline(
        SectionSimulation $sectionSimulation,
        SimulationContentResolver $contentResolver,
        User $actor,
        AuthoritativeContentPackageManifest $authoritativePackages,
    ): Collection {
        return $this->orderedWeeks($sectionSimulation)
            ->map(function (SectionSimulationWeek $week) use ($contentResolver, $actor, $authoritativePackages): array {
                $execution = $this->latestExecution($week);
                $content = $this->contentStatus($week, $contentResolver, $actor, $authoritativePackages);
                $state = $this->timelineState($week, $execution, $content['status']);

                return [
                    'id' => $week->id,
                    'number' => $week->definition->week_number,
                    'title' => $week->definition->title,
                    'status' => $week->statusValue(),
                    'state' => $state,
                    'content_status' => $content['status'],
                    'execution_status' => $execution->status ?? 'not_run',
                ];
            });
    }

    private function timelineState(SectionSimulationWeek $runtimeWeek, ?WeekExecutionRecord $execution, string $contentStatus): string
    {
        if ($execution?->status === WeekExecutionRecord::STATUS_COMPLETED) {
            return 'completed';
        }

        if ($runtimeWeek->statusEnum() === SectionSimulationWeekStatus::Open) {
            return 'active';
        }

        if ($contentStatus === 'missing' && $runtimeWeek->definition->week_number !== 14) {
            return 'blocked';
        }

        if (in_array($runtimeWeek->statusEnum(), [SectionSimulationWeekStatus::Released, SectionSimulationWeekStatus::Closed], true)) {
            return 'available';
        }

        return $runtimeWeek->definition->week_number === 14 ? 'assessment' : 'pending';
    }

    /**
     * @return array<string, mixed>
     */
    private function operationsSummary(
        SectionSimulationWeek $runtimeWeek,
        SimulationContentResolver $contentResolver,
        User $actor,
        AuthoritativeContentPackageManifest $authoritativePackages,
    ): array {
        $execution = $this->latestExecution($runtimeWeek);
        $content = $this->contentStatus($runtimeWeek, $contentResolver, $actor, $authoritativePackages);

        return [
            'content' => $content,
            'execution_status' => $execution->status ?? 'not_run',
            'execution_steps' => $execution->steps ?? [],
            'failure_message' => $execution?->failure_message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resultsSummary(SectionSimulationWeek $runtimeWeek): array
    {
        $economic = $this->economicSummary($runtimeWeek);
        $kpi = $this->kpiSummary($runtimeWeek);
        $ranking = $this->rankingSummary($runtimeWeek);
        $consequenceCount = ConsequenceLink::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('source_section_simulation_week_id', $runtimeWeek->id)
            ->count();

        return [
            'economic' => $economic,
            'kpi' => $kpi,
            'ranking' => $ranking,
            'consequence_count' => $consequenceCount,
            'consequence_note' => $consequenceCount > 0
                ? "{$consequenceCount} authoritative consequence link(s) recorded."
                : 'No authoritative consequence mapping available.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function economicSummary(SectionSimulationWeek $runtimeWeek): array
    {
        $model = $this->evaluationModelForWeek($runtimeWeek->definition->week_number);

        if ($model === null) {
            return [
                'label' => 'Economic evaluation',
                'count' => 0,
                'status_counts' => [],
                'note' => 'Economic runtime evaluation is not implemented for this week.',
            ];
        }

        $query = $model::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id);

        return [
            'label' => $this->evaluationLabelForWeek($runtimeWeek->definition->week_number),
            'count' => (clone $query)->count(),
            'status_counts' => $this->evaluationHasStatus($runtimeWeek->definition->week_number) ? $this->statusCounts($query) : [],
            'note' => 'Evaluation records are immutable historical results.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kpiSummary(SectionSimulationWeek $runtimeWeek): array
    {
        $base = KpiSnapshot::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id);

        $total = (clone $base)->count();
        $available = (clone $base)->where('status', KpiSnapshotStatus::Available->value)->count();
        $unavailable = (clone $base)->where('status', KpiSnapshotStatus::Unavailable->value)->count();

        return [
            'total' => $total,
            'available' => $available,
            'unavailable' => $unavailable,
            'note' => $total === 0
                ? 'No KPI snapshots exist for this week yet.'
                : 'Unavailable KPI values remain null until an authoritative mapping exists.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rankingSummary(SectionSimulationWeek $runtimeWeek): array
    {
        $base = RankingSnapshot::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id);

        $total = (clone $base)->count();
        $complete = (clone $base)->where('status', RankingSnapshotStatus::Complete->value)->count();
        $incomplete = (clone $base)->where('status', RankingSnapshotStatus::Incomplete->value)->count();

        return [
            'total' => $total,
            'complete' => $complete,
            'incomplete' => $incomplete,
            'note' => $incomplete > 0
                ? 'Incomplete rankings are preserved when the KPI basis is not complete.'
                : 'Ranking snapshots appear after supported KPI calculation runs.',
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @return array<string, int>
     */
    private function statusCounts(Builder $query): array
    {
        return $query
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (int|string $count): int => (int) $count)
            ->all();
    }

    private function latestExecution(SectionSimulationWeek $runtimeWeek): ?WeekExecutionRecord
    {
        return WeekExecutionRecord::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->latest('id')
            ->first();
    }

    private function executionTimestamp(?WeekExecutionRecord $execution): ?string
    {
        if (! $execution instanceof WeekExecutionRecord) {
            return null;
        }

        foreach (['completed_at', 'failed_at', 'started_at'] as $attribute) {
            $value = $execution->getAttribute($attribute);

            if ($value instanceof DateTimeInterface) {
                return $value->format('M j, Y g:i A');
            }

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array{status: string, detail: string, artifact_count: int, package_version: string|null}
     */
    private function contentStatus(
        SectionSimulationWeek $runtimeWeek,
        SimulationContentResolver $contentResolver,
        User $actor,
        AuthoritativeContentPackageManifest $authoritativePackages,
    ): array {
        $packageType = $this->packageTypeFor($runtimeWeek->definition->week_number, $authoritativePackages);

        try {
            $package = $contentResolver->activePackageFor($runtimeWeek, $packageType);
            $artifactCount = $contentResolver->authorizedArtifactsFor($actor, $runtimeWeek, $packageType)->count();

            return [
                'status' => 'active',
                'detail' => "{$package->package_type} {$package->version}",
                'artifact_count' => $artifactCount,
                'package_version' => $package->version,
            ];
        } catch (InvalidArgumentException $exception) {
            return [
                'status' => 'missing',
                'detail' => $exception->getMessage(),
                'artifact_count' => 0,
                'package_version' => null,
            ];
        }
    }

    private function packageTypeFor(int $weekNumber, AuthoritativeContentPackageManifest $authoritativePackages): string
    {
        if (in_array($weekNumber, $authoritativePackages->registrableWeeks(), true)) {
            return $authoritativePackages->packageType($weekNumber);
        }

        return match ($weekNumber) {
            6 => 'week6_reference_package',
            8 => 'week8_reference_package',
            default => 'reference_package',
        };
    }

    /**
     * @return class-string<Model>|null
     */
    private function evaluationModelForWeek(int $weekNumber): ?string
    {
        return match ($weekNumber) {
            1 => Week1EconomicEvaluation::class,
            4 => EconomicResolution::class,
            5 => Week5EconomicEvaluation::class,
            6 => CapitalAllocationEvaluation::class,
            8 => Week8EconomicEvaluation::class,
            9 => Week9EconomicEvaluation::class,
            10 => Week10EconomicEvaluation::class,
            11 => Week11EconomicEvaluation::class,
            12 => Week12EconomicEvaluation::class,
            13 => Week13EconomicEvaluation::class,
            default => null,
        };
    }

    private function evaluationLabelForWeek(int $weekNumber): string
    {
        return match ($weekNumber) {
            1 => 'Week 1 asset-register evaluations',
            4 => 'Week 4 economic resolutions',
            5 => 'Week 5 currency evaluations',
            6 => 'Week 6 capital evaluations',
            8 => 'Week 8 scenario evaluations',
            9 => 'Week 9 retail evaluations',
            10 => 'Week 10 convergence evaluations',
            11 => 'Week 11 Kessana evaluations',
            12 => 'Week 12 portfolio evaluations',
            13 => 'Week 13 factor-market evaluations',
            default => 'Economic evaluations',
        };
    }

    private function evaluationHasStatus(int $weekNumber): bool
    {
        return $weekNumber !== 4;
    }
}
