<?php

namespace App\Http\Controllers;

use App\Enums\PlatformRole;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\Enrollment;
use App\Models\SectionSimulation;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user()->load('tenant');
        $role = $user->global_role instanceof PlatformRole ? $user->global_role->value : (string) $user->global_role;

        $enrollments = Enrollment::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->with('section.course.institution')
            ->get();

        $teams = Team::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereHas('members', fn ($query) => $query->whereKey($user->id))
            ->with('section.course')
            ->get();

        $visibleWeekStatuses = [
            SectionSimulationWeekStatus::Released->value,
            SectionSimulationWeekStatus::Open->value,
            SectionSimulationWeekStatus::Closed->value,
            SectionSimulationWeekStatus::Published->value,
        ];

        $sectionIds = $enrollments->pluck('section_id');
        $sectionSimulations = SectionSimulation::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('section_id', $sectionIds)
            ->with([
                'section.course',
                'simulation',
                'variant',
                'version',
                'weeks' => fn ($query) => $query
                    ->whereIn('status', $visibleWeekStatuses)
                    ->with('definition'),
            ])
            ->get();

        return Inertia::render('Dashboard', [
            'foundation' => [
                'tenant' => $user->tenant?->only(['name', 'slug']),
                'role' => $role,
                'enrollments' => $enrollments->map(fn (Enrollment $enrollment) => [
                    'section' => $enrollment->section->name,
                    'course' => $enrollment->section->course->name,
                    'institution' => $enrollment->section->course->institution->name,
                    'status' => $enrollment->status,
                ])->values(),
                'teams' => $teams->map(fn (Team $team) => [
                    'name' => $team->name,
                    'section' => $team->section->name,
                    'course' => $team->section->course->name,
                ])->values(),
                'simulations' => $sectionSimulations
                    ->filter(fn (SectionSimulation $sectionSimulation) => $sectionSimulation->weeks->isNotEmpty())
                    ->map(fn (SectionSimulation $sectionSimulation) => [
                        'name' => $sectionSimulation->name,
                        'simulation' => $sectionSimulation->simulation->name,
                        'variant' => $sectionSimulation->variant->name,
                        'version' => $sectionSimulation->version->version,
                        'section' => $sectionSimulation->section->name,
                        'course' => $sectionSimulation->section->course->name,
                        'weeks' => $sectionSimulation->weeks
                            ->sortBy(fn ($runtimeWeek) => $runtimeWeek->definition->week_number)
                            ->map(fn ($runtimeWeek) => [
                                'number' => $runtimeWeek->definition->week_number,
                                'title' => $runtimeWeek->definition->title,
                                'status' => $runtimeWeek->status->value,
                            ])
                            ->values(),
                    ])
                    ->values(),
            ],
        ]);
    }
}
