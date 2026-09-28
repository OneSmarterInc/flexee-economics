<?php

namespace App\Livewire;

use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\SimulationContentResolver;
use App\Domain\Content\Week6\Week6ContentPackageManifest;
use App\Domain\Content\Week8\Week8ContentPackageManifest;
use App\Domain\Execution\WeekExecutionService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionCompletenessService;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\CapitalAllocationDecision;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\WeekExecutionRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;

class FacultyWeekControl extends Component
{
    public ?int $sectionSimulationId = null;

    public ?int $runtimeWeekId = null;

    public function updatedSectionSimulationId(): void
    {
        $this->runtimeWeekId = null;
        $this->resetErrorBag();
    }

    public function updatedRuntimeWeekId(): void
    {
        $this->resetErrorBag();
    }

    public function transitionSelectedWeek(string $status): void
    {
        $runtimeWeek = $this->selectedRuntimeWeek();

        if (! $runtimeWeek instanceof SectionSimulationWeek) {
            $this->addError('runtimeWeek', 'Select a simulation week first.');

            return;
        }

        Gate::authorize('update', $runtimeWeek);

        app(SimulationLifecycleService::class)->transitionWeek(
            $runtimeWeek,
            SectionSimulationWeekStatus::from($status),
            auth()->user(),
        );

        $this->resetErrorBag();
    }

    public function executeSelectedWeek(): void
    {
        $runtimeWeek = $this->selectedRuntimeWeek();

        if (! $runtimeWeek instanceof SectionSimulationWeek) {
            $this->addError('execution', 'Select a simulation week first.');

            return;
        }

        try {
            app(WeekExecutionService::class)->execute($runtimeWeek, auth()->user(), $this->packageTypeFor($runtimeWeek));
            $this->resetErrorBag();
        } catch (InvalidArgumentException $exception) {
            $this->addError('execution', $exception->getMessage());
        }
    }

    public function render(
        SimulationContentResolver $content,
        SubmissionCompletenessService $completeness,
        SimulationLifecycleService $lifecycle,
    ): View {
        $user = auth()->user();

        abort_unless($user && ($user->isAdministrator() || $user->isFaculty()), 403);

        $sectionSimulations = $this->authorizedSectionSimulations();
        $this->normalizeSelections($sectionSimulations);

        $selectedSectionSimulation = $sectionSimulations->firstWhere('id', $this->sectionSimulationId);
        $runtimeWeeks = $selectedSectionSimulation instanceof SectionSimulation
            ? $selectedSectionSimulation->weeks->sortBy(fn (SectionSimulationWeek $week): int => $week->definition->week_number)->values()
            : new Collection;
        $selectedRuntimeWeek = $runtimeWeeks->firstWhere('id', $this->runtimeWeekId);
        $executionRecord = $selectedRuntimeWeek instanceof SectionSimulationWeek
            ? $this->executionRecord($selectedRuntimeWeek)
            : null;

        return view('livewire.faculty-week-control', [
            'sectionSimulations' => $sectionSimulations,
            'runtimeWeeks' => $runtimeWeeks,
            'selectedSectionSimulation' => $selectedSectionSimulation,
            'selectedRuntimeWeek' => $selectedRuntimeWeek,
            'contentState' => $selectedRuntimeWeek instanceof SectionSimulationWeek
                ? $this->contentState($content, $selectedRuntimeWeek)
                : null,
            'submissionState' => $selectedSectionSimulation instanceof SectionSimulation && $selectedRuntimeWeek instanceof SectionSimulationWeek
                ? $this->submissionState($selectedSectionSimulation, $selectedRuntimeWeek, $completeness)
                : null,
            'executionRecord' => $executionRecord,
            'executionSteps' => $executionRecord instanceof WeekExecutionRecord ? $executionRecord->steps ?? [] : [],
            'validTransitions' => $selectedRuntimeWeek instanceof SectionSimulationWeek
                ? $lifecycle->validTransitions()[$selectedRuntimeWeek->statusValue()] ?? []
                : [],
        ]);
    }

