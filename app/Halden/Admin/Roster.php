<?php

namespace App\Halden\Admin;

use App\Models\Enrolment;
use App\Models\Section;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A class's students and teams: who is enrolled, who sits where, and the ways in (added by the instructor, or the
 * join link). Logins are never deleted here; removing a student takes them off the roster and their team, and
 * adding the same email again puts them back.
 */
final class Roster
{
    /** The seat a sixth team member shares: trading and finance are two jobs, so two people can hold it. */
    public const SHARED_SEAT = 'trading_finance';

    /**
     * Turns what an instructor pasted or uploaded into [email, name] pairs. Each line may be an email, "Name <email>",
     * "email, name" or "name, email" (a CSV row), with a header row ignored. Bad lines come back under `rejected`.
     *
     * @return array{people: list<array{email: string, name: string}>, rejected: list<string>}
     */
    public static function parse(string $text): array
    {
        $people = [];
        $rejected = [];
        $seen = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $raw) {
            $line = trim($raw, " \t\"'");
            if ($line === '') {
                continue;
            }
            $email = null;
            $name = '';
            if (preg_match('/^(.*?)<\s*([^<>\s]+@[^<>\s]+)\s*>$/', $line, $m)) {
                $email = $m[2];
                $name = trim($m[1], " \t\"'");
            } else {
                $parts = array_values(array_filter(array_map(fn (string $p) => trim($p, " \t\"'"), preg_split('/[,;\t]/', $line) ?: []), fn (string $p) => $p !== ''));
                foreach ($parts as $i => $p) {
                    if (str_contains($p, '@')) {
                        $email = $p;
                        unset($parts[$i]);
                        break;
                    }
                }
                $name = implode(' ', $parts);
            }
            $email = strtolower((string) $email);
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                if (! in_array(strtolower($line), ['email', 'email,name', 'name,email', 'email, name', 'name, email'], true)) {
                    $rejected[] = $line;
                }

                continue;
            }
            if (isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $people[] = ['email' => $email, 'name' => $name !== '' ? $name : Str::before($email, '@')];
        }

