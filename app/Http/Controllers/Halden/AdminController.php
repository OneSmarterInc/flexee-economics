<?php

namespace App\Http\Controllers\Halden;

use App\Halden\Admin\ClassAccess;
use App\Halden\Admin\ClassFactory;
use App\Http\Controllers\Controller;
use App\Models\AdvisorMessage;
use App\Models\FacultyDraft;
use App\Models\Quarter;
use App\Models\Section;
use App\Models\TeamMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin's screens: every class and its instructor, new classes and instructors, and a class's settings.
 * Instructors run their classes from the faculty board; an admin can open any class's board too.
 */
class AdminController extends Controller
{
    public function index(Request $request): Response
    {
        $this->admin($request);
        $classes = [];
        foreach (Section::query()->with(['faculty', 'coInstructors'])->orderByDesc('id')->get() as $section) {
            $current = $section->currentQuarter();
            $classes[] = [
                'id' => $section->id, 'name' => $section->name, 'course' => $section->course_name, 'weeks' => (int) $section->weeks,
                'instructor' => $section->faculty->name, 'instructorEmail' => $section->faculty->email,
                'coInstructors' => $section->coInstructors->map(fn (User $u) => $u->name)->values()->all(),
                'teams' => $section->teams()->count(), 'students' => TeamMember::query()->whereIn('team_id', $section->teams()->select('id'))->count(),
                'seats' => $section->seats, 'advisorsEnabled' => (bool) $section->advisors_enabled,
                'where' => $current === null ? 'No quarters' : ($section->weeks < Quarter::COMPANY_QUARTERS ? 'Week '.$current->week() : 'Quarter '.$current->number).' · '.$this->statusText($current->status),
                'started' => $section->hasStarted(),
            ];
        }
        $instructors = User::query()->whereIn('role', [User::ROLE_FACULTY, User::ROLE_ADMIN])->orderBy('name')->get()
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role,
                'classes' => ClassAccess::query($u)->pluck('name')->all()])->values();

        return Inertia::render('halden/AdminClasses', [
            'classes' => $classes,
            'instructors' => $instructors,
            'defaultDeadline' => ClassFactory::defaultFirstDeadline()->setTimezone('America/New_York')->format('Y-m-d\TH:i'),
            'newPassword' => $request->session()->get('new_password'),
        ]);
    }

    public function storeClass(Request $request, ClassFactory $factory): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:sections,name'],
            'course' => ['required', 'string', 'max:120'],
            'weeks' => ['required', 'integer', Rule::in([7, 14])],
            'instructor' => ['required', 'integer', Rule::exists('users', 'id')->whereIn('role', [User::ROLE_FACULTY, User::ROLE_ADMIN])],
            'first_deadline' => ['required', 'date'],
            'teams' => ['required', 'integer', 'min:0', 'max:40'],
            'seats' => ['nullable', 'integer', 'min:1', 'max:500'],
        ], [
            'name.unique' => 'There is already a class with that name.',
            'first_deadline.required' => 'Pick the first deadline.',
        ]);
        $faculty = User::query()->findOrFail((int) $data['instructor']);
        $first = CarbonImmutable::parse((string) $data['first_deadline'], 'America/New_York')->utc();
        $section = $factory->create($data['name'], $data['course'], (int) $data['weeks'], $faculty, $first, (int) $data['teams'], isset($data['seats']) ? (int) $data['seats'] : null);

        return redirect()->route('admin.class', $section)->with('done', 'created');
    }

    public function showClass(Request $request, Section $section): Response
    {
        $this->admin($request);
        $first = $section->quarters()->first();
        $teamIds = $section->teams()->select('id');
        $tokens = AdvisorMessage::query()->whereHas('thread', fn ($q) => $q->whereIn('team_id', $teamIds))
            ->selectRaw('COALESCE(SUM(input_tokens),0) as i, COALESCE(SUM(output_tokens),0) as o, COUNT(*) as n')->first();
        $drafts = FacultyDraft::query()->whereHas('teamQuarter', fn ($q) => $q->whereIn('team_id', $teamIds))
            ->selectRaw('COALESCE(SUM(input_tokens),0) as i, COALESCE(SUM(output_tokens),0) as o, COUNT(*) as n')->first();

        return Inertia::render('halden/AdminClass', [
            'section' => [
                'id' => $section->id, 'name' => $section->name, 'course' => $section->course_name, 'weeks' => (int) $section->weeks,
                'instructor' => $section->faculty_user_id, 'seats' => $section->seats, 'advisorsEnabled' => (bool) $section->advisors_enabled,
                'requiresPayment' => (bool) $section->requires_payment,
                'coInstructors' => $section->coInstructors()->orderBy('name')->get()->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->values(),
                'firstDeadline' => $first?->deadline_at?->setTimezone('America/New_York')->format('Y-m-d\TH:i'),
                'started' => $section->hasStarted(),
                'teams' => $section->teams()->withCount('members')->orderBy('name')->get()->map(fn ($t) => ['name' => $t->name, 'members' => $t->members_count])->values(),
                'quarters' => $section->quarters()->get()->map(fn (Quarter $q) => ['number' => $q->number, 'label' => $q->label(), 'status' => $this->statusText($q->status),
                    'deadline' => $q->deadline_at?->setTimezone('America/New_York')->format('D j M Y, g:i a')])->values(),
            ],
            'usage' => [
                'advisorAnswers' => (int) ($tokens->n ?? 0), 'advisorTokens' => (int) ($tokens->i ?? 0) + (int) ($tokens->o ?? 0),
                'drafts' => (int) ($drafts->n ?? 0), 'draftTokens' => (int) ($drafts->i ?? 0) + (int) ($drafts->o ?? 0),
            ],
            'instructors' => User::query()->whereIn('role', [User::ROLE_FACULTY, User::ROLE_ADMIN])->orderBy('name')->get()
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->values(),
            'done' => $request->session()->get('done'),
        ]);
    }

    public function updateClass(Request $request, Section $section, ClassFactory $factory): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('sections', 'name')->ignore($section->id)],
            'course' => ['required', 'string', 'max:120'],
            'weeks' => ['required', 'integer', Rule::in([7, 14])],
            'instructor' => ['required', 'integer', Rule::exists('users', 'id')->whereIn('role', [User::ROLE_FACULTY, User::ROLE_ADMIN])],
            'first_deadline' => ['nullable', 'date'],
            'seats' => ['nullable', 'integer', 'min:1', 'max:500'],
            'advisors_enabled' => ['required', 'boolean'],
            'requires_payment' => ['sometimes', 'boolean'],
        ], ['name.unique' => 'There is already a class with that name.']);
        $started = $section->hasStarted();
        if ($started && (int) $data['weeks'] !== (int) $section->weeks) {
            throw ValidationException::withMessages(['weeks' => 'The class has started, so its length can\'t change now.']);
        }
        $section->update([
            'name' => $data['name'], 'course_name' => $data['course'], 'weeks' => (int) $data['weeks'], 'faculty_user_id' => (int) $data['instructor'],
            'seats' => isset($data['seats']) ? (int) $data['seats'] : null, 'advisors_enabled' => (bool) $data['advisors_enabled'],
            'requires_payment' => (bool) ($data['requires_payment'] ?? false),
        ]);
        if (! $started && ! empty($data['first_deadline'])) {
            $factory->scheduleQuarters($section->refresh(), CarbonImmutable::parse((string) $data['first_deadline'], 'America/New_York')->utc());
        }

        return back()->with('done', 'saved');
    }

    /** Adds a co-instructor to a class, or takes one off; the lead instructor stays on the class's settings. */
    public function coInstructor(Request $request, Section $section): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'instructor' => ['required', 'integer', Rule::exists('users', 'id')->whereIn('role', [User::ROLE_FACULTY, User::ROLE_ADMIN])],
            'remove' => ['sometimes', 'boolean'],
        ]);
        $id = (int) $data['instructor'];
        if ($request->boolean('remove')) {
            $section->coInstructors()->detach($id);

            return back()->with('done', 'co-removed');
        }
        if ($id === (int) $section->faculty_user_id) {
            throw ValidationException::withMessages(['co_instructor' => 'That is already the class\'s instructor.']);
        }
        $section->coInstructors()->syncWithoutDetaching([$id]);

        return back()->with('done', 'co-added');
    }

    public function destroyClass(Request $request, Section $section): RedirectResponse
    {
        $this->admin($request);
        if ($section->hasStarted()) {
            throw ValidationException::withMessages(['delete' => 'The class has started, so it can\'t be deleted. Its results are the record.']);
        }
        $section->delete();

        return redirect()->route('admin.index')->with('done', 'deleted');
    }

    /** A new instructor login, with a password shown once; or a new password for an existing one. */
    public function storeInstructor(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
        ]);
        $user = User::query()->where('email', $data['email'])->first();
        if ($user !== null && $user->isStudent()) {
            throw ValidationException::withMessages(['email' => 'That email belongs to a student login.']);
        }
        $password = Str::password(20, symbols: false);
        $user ??= new User(['email' => $data['email']]);
        $user->forceFill(['name' => $data['name'], 'role' => $user->role === User::ROLE_ADMIN ? User::ROLE_ADMIN : User::ROLE_FACULTY,
            'email_verified_at' => $user->email_verified_at ?? now(), 'password' => Hash::make($password)])->save();

        return back()->with('done', 'instructor')->with('new_password', ['email' => $user->email, 'password' => $password]);
    }

    private function admin(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isAdmin(), 403);
    }

    private function statusText(string $status): string
    {
        return match ($status) {
            Quarter::OPEN => 'open', Quarter::CLOSED => 'closed, results not shown', Quarter::PUBLISHED => 'results shown', default => 'not open yet',
        };
    }
}
