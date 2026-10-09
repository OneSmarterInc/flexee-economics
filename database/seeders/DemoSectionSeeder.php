<?php

namespace Database\Seeders;

use App\Models\Quarter;
use App\Models\Section;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A demo class: one faculty member, three teams of five, fourteen quarters with Quarter 1 open.
 *
 * In production this seeds nothing unless SEED_DEMO_PASSWORD is set to 16 or more characters,
 * so the live site never gets accounts with a known password.
 */
class DemoSectionSeeder extends Seeder
{
    public function run(): void
    {
        $plain = (string) config('halden.seed_demo_password', '');
        if (app()->isProduction() && strlen($plain) < 16) {
            $this->command->warn('Skipping demo accounts: set SEED_DEMO_PASSWORD (16+ characters) to seed them in production.');

            return;
        }
        $password = Hash::make($plain !== '' ? $plain : 'password');

        $user = fn (string $email, string $name, string $role): User => User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password, 'role' => $role, 'email_verified_at' => now()],
        );

        $user('admin@example.test', 'Demo Administrator', User::ROLE_ADMIN);
        $faculty = $user('faculty@example.test', 'Demo Faculty', User::ROLE_FACULTY);

        $section = Section::query()->updateOrCreate(
            ['name' => 'Demo section'],
            ['course_name' => 'Managerial Economics (demo)', 'weeks' => 14, 'faculty_user_id' => $faculty->id],
        );

        $seats = array_keys(TeamMember::SEATS);
        foreach (['Alpha', 'Bravo', 'Charlie'] as $teamName) {
            $team = Team::query()->updateOrCreate(['section_id' => $section->id, 'name' => "Team $teamName"]);
            foreach ($seats as $i => $seat) {
                $n = $i + 1;
                $student = $user(strtolower($teamName)."$n@example.test", "$teamName student $n", User::ROLE_STUDENT);
                TeamMember::query()->updateOrCreate(['team_id' => $team->id, 'user_id' => $student->id], ['seat' => $seat]);
            }
        }

        $firstDeadline = CarbonImmutable::now('America/New_York')->next('Thursday')->setTime(17, 0)->utc();
        for ($n = 1; $n <= 14; $n++) {
            Quarter::query()->updateOrCreate(
                ['section_id' => $section->id, 'number' => $n],
                [
                    'company_quarter' => Quarter::companyQuarterFor($n),
                    'status' => $n === 1 ? Quarter::OPEN : Quarter::UPCOMING,
                    'deadline_at' => $firstDeadline->addWeeks($n - 1),
                    'opened_at' => $n === 1 ? now() : null,
                ],
            );
        }
    }
}
