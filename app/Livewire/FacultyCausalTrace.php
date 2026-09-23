<?php

namespace App\Livewire;

use App\Domain\CausalTrace\CausalTrace;
use App\Domain\CausalTrace\CausalTraceService;
use App\Models\ConsequenceLink;
use App\Models\DecisionSubmission;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\TeamSimulation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class FacultyCausalTrace extends Component
{
    public ?int $sectionSimulationId = null;

    public ?int $teamSimulationId = null;

    public ?int $runtimeWeekId = null;

    public string $sourceType = 'decision';

    public function updatedSectionSimulationId(): void
    {
        $this->teamSimulationId = null;
        $this->runtimeWeekId = null;
    }

    /**
     * @return list<string>
     */
    public function sourceTypes(): array
    {
        return ['decision', 'consequence'];
    }

    public function render(CausalTraceService $traces): View
    {
        $user = auth()->user();

        abort_unless($user && ($user->isAdministrator() || $user->isFaculty()), 403);

        $sectionSimulations = $this->authorizedSectionSimulations();
        $this->normalizeSelections($sectionSimulations);

        $selectedSectionSimulation = $sectionSimulations->firstWhere('id', $this->sectionSimulationId);
        $teamSimulations = $selectedSectionSimulation instanceof SectionSimulation
            ? $selectedSectionSimulation->teamSimulations
            : new Collection;
        $runtimeWeeks = $selectedSectionSimulation instanceof SectionSimulation
            ? $selectedSectionSimulation->weeks
            : new Collection;
        $trace = $this->selectedTrace($traces);

        return view('livewire.faculty-causal-trace', [
            'sectionSimulations' => $sectionSimulations,
            'teamSimulations' => $teamSimulations,
            'runtimeWeeks' => $runtimeWeeks,
            'trace' => $trace,
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
        if (! in_array($this->sourceType, $this->sourceTypes(), true)) {
            $this->sourceType = 'decision';
        }

        $selectedSection = $sectionSimulations->firstWhere('id', $this->sectionSimulationId)
            ?? $sectionSimulations->first();

        $this->sectionSimulationId = $selectedSection?->id;

        if (! $selectedSection instanceof SectionSimulation) {
            $this->teamSimulationId = null;
            $this->runtimeWeekId = null;

            return;
        }

        $team = $selectedSection->teamSimulations->firstWhere('id', $this->teamSimulationId)
            ?? $selectedSection->teamSimulations->sortBy('id')->first();
        $week = $selectedSection->weeks->firstWhere('id', $this->runtimeWeekId)
            ?? $selectedSection->weeks->sortBy(fn (SectionSimulationWeek $runtimeWeek): int => $runtimeWeek->definition->week_number)->first();

        $this->teamSimulationId = $team?->id;
        $this->runtimeWeekId = $week?->id;
    }

    private function selectedTrace(CausalTraceService $traces): ?CausalTrace
    {
        $user = auth()->user();

        if ($this->teamSimulationId === null || $this->runtimeWeekId === null) {
            return null;
        }

        $teamSimulation = TeamSimulation::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($this->teamSimulationId)
            ->first();

        if (! $teamSimulation instanceof TeamSimulation) {
            return null;
        }

        if ($this->sourceType === 'consequence') {
            $consequence = ConsequenceLink::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->where('source_section_simulation_week_id', $this->runtimeWeekId)
                ->orderBy('id')
                ->first();

            return $consequence instanceof ConsequenceLink
                ? $traces->backwardFromConsequence($user, $consequence)
                : null;
        }

        $decision = DecisionSubmission::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('section_simulation_week_id', $this->runtimeWeekId)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->first();

        return $decision instanceof DecisionSubmission
            ? $traces->forwardFromDecision($user, $decision)
            : null;
    }
}
