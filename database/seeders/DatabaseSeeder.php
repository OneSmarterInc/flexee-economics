<?php

namespace Database\Seeders;

use App\Enums\PlatformRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institution;
use App\Models\Seat;
use App\Models\Section;
use App\Models\SectionFaculty;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tenant;
use App\Models\User;
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
    }
}
