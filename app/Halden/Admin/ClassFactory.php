<?php

namespace App\Halden\Admin;

use App\Models\Quarter;
use App\Models\Section;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Makes a class: a section for one instructor, its fourteen company quarters with weekly deadlines, and optionally
 * empty teams. A 7-week class plays two quarters a week: the deadline sits on the first quarter of each pair. Used
 * by the admin screen and by `php artisan halden:class`.
 */
final class ClassFactory
{
    /** The deadline each week lands on when none is given: the next Thursday at 5 pm Eastern. */
    public static function defaultFirstDeadline(): CarbonImmutable
    {
        return CarbonImmutable::now('America/New_York')->next('Thursday')->setTime(17, 0)->utc();
    }

    public function create(string $name, string $course, int $weeks, User $faculty, CarbonImmutable $firstDeadline, int $teams = 0, ?int $seats = null): Section
    {
        return DB::transaction(function () use ($name, $course, $weeks, $faculty, $firstDeadline, $teams, $seats): Section {
            $section = Section::query()->create(['name' => $name, 'course_name' => $course, 'weeks' => $weeks, 'faculty_user_id' => $faculty->id, 'seats' => $seats]);
            $this->scheduleQuarters($section, $firstDeadline);
            for ($i = 0; $i < $teams; $i++) {
                Team::query()->create(['section_id' => $section->id, 'name' => 'Team '.chr(ord('A') + $i)]);
            }

            return $section;
        });
    }

    /** Creates the fourteen quarter rows, or moves every deadline that has not passed when the class is rescheduled before it opens. */
    public function scheduleQuarters(Section $section, CarbonImmutable $firstDeadline): void
    {
        $perWeek = intdiv(Quarter::COMPANY_QUARTERS, (int) $section->weeks);
        // Weeks are added in Eastern time so the deadline stays at the same clock time across the change to and from daylight saving.
        $firstEastern = $firstDeadline->setTimezone('America/New_York');
        for ($n = 1; $n <= Quarter::COMPANY_QUARTERS; $n++) {
            $week = intdiv($n - 1, $perWeek);
            $firstOfWeek = ($n - 1) % $perWeek === 0;
            Quarter::query()->updateOrCreate(
                ['section_id' => $section->id, 'number' => $n],
                ['company_quarter' => Quarter::companyQuarterFor($n), 'deadline_at' => $firstOfWeek ? $firstEastern->addWeeks($week)->utc() : null],
            );
        }
    }

    /** Fills a class's teams with demo students (<slug><letter><n>@example.test), for rehearsals. */
    public function fillWithDemoStudents(Section $section, string $password): void
    {
        $slug = Str::slug($section->name);
        foreach ($section->teams()->orderBy('id')->get() as $i => $team) {
            $letter = strtolower(chr(ord('A') + $i));
            foreach (array_keys(TeamMember::SEATS) as $k => $seat) {
                $student = User::query()->updateOrCreate(['email' => "$slug$letter".($k + 1).'@example.test'],
                    ['name' => "{$team->name} student ".($k + 1), 'password' => Hash::make($password), 'role' => User::ROLE_STUDENT, 'email_verified_at' => now()]);
                TeamMember::query()->updateOrCreate(['team_id' => $team->id, 'user_id' => $student->id], ['seat' => $seat]);
            }
        }
    }
}
