<?php

namespace App\Http\Controllers\Halden;

use App\Halden\Admin\Roster;
use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * The join link an instructor hands out: a student makes their login here and lands in the class, unplaced until
 * the instructor forms teams. Someone already signed in as a student is enrolled straight away.
 */
class JoinController extends Controller
{
    public function show(Request $request, string $code, Roster $roster): Response|RedirectResponse
    {
        $section = $this->section($code);
        /** @var User|null $user */
        $user = $request->user();
        if ($user !== null) {
            try {
                $roster->joinExisting($section, $user);
            } catch (RuntimeException $e) {
                return Inertia::render('auth/Join', ['code' => $code, 'className' => $section->name, 'course' => $section->course_name, 'full' => true, 'problem' => $e->getMessage()]);
            }

            return redirect()->route('play.home');
        }

        return Inertia::render('auth/Join', [
            'code' => $code, 'className' => $section->name, 'course' => $section->course_name, 'full' => ! $section->hasRoom(), 'problem' => null,
        ]);
    }

    public function store(Request $request, string $code, Roster $roster): RedirectResponse
    {
        $section = $this->section($code);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);
        if (User::query()->where('email', strtolower($data['email']))->exists()) {
            throw ValidationException::withMessages(['email' => 'That email already has a login. Log in first, then open this link again.']);
        }
        try {
            $user = $roster->join($section, trim($data['name']), $data['email'], $data['password']);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        }
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('opening');
    }

    private function section(string $code): Section
    {
        $section = Section::query()->where('join_code', $code)->first();
        abort_if($section === null, 404, 'This join link is no longer in use. Ask your instructor for a new one.');

        return $section;
    }
}
