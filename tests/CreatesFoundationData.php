<?php

namespace Tests;

use App\Enums\PlatformRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institution;
use App\Models\Section;
use App\Models\SectionFaculty;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tenant;
use App\Models\User;

trait CreatesFoundationData
{
    protected function tenantGraph(string $suffix = ''): array
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Tenant '.$suffix,
            'slug' => 'tenant-'.strtolower($suffix).'-'.uniqid(),
        ]);

        $institution = Institution::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Institution '.$suffix,
        ]);

        $course = Course::factory()->create([
            'tenant_id' => $tenant->id,
            'institution_id' => $institution->id,
            'name' => 'Managerial Economics '.$suffix,
        ]);

        $section = Section::factory()->create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'name' => 'Section '.$suffix,
        ]);

        $team = Team::factory()->create([
            'tenant_id' => $tenant->id,
            'section_id' => $section->id,
            'name' => 'Team '.$suffix,
            'slug' => 'team-'.strtolower($suffix),
        ]);

        $admin = User::factory()->administrator()->create([
            'tenant_id' => $tenant->id,
            'email' => 'admin-'.$suffix.'@example.test',
        ]);

        $faculty = User::factory()->faculty()->create([
            'tenant_id' => $tenant->id,
            'email' => 'faculty-'.$suffix.'@example.test',
        ]);

        $student = User::factory()->student()->create([
            'tenant_id' => $tenant->id,
            'email' => 'student-'.$suffix.'@example.test',
        ]);

        SectionFaculty::query()->create([
            'tenant_id' => $tenant->id,
            'section_id' => $section->id,
            'user_id' => $faculty->id,
            'role' => 'instructor',
        ]);

        Enrollment::query()->create([
            'tenant_id' => $tenant->id,
            'section_id' => $section->id,
            'user_id' => $student->id,
            'status' => 'active',
        ]);

        TeamMember::query()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'user_id' => $student->id,
        ]);

        return compact('tenant', 'institution', 'course', 'section', 'team', 'admin', 'faculty', 'student');
    }

    protected function userFor(Tenant $tenant, PlatformRole $role): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'global_role' => $role,
        ]);
    }
}