        return ['people' => $people, 'rejected' => $rejected];
    }

    /**
     * Enrols people by email. A new email gets a student login with a one-time password, returned once and never
     * stored in plain text; an existing student is enrolled as they are (and unblocked). Stops at the seat count.
     *
     * @param  list<array{email: string, name: string}>  $people
     * @return list<array{email: string, name: string, password: string|null, note: string}>
     */
    public function add(Section $section, array $people, ?Team $team = null): array
    {
        if ($team !== null && $team->section_id !== $section->id) {
            throw new RuntimeException('That team is in another class.');
        }
        $out = [];
        foreach ($people as $person) {
            $user = User::query()->where('email', $person['email'])->first();
            if ($user !== null && ! $user->isStudent()) {
                $out[] = ['email' => $person['email'], 'name' => $user->name, 'password' => null, 'note' => 'This email belongs to an instructor login, so it was skipped.'];

                continue;
            }
            $existing = $user !== null && Enrolment::query()->where('section_id', $section->id)->where('user_id', $user->id)->exists();
            if (! $existing && ! $section->hasRoom()) {
                $out[] = ['email' => $person['email'], 'name' => $person['name'], 'password' => null, 'note' => 'The class is full (every seat is taken), so this one was not added.'];

                continue;
            }
            $password = null;
            if ($user === null) {
                $password = Str::password(12, symbols: false);
                $user = User::query()->create(['name' => $person['name'], 'email' => $person['email'], 'password' => Hash::make($password), 'role' => User::ROLE_STUDENT]);
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            Enrolment::query()->updateOrCreate(['section_id' => $section->id, 'user_id' => $user->id], ['status' => Enrolment::ACTIVE]);
            if ($team !== null && ! $this->memberIn($section, $user)) {
                TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $user->id, 'seat' => $this->freeSeat($team)]);
            }
            $out[] = ['email' => $user->email, 'name' => $user->name, 'password' => $password,
                'note' => $password !== null ? 'New login.' : ($existing ? 'Already in the class.' : 'Had a login already; added to the class.')];
        }

        return $out;
    }

    /** A student who followed the join link: a new login, enrolled. */
    public function join(Section $section, string $name, string $email, string $password): User
    {
        if (! $section->hasRoom()) {
            throw new RuntimeException('This class is full. Ask your instructor.');
        }
        $user = User::query()->create(['name' => $name, 'email' => strtolower($email), 'password' => Hash::make($password), 'role' => User::ROLE_STUDENT]);
        $user->forceFill(['email_verified_at' => now()])->save();
        Enrolment::query()->create(['section_id' => $section->id, 'user_id' => $user->id, 'status' => Enrolment::ACTIVE]);

        return $user;
    }

    /** Enrols a student who already has a login and followed the join link. */
    public function joinExisting(Section $section, User $user): void
    {
        if (! $user->isStudent()) {
            throw new RuntimeException('This link is for students. You are signed in as an instructor.');
        }
        if (Enrolment::query()->where('section_id', $section->id)->where('user_id', $user->id)->exists()) {
            return;
        }
        if (! $section->hasRoom()) {
            throw new RuntimeException('This class is full. Ask your instructor.');
        }
        Enrolment::query()->create(['section_id' => $section->id, 'user_id' => $user->id, 'status' => Enrolment::ACTIVE]);
    }

    /** Makes (or replaces) the class's join code. */
    public function newJoinCode(Section $section): string
    {
        do {
            $code = strtolower(Str::random(10));
        } while (Section::query()->where('join_code', $code)->exists());
        $section->update(['join_code' => $code]);

        return $code;
    }

    /**
     * Puts every unplaced student on a team. Teams that have people but an empty seat are filled first, then teams
     * of five are made (empty teams first, then Team A, Team B, ... as needed). What is left over joins full teams as
     * a sixth member on the shared seat when it is one or two students, and makes a team of three or four otherwise.
     *
     * @return int how many students were placed
     */
    public function formTeams(Section $section): int
    {
        return DB::transaction(function () use ($section): int {
            $placedIds = TeamMember::query()->whereIn('team_id', $section->teams()->select('id'))->pluck('user_id')->all();
            $waiting = $section->enrolments()->where('status', Enrolment::ACTIVE)->whereNotIn('user_id', $placedIds)->pluck('user_id')->shuffle()->all();
            $placed = 0;
            $seats = array_keys(TeamMember::SEATS);
            $teams = $section->teams()->withCount('members')->orderBy('id')->get();
            // Empty seats on teams that already have people.
            foreach ($teams->filter(fn (Team $t) => $t->members_count > 0) as $team) {
                foreach ($this->emptySeats($team) as $seat) {
                    if ($waiting === []) {
                        return $placed;
                    }
                    TeamMember::query()->create(['team_id' => $team->id, 'user_id' => array_shift($waiting), 'seat' => $seat]);
                    $placed++;
                }
            }
            // Teams of five, using empty teams before making new ones.
            $empty = $teams->filter(fn (Team $t) => $t->members_count === 0)->values()->all();
            while (count($waiting) >= count($seats)) {
                $team = array_shift($empty) ?? $this->newTeam($section);
                foreach ($seats as $seat) {
                    TeamMember::query()->create(['team_id' => $team->id, 'user_id' => array_shift($waiting), 'seat' => $seat]);
                    $placed++;
                }
            }
            if ($waiting === []) {
                return $placed;
            }
            // The remainder: sixth members on full teams when there are one or two, otherwise a smaller team.
            $full = $section->teams()->withCount('members')->orderBy('id')->get()->filter(fn (Team $t) => $t->members_count === count($seats))->values();
            if (count($waiting) <= 2 && $full->count() >= count($waiting)) {
                foreach ($waiting as $i => $userId) {
                    TeamMember::query()->create(['team_id' => $full[$i]->id, 'user_id' => $userId, 'seat' => self::SHARED_SEAT]);
                    $placed++;
                }
            } else {
                $team = array_shift($empty) ?? $this->newTeam($section);
                foreach ($waiting as $i => $userId) {
                    TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $userId, 'seat' => $seats[$i]]);
                    $placed++;
                }
            }

            return $placed;
        });
    }

    /** Adds an empty team with the next free letter. */
    public function newTeam(Section $section): Team
    {
        $taken = $section->teams()->pluck('name')->all();
        for ($i = 0; ; $i++) {
            $name = 'Team '.($i < 26 ? chr(ord('A') + $i) : chr(ord('A') + intdiv($i, 26) - 1).chr(ord('A') + $i % 26));
            if (! in_array($name, $taken, true)) {
                return Team::query()->create(['section_id' => $section->id, 'name' => $name]);
            }
        }
    }

    /** Moves a student to a team and seat, or off every team when $team is null. */
    public function move(Section $section, User $user, ?Team $team, ?string $seat): void
    {
        $this->assertEnrolled($section, $user);
        $current = $this->memberIn($section, $user);
        if ($team === null) {
            $current?->delete();

            return;
        }
        if ($team->section_id !== $section->id) {
            throw new RuntimeException('That team is in another class.');
        }
        // Without a seat given: a free seat on the team, else the one they held, else the shared seat.
        $seat ??= $this->emptySeats($team)[0] ?? ($current === null ? self::SHARED_SEAT : $current->seat);
        if (! array_key_exists($seat, TeamMember::SEATS)) {
            throw new RuntimeException('Pick one of the five seats.');
        }
        if ($current !== null && $current->team_id === $team->id) {
            $current->update(['seat' => $seat]);

            return;
        }
        $current?->delete();
        TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $user->id, 'seat' => $seat]);
    }

    public function block(Section $section, User $user, bool $blocked): void
    {
        $this->assertEnrolled($section, $user)->update(['status' => $blocked ? Enrolment::BLOCKED : Enrolment::ACTIVE]);
    }

    /** Takes a student off the roster and their team. Their login and anything their team did stay. */
    public function remove(Section $section, User $user): void
    {
        $this->assertEnrolled($section, $user)->delete();
        $this->memberIn($section, $user)?->delete();
    }

    /** A new one-time password for a student, returned once. */
    public function resetPassword(Section $section, User $user): string
    {
        $this->assertEnrolled($section, $user);
        $password = Str::password(12, symbols: false);
        $user->forceFill(['password' => Hash::make($password)])->save();

        return $password;
    }

    /** Deletes a team that has no members and has never played. */
    public function deleteTeam(Section $section, Team $team): void
    {
        if ($team->section_id !== $section->id) {
            throw new RuntimeException('That team is in another class.');
        }
        if ($team->members()->exists() || $team->teamQuarters()->exists()) {
            throw new RuntimeException('Move its students off first. A team that has played stays.');
        }
        $team->delete();
    }

    /** The student's enrolment in this class, when they have one and are not blocked, otherwise why they can't play. */
    public static function reasonCannotPlay(Section $section, User $user): ?string
    {
        $enrolment = Enrolment::query()->where('section_id', $section->id)->where('user_id', $user->id)->first();
        if ($enrolment !== null && $enrolment->status === Enrolment::BLOCKED) {
            return 'Your instructor has paused your access for now. Ask them about it.';
        }

        return null;
    }

    private function assertEnrolled(Section $section, User $user): Enrolment
    {
        $enrolment = Enrolment::query()->where('section_id', $section->id)->where('user_id', $user->id)->first();
        if ($enrolment === null) {
            throw new RuntimeException('That student is not in this class.');
        }

        return $enrolment;
    }

    private function memberIn(Section $section, User $user): ?TeamMember
    {
        return TeamMember::query()->where('user_id', $user->id)->whereIn('team_id', $section->teams()->select('id'))->first();
    }

    /** @return list<string> */
    private function emptySeats(Team $team): array
    {
        $taken = $team->members()->pluck('seat')->all();

        return array_values(array_filter(array_keys(TeamMember::SEATS), fn (string $s) => ! in_array($s, $taken, true)));
    }

    private function freeSeat(Team $team): string
    {
        return $this->emptySeats($team)[0] ?? self::SHARED_SEAT;
    }
}
