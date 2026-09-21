<?php

namespace Tests;

use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\SimulationVersionStatus;
use App\Enums\PlatformRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institution;
use App\Models\SectionSimulation;
use App\Models\Simulation;
use App\Models\SimulationVariant;
use App\Models\SimulationVersion;
use App\Models\SimulationWeek;
use App\Models\Section;
use App\Models\SectionFaculty;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WeekContentVersion;

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

    protected function simulationStructure(int $weeks = 14): array
    {
        $simulation = Simulation::factory()->create([
            'slug' => 'halden-energy-'.uniqid(),
            'name' => 'Halden Energy',
        ]);

        $variant = SimulationVariant::factory()->create([
            'simulation_id' => $simulation->id,
            'slug' => 'fourteen-week-'.uniqid(),
            'name' => 'Fourteen-week flagship',
            'duration_weeks' => $weeks,
        ]);

        $version = SimulationVersion::factory()->published()->create([
            'simulation_id' => $simulation->id,
            'simulation_variant_id' => $variant->id,
            'version' => '2026-demo-'.uniqid(),
            'configuration' => ['economics' => 'not-included'],
        ]);

        $simulationWeeks = collect(range(1, $weeks))->map(function (int $weekNumber) use ($simulation, $variant, $version): SimulationWeek {
            $week = SimulationWeek::factory()->create([
                'simulation_id' => $simulation->id,
                'simulation_variant_id' => $variant->id,
                'simulation_version_id' => $version->id,
                'week_number' => $weekNumber,
                'slug' => 'week-'.$weekNumber,
                'title' => 'Week '.$weekNumber,
            ]);

            WeekContentVersion::factory()->create([
                'simulation_version_id' => $version->id,
                'simulation_week_id' => $week->id,
            ]);

            return $week;
        });

        return compact('simulation', 'variant', 'version', 'simulationWeeks');
    }

    protected function draftSimulationVersion(): SimulationVersion
    {
        $simulation = Simulation::factory()->create();
        $variant = SimulationVariant::factory()->create([
            'simulation_id' => $simulation->id,
        ]);

        return SimulationVersion::factory()->create([
            'simulation_id' => $simulation->id,
            'simulation_variant_id' => $variant->id,
            'status' => SimulationVersionStatus::Draft,
        ]);
    }

    protected function assignSimulation(array $graph, SimulationVersion $version): SectionSimulation
    {
        return app(SimulationLifecycleService::class)
            ->assignToSection($graph['section'], $version, $graph['faculty']);
    }
}