    /**
     * @return Collection<int, SectionSimulation>
     */
    private function authorizedSectionSimulations(): Collection
    {
        $user = auth()->user();

        return SectionSimulation::query()
            ->forTenant($user->tenant_id)
            ->when($user->isFaculty() && ! $user->isAdministrator(), function ($query) use ($user): void {
                $query->whereIn('section_id', $user->facultySections()->select('sections.id'));
            })
            ->with([
                'section.course',
                'variant',
                'version',
                'teamSimulations.team',
                'weeks.definition',
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, SectionSimulation>  $sectionSimulations
     */
    private function normalizeSelections(Collection $sectionSimulations): void
    {
        $selectedSection = $sectionSimulations->firstWhere('id', $this->sectionSimulationId)
            ?? $sectionSimulations->first();

        $this->sectionSimulationId = $selectedSection?->id;

        if (! $selectedSection instanceof SectionSimulation) {
            $this->runtimeWeekId = null;

            return;
        }

        $selectedWeek = $selectedSection->weeks->firstWhere('id', $this->runtimeWeekId)
            ?? $selectedSection->weeks
                ->sortBy(fn (SectionSimulationWeek $week): int => $week->definition->week_number)
                ->first();

        $this->runtimeWeekId = $selectedWeek?->id;
    }

    private function selectedRuntimeWeek(): ?SectionSimulationWeek
    {
        $user = auth()->user();

        if ($user === null || $this->runtimeWeekId === null) {
            return null;
        }

        return SectionSimulationWeek::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($this->runtimeWeekId)
            ->with(['definition', 'sectionSimulation.section'])
            ->first();
    }

    /**
     * @return array{status: string, package_version: string|null, artifact_count: int, message: string|null}
     */
    private function contentState(SimulationContentResolver $content, SectionSimulationWeek $runtimeWeek): array
    {
        try {
            $packageType = $this->packageTypeFor($runtimeWeek);
            $package = $content->activePackageFor($runtimeWeek, $packageType);
            $artifacts = $content->authorizedArtifactsFor(auth()->user(), $runtimeWeek, $packageType);

            return [
                'status' => 'validated',
                'package_version' => $package->version,
                'artifact_count' => $artifacts->count(),
                'message' => null,
            ];
        } catch (InvalidArgumentException $exception) {
            return [
                'status' => 'unavailable',
                'package_version' => null,
                'artifact_count' => 0,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{team_count: int, decision_submitted_count: int, memo_submitted_count: int, capital_allocation_submitted_count: int, complete_count: int}
     */
    private function submissionState(SectionSimulation $sectionSimulation, SectionSimulationWeek $runtimeWeek, SubmissionCompletenessService $completeness): array
    {
        $decisionSubmitted = 0;
        $memoSubmitted = 0;
        $capitalAllocationsSubmitted = 0;
        $complete = 0;

        foreach ($sectionSimulation->teamSimulations as $teamSimulation) {
            $status = $completeness->statusFor($runtimeWeek, $teamSimulation);

            if ($status['decision_status'] === 'submitted') {
                $decisionSubmitted++;
            }

            if ($status['memo_status'] === 'submitted') {
                $memoSubmitted++;
            }

            $hasCapitalAllocation = CapitalAllocationDecision::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->exists();

            if ($hasCapitalAllocation) {
                $capitalAllocationsSubmitted++;
            }

            if ($status['complete']) {
                $complete++;
            }
        }

        return [
            'team_count' => $sectionSimulation->teamSimulations->count(),
            'decision_submitted_count' => $decisionSubmitted,
            'memo_submitted_count' => $memoSubmitted,
            'capital_allocation_submitted_count' => $capitalAllocationsSubmitted,
            'complete_count' => $complete,
        ];
    }

    private function executionRecord(SectionSimulationWeek $runtimeWeek): ?WeekExecutionRecord
    {
        return WeekExecutionRecord::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->latest('started_at')
            ->first();
    }

    private function packageTypeFor(SectionSimulationWeek $runtimeWeek): string
    {
        return match ($runtimeWeek->definition->week_number) {
            6 => Week6ContentPackageManifest::PACKAGE_TYPE,
            8 => Week8ContentPackageManifest::PACKAGE_TYPE,
            5, 9, 10, 11 => app(AuthoritativeContentPackageManifest::class)->packageType($runtimeWeek->definition->week_number),
            default => 'reference_package',
        };
    }
}
