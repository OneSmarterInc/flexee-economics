<?php

namespace App\Http\Controllers\Halden;

use App\Halden\Admin\ClassAccess;
use App\Halden\Admin\Roster;
use App\Halden\Mail\Outgoing;
use App\Http\Controllers\Controller;
use App\Models\Enrolment;
use App\Models\Section;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * The instructor's roster: the class's students and teams, the ways to add students, and the one-time passwords
 * shown once. Every action redirects back to the roster with `?section=` kept.
 */
class RosterController extends Controller
{
    public function show(Request $request): Response
    {
        $section = ClassAccess::pick($request);
        $members = TeamMember::query()->whereIn('team_id', $section->teams()->select('id'))->get()->keyBy('user_id');
        $students = $section->enrolments()->with('user')->get()
            ->sortBy(fn (Enrolment $e) => strtolower($e->user->name))
            ->map(fn (Enrolment $e) => [
                'id' => $e->user->id, 'name' => $e->user->name, 'email' => $e->user->email, 'blocked' => $e->status === Enrolment::BLOCKED,
                'teamId' => $members[$e->user->id]->team_id ?? null, 'seat' => $members[$e->user->id]->seat ?? null,
                'seenOpening' => $e->user->opening_seen_at !== null,
            ])->values();

        /** @var User $viewer */
        $viewer = $request->user();

        return Inertia::render('halden/FacultyRoster', [
            'classes' => ClassAccess::choices($viewer),
            'mailEnabled' => Outgoing::enabled(),
            'section' => [
                'id' => $section->id, 'name' => $section->name, 'course' => $section->course_name, 'seats' => $section->seats,
                'joinUrl' => $section->join_code === null ? null : route('join.show', $section->join_code),
                'started' => $section->hasStarted(),
            ],
            'students' => $students,
            'teams' => $section->teams()->orderBy('name')->get()->map(fn (Team $t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'seats' => TeamMember::SEATS,
            'added' => $request->session()->get('added'),
            'rejected' => $request->session()->get('rejected', []),
            'newPassword' => $request->session()->get('new_password'),
            'done' => $request->session()->get('done'),
        ]);
    }

    /** Adds students from a pasted list or an uploaded CSV, optionally straight onto one team. */
    public function add(Request $request, Roster $roster): RedirectResponse
    {
        $section = ClassAccess::pick($request);
        $data = $request->validate([
            'list' => ['nullable', 'string', 'max:200000'],
            'file' => ['nullable', 'file', 'max:2048', 'mimes:csv,txt'],
            'team' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('section_id', $section->id)],
            'email' => ['sometimes', 'boolean'],
        ]);
        $text = (string) ($data['list'] ?? '');
        if ($request->hasFile('file')) {
            $text .= "\n".(string) file_get_contents((string) $request->file('file')?->getRealPath());
        }
        $parsed = Roster::parse($text);
        if ($parsed['people'] === []) {
            throw ValidationException::withMessages(['list' => 'No email addresses found. One student per line: an email, or "Name <email>", or "email, name".']);
        }
        $team = empty($data['team']) ? null : Team::query()->find((int) $data['team']);
        $added = $this->run(fn () => $roster->add($section, $parsed['people'], $team, $request->boolean('email')));

        return $this->back($section)->with('added', $added)->with('rejected', $parsed['rejected']);
    }

    public function joinLink(Request $request, Roster $roster): RedirectResponse
    {
        $section = ClassAccess::pick($request);
        if ($request->boolean('off')) {
            $section->update(['join_code' => null]);
        } else {
            $roster->newJoinCode($section);
        }

        return $this->back($section)->with('done', 'join-link');
    }

    public function formTeams(Request $request, Roster $roster): RedirectResponse
    {
        $section = ClassAccess::pick($request);
        $placed = $this->run(fn () => $roster->formTeams($section));

        return $this->back($section)->with('done', $placed === 0 ? 'nobody-to-place' : "placed:$placed");
    }

    public function newTeam(Request $request, Roster $roster): RedirectResponse
    {
        $section = ClassAccess::pick($request);
        $roster->newTeam($section);

        return $this->back($section)->with('done', 'team-added');
    }

    public function renameTeam(Request $request, Team $team): RedirectResponse
    {
        $section = $this->own($request, $team);
        $data = $request->validate(['name' => ['required', 'string', 'max:60', Rule::unique('teams', 'name')->where('section_id', $section->id)->ignore($team->id)]],
            ['name.unique' => 'There is already a team with that name.']);
        $team->update(['name' => trim($data['name'])]);

        return $this->back($section)->with('done', 'team-renamed');
    }

    public function deleteTeam(Request $request, Team $team, Roster $roster): RedirectResponse
    {
        $section = $this->own($request, $team);
        $this->run(fn () => $roster->deleteTeam($section, $team));

        return $this->back($section)->with('done', 'team-deleted');
    }

    /** Moves a student to a team and seat (or off every team), blocks or unblocks them, removes them, or resets their password. */
    public function student(Request $request, User $user, string $action, Roster $roster): RedirectResponse
    {
        $section = ClassAccess::pick($request);
        $done = $action;
        $with = [];
        switch ($action) {
            case 'move':
                $data = $request->validate([
                    'team' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('section_id', $section->id)],
                    'seat' => ['nullable', 'string', Rule::in(array_keys(TeamMember::SEATS))],
                ]);
                $team = empty($data['team']) ? null : Team::query()->find((int) $data['team']);
                $this->run(fn () => $roster->move($section, $user, $team, $data['seat'] ?? null));
                break;
            case 'block':
            case 'unblock':
                $this->run(fn () => $roster->block($section, $user, $action === 'block'));
                break;
            case 'remove':
                $this->run(fn () => $roster->remove($section, $user));
                break;
            case 'reset-password':
                $password = $this->run(fn () => $roster->resetPassword($section, $user));
                $with['new_password'] = ['email' => $user->email, 'name' => $user->name, 'password' => $password];
                break;
            default:
                abort(404);
        }

        return $this->back($section)->with('done', $done)->with($with);
    }

    private function own(Request $request, Team $team): Section
    {
        $section = ClassAccess::pick($request);
        abort_unless($team->section_id === $section->id, 404);

        return $section;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    private function run(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['roster' => $e->getMessage()]);
        }
    }

    private function back(Section $section): RedirectResponse
    {
        return redirect()->route('faculty.roster', ['section' => $section->id]);
    }
}
