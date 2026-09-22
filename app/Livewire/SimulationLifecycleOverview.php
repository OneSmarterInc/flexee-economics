<?php

namespace App\Livewire;

use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionCompletenessService;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class SimulationLifecycleOverview extends Component
{
    public function transitionWeek(int $runtimeWeekId, string $status): void
    {
        $runtimeWeek = SectionSimulationWeek::query()->findOrFail($runtimeWeekId);

        Gate::authorize('update', $runtimeWeek);

        app(SimulationLifecycleService::class)
            ->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::from($status), auth()->user());
    }

    public function render(SubmissionCompletenessService $completeness): View
    {
        $user = auth()->user();

        abort_unless($user && ($user->isAdministrator() || $user->isFaculty()), 403);

        $sectionSimulations = SectionSimulation::query()
            ->forTenant($user->tenant_id)
            ->when($user->isFaculty() && ! $user->isAdministrator(), function ($query) use ($user): void {
                $query->whereIn('section_id', $user->facultySections()->select('sections.id'));
            })
            ->with(['section.course', 'simulation', 'variant', 'version', 'teamSimulations.team', 'weeks.definition.decisionFormDefinitions', 'weeks.definition.memoDefinitions'])
            ->orderBy('name')
            ->get();

        $teamStatuses = [];

        foreach ($sectionSimulations as $sectionSimulation) {
            foreach ($sectionSimulation->weeks as $week) {
                foreach ($sectionSimulation->teamSimulations as $teamSimulation) {
                    $teamStatuses[$week->id][$teamSimulation->id] = $completeness->statusFor($week, $teamSimulation);
                }
            }
        }

        return view('livewire.simulation-lifecycle-overview', [
            'sectionSimulations' => $sectionSimulations,
            'validTransitions' => app(SimulationLifecycleService::class)->validTransitions(),
            'teamStatuses' => $teamStatuses,
        ]);
    }
}
