<?php

namespace App\Http\Controllers;

use App\Enums\PlatformRole;
use App\Models\Enrollment;
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
            ],
        ]);
    }
}
