<?php

namespace App\Livewire;

use App\Domain\WhatIf\Week4WhatIfSimulationService;
use App\Models\EconomicResolution;
use App\Models\SectionSimulation;
use App\Models\WhatIfRun;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class FacultyWhatIfConsole extends Component
{
    public ?int $sectionSimulationId = null;

    public ?int $teamSimulationId = null;

    public ?int $sourceResolutionId = null;

    public string $transferPrice = '18.70';

    public ?int $latestRunId = null;

    public function updatedSectionSimulationId(): void
    {
        $this->teamSimulationId = null;
        $this->sourceResolutionId = null;
        $this->latestRunId = null;
    }

    public function updatedTeamSimulationId(): void
    {
        $this->sourceResolutionId = null;
        $this->latestRunId = null;
    }

    public function runScenario(Week4WhatIfSimulationService $whatIf): void
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $source = EconomicResolution::query()->findOrFail($this->sourceResolutionId);
        $run = $whatIf->runTransferPriceScenario($source, $this->transferPrice, $user);

        $this->latestRunId = $run->id;
    }

    public function render(): View
    {
        $user = auth()->user();

        abort_unless($user && ($user->isAdministrator() || $user->isFaculty()), 403);

        $sectionSimulations = $this->authorizedSectionSimulations();
        $this->normalizeSelections($sectionSimulations);
        $selectedSectionSimulation = $sectionSimulations->firstWhere('id', $this->sectionSimulationId);
        $teamSimulations = $selectedSectionSimulation instanceof SectionSimulation
            ? $selectedSectionSimulation->teamSimulations
            : new Collection;
        $sourceResolutions = $this->sourceResolutions();
        $latestRun = $this->latestRunId !== null
            ? WhatIfRun::query()->where('tenant_id', $user->tenant_id)->whereKey($this->latestRunId)->first()
            : null;

        return view('livewire.faculty-what-if-console', [
            'sectionSimulations' => $sectionSimulations,
            'teamSimulations' => $teamSimulations,
            'sourceResolutions' => $sourceResolutions,
            'latestRun' => $latestRun,
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
            ->with(['section.course', 'teamSimulations.team'])
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
            $this->teamSimulationId = null;
            $this->sourceResolutionId = null;

            return;
        }

        $team = $selectedSection->teamSimulations->firstWhere('id', $this->teamSimulationId)
            ?? $selectedSection->teamSimulations->sortBy('id')->first();
        $this->teamSimulationId = $team?->id;

        $source = $this->sourceResolutions()->firstWhere('id', $this->sourceResolutionId)
            ?? $this->sourceResolutions()->first();
        $this->sourceResolutionId = $source?->id;
    }

    /**
     * @return Collection<int, EconomicResolution>
     */
    private function sourceResolutions(): Collection
    {
        $user = auth()->user();

        if ($this->teamSimulationId === null) {
            return new Collection;
        }

        return EconomicResolution::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('team_simulation_id', $this->teamSimulationId)
            ->with('runtimeWeek.definition')
            ->orderBy('section_simulation_week_id')
            ->orderBy('id')
            ->get();
    }
}
