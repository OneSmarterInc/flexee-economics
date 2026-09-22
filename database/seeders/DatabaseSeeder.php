<?php

namespace Database\Seeders;

use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\PlatformRole;
use App\Enums\SectionSimulationWeekStatus;
use App\Enums\SimulationVersionStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institution;
use App\Models\Seat;
use App\Models\Section;
use App\Models\SectionFaculty;
use App\Models\SectionSimulation;
use App\Models\Simulation;
use App\Models\SimulationVariant;
use App\Models\SimulationVersion;
use App\Models\SimulationWeek;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WeekContentVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'halden-university-demo'],
            ['name' => 'Halden University Demo', 'status' => 'active'],
        );

        $institution = Institution::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'halden-university-demo',
        ], [
            'name' => 'Halden University Demo',
        ]);

        $course = Course::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'institution_id' => $institution->id,
            'code' => 'ECON-501',
            'term' => '2026 Fall',
        ], [
            'name' => 'Managerial Economics',
        ]);

        $sectionA = Section::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'name' => 'Section A',
        ], [
            'status' => 'active',
        ]);

        $sectionB = Section::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'name' => 'Section B',
        ], [
            'status' => 'active',
        ]);

        $seats = collect([
            ['code' => 'evp', 'name' => 'EVP', 'sort_order' => 1],
            ['code' => 'upstream-head', 'name' => 'Upstream Segment Head', 'sort_order' => 2],
            ['code' => 'refining-head', 'name' => 'Refining Segment Head', 'sort_order' => 3],
            ['code' => 'trading-head', 'name' => 'Trading Segment Head', 'sort_order' => 4],
            ['code' => 'retail-head', 'name' => 'Retail Segment Head', 'sort_order' => 5],
        ])->map(fn (array $seat) => Seat::query()->firstOrCreate(['code' => $seat['code']], $seat));

        $password = Hash::make('password');

        User::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'email' => 'admin@example.test',
        ], [
            'name' => 'Demo Administrator',
            'password' => $password,
            'global_role' => PlatformRole::Administrator,
            'email_verified_at' => now(),
        ]);

        $faculty = User::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'email' => 'faculty@example.test',
        ], [
            'name' => 'Demo Faculty',
            'password' => $password,
            'global_role' => PlatformRole::Faculty,
            'email_verified_at' => now(),
        ]);

        foreach ([$sectionA, $sectionB] as $section) {
            SectionFaculty::query()->firstOrCreate([
                'tenant_id' => $tenant->id,
                'section_id' => $section->id,
                'user_id' => $faculty->id,
            ], [
                'role' => 'instructor',
            ]);
        }

        foreach ([$sectionA, $sectionB] as $sectionIndex => $section) {
            foreach (range(1, 5) as $studentIndex) {
                $student = User::query()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'email' => 'student'.($sectionIndex + 1).$studentIndex.'@example.test',
                ], [
                    'name' => 'Demo Student '.($sectionIndex + 1).$studentIndex,
                    'password' => $password,
                    'global_role' => PlatformRole::Student,
                    'email_verified_at' => now(),
                ]);

                Enrollment::query()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'section_id' => $section->id,
                    'user_id' => $student->id,
                ], [
                    'status' => 'active',
                ]);
            }
        }

        foreach ([[$sectionA, 'Alpha'], [$sectionB, 'Bravo']] as [$section, $teamName]) {
            $team = Team::query()->firstOrCreate([
                'tenant_id' => $tenant->id,
                'section_id' => $section->id,
                'slug' => strtolower($teamName),
            ], [
                'name' => 'Team '.$teamName,
            ]);

            $students = $section->enrollments()->with('user')->limit(5)->get()->pluck('user');

            foreach ($students->values() as $index => $student) {
                TeamMember::query()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'team_id' => $team->id,
                    'user_id' => $student->id,
                ], [
                    'seat_id' => $seats->values()->get($index)?->id,
                ]);
            }
        }

        $simulation = Simulation::query()->firstOrCreate([
            'slug' => 'halden-energy',
        ], [
            'name' => 'Halden Energy',
            'description' => 'Reusable Halden Energy simulation definition.',
            'status' => 'active',
            'metadata' => ['seeded_for' => 'batch-2-demo'],
        ]);

        $variant = SimulationVariant::query()->firstOrCreate([
            'simulation_id' => $simulation->id,
            'slug' => 'fourteen-week',
        ], [
            'name' => 'Fourteen-week flagship',
            'duration_weeks' => 14,
            'metadata' => ['cadence' => 'weekly'],
        ]);

        $version = SimulationVersion::query()->firstOrCreate([
            'simulation_variant_id' => $variant->id,
            'version' => '2026-demo',
        ], [
            'simulation_id' => $simulation->id,
            'status' => SimulationVersionStatus::Published,
            'config_hash' => 'halden-demo-structural-v1',
            'configuration' => [
                'source' => 'structural-demo',
                'economics' => 'not-included',
            ],
            'notes' => 'Batch 2 seeded structure only.',
            'published_at' => now(),
        ]);

        $weekTitles = [
            1 => 'Asset register',
            2 => 'Elasticity estimation',
            3 => 'Shutdown point',
            4 => 'Transfer pricing',
            5 => 'Currency',
            6 => 'Capital allocation',
            7 => 'Competitive response',
            8 => 'OPEC',
            9 => 'Retail branding',
            10 => 'Recession',
            11 => 'Kessana hold-up',
            12 => 'Transition portfolio',
            13 => 'Factor markets',
            14 => 'Board defense',
        ];

        foreach ($weekTitles as $weekNumber => $title) {
            $week = SimulationWeek::query()->firstOrCreate([
                'simulation_version_id' => $version->id,
                'week_number' => $weekNumber,
            ], [
                'simulation_id' => $simulation->id,
                'simulation_variant_id' => $variant->id,
                'slug' => 'week-'.$weekNumber,
                'title' => $title,
                'pattern' => 'weekly-briefing',
                'status' => 'active',
                'content_metadata' => ['placeholder' => true],
            ]);

            WeekContentVersion::query()->firstOrCreate([
                'simulation_week_id' => $week->id,
                'version' => 'placeholder-v1',
            ], [
                'simulation_version_id' => $version->id,
                'status' => 'placeholder',
                'metadata' => ['placeholder' => true],
            ]);
        }

        $sectionSimulation = SectionSimulation::query()
            ->where('tenant_id', $tenant->id)
            ->where('section_id', $sectionA->id)
            ->where('simulation_version_id', $version->id)
            ->first();

        if (! $sectionSimulation) {
            $sectionSimulation = app(SimulationLifecycleService::class)
                ->assignToSection($sectionA, $version, $faculty, 'Section A Demo Halden Energy');
        }

        $weekOne = $sectionSimulation->weeks()
            ->whereHas('definition', fn ($query) => $query->where('week_number', 1))
            ->first();

        if ($weekOne && $weekOne->statusEnum() === SectionSimulationWeekStatus::Draft) {
            app(SimulationLifecycleService::class)
                ->transitionWeek($weekOne, SectionSimulationWeekStatus::Released, $faculty);
        }
    }
}
