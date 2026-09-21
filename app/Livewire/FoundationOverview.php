<?php

namespace App\Livewire;

use App\Models\Course;
use App\Models\Section;
use App\Models\Team;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class FoundationOverview extends Component
{
    public function render(): View
    {
        $user = auth()->user();

        abort_unless($user && ($user->isAdministrator() || $user->isFaculty()), 403);

        $courses = Course::query()
            ->forTenant($user->tenant_id)
            ->with('sections.teams')
            ->orderBy('code')
            ->get();

        $sections = Section::query()
            ->forTenant($user->tenant_id)
            ->when($user->isFaculty() && ! $user->isAdministrator(), function ($query) use ($user): void {
                $query->whereIn('id', $user->facultySections()->select('sections.id'));
            })
            ->with(['course', 'teams'])
            ->orderBy('name')
            ->get();

        return view('livewire.foundation-overview', [
            'courses' => $courses,
            'sections' => $sections,
            'teamCount' => Team::query()->forTenant($user->tenant_id)->count(),
        ]);
    }
}
