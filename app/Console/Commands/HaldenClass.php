<?php

namespace App\Console\Commands;

use App\Models\Quarter;
use App\Models\Section;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates a class: a section for one instructor, its fourteen company quarters with weekly deadlines, and optionally
 * empty teams (five seats, filled with `halden:user --team`) or, outside production, demo teams with known logins.
 *
 * A 7-week class plays two quarters a week: the deadline sits on the first quarter of each pair.
 */
class HaldenClass extends Command
{
    protected $signature = 'halden:class {name : The class name, e.g. "MBA 7250 Spring 2027"}
        {--faculty= : The instructor\'s login email (must exist; create it with halden:user)}
        {--course=Managerial Economics : The course name shown to students}
        {--weeks=14 : 14 (one quarter a week) or 7 (two quarters a week)}
        {--first-deadline= : The first deadline, e.g. "2027-01-14 17:00" Eastern; default next Thursday 5 pm}
        {--teams=0 : Teams to create, named Team A, Team B, ...}
        {--demo-password= : Fill each team with five demo students (<team>1@... to <team>5@example.test) using this password; outside production only, unless 16+ characters}';

    protected $description = 'Create a class with its fourteen quarters and weekly deadlines';

    public function handle(): int
    {
        $weeks = (int) $this->option('weeks');
        if (! in_array($weeks, [7, 14], true)) {
            $this->error('--weeks must be 14 or 7.');

            return self::FAILURE;
        }
        $faculty = User::query()->where('email', (string) $this->option('faculty'))->first();
        if ($faculty === null || ! ($faculty->isFaculty() || $faculty->isAdmin())) {
            $this->error('--faculty must be the email of an existing faculty or admin login (see halden:user).');

            return self::FAILURE;
        }
        $name = trim((string) $this->argument('name'));
        if (Section::query()->where('name', $name)->exists()) {
            $this->error("A class called \"$name\" already exists.");

            return self::FAILURE;
        }
        $first = $this->option('first-deadline')
            ? CarbonImmutable::parse((string) $this->option('first-deadline'), 'America/New_York')->utc()
            : CarbonImmutable::now('America/New_York')->next('Thursday')->setTime(17, 0)->utc();

        $section = Section::query()->create(['name' => $name, 'course_name' => (string) $this->option('course'), 'weeks' => $weeks, 'faculty_user_id' => $faculty->id]);
        $perWeek = intdiv(Quarter::COMPANY_QUARTERS, $weeks);
        for ($n = 1; $n <= Quarter::COMPANY_QUARTERS; $n++) {
            $week = intdiv($n - 1, $perWeek);
            $firstOfWeek = ($n - 1) % $perWeek === 0;
            Quarter::query()->create([
                'section_id' => $section->id, 'number' => $n, 'company_quarter' => Quarter::companyQuarterFor($n),
                'status' => Quarter::UPCOMING, 'deadline_at' => $firstOfWeek ? $first->addWeeks($week) : null,
            ]);
        }

        $demo = (string) $this->option('demo-password');
        if ($demo !== '' && app()->isProduction() && strlen($demo) < 16) {
            $this->error('In production a demo password must be 16 characters or more.');

            return self::FAILURE;
        }
        $teams = max(0, (int) $this->option('teams'));
        for ($i = 0; $i < $teams; $i++) {
            $letter = chr(ord('A') + $i);
            $team = Team::query()->create(['section_id' => $section->id, 'name' => "Team $letter"]);
            if ($demo === '') {
                continue;
            }
            $slug = Str::slug($name).strtolower($letter);
            foreach (array_keys(TeamMember::SEATS) as $k => $seat) {
                $student = User::query()->updateOrCreate(['email' => "$slug".($k + 1).'@example.test'],
                    ['name' => "Team $letter student ".($k + 1), 'password' => Hash::make($demo), 'role' => User::ROLE_STUDENT, 'email_verified_at' => now()]);
                TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $student->id, 'seat' => $seat]);
            }
        }

        $this->info("Created \"$name\" ($weeks weeks, {$teams} teams) for {$faculty->email}. Quarter 1 is not open yet; open it from the faculty board.");
        $this->line('First deadline: '.$first->setTimezone('America/New_York')->format('l j M Y, g:i a').' Eastern.');
        if ($demo !== '' && $teams > 0) {
            $this->line('Demo logins: '.Str::slug($name).'a1@example.test … (five per team), with the password you gave.');
        }

        return self::SUCCESS;
    }
}
