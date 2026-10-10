<?php

namespace App\Halden\Admin;

use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Which classes an instructor or admin may open: an instructor's own, every class for an admin. The faculty
 * screens take `?section=` to pick one; without it the first class is shown.
 */
final class ClassAccess
{
    /** Session key holding the class opened last, so `/faculty` comes back to it. */
    public const SESSION_KEY = 'halden.section';

    /** @return Builder<Section> */
    public static function query(User $user): Builder
    {
        $query = Section::query()->orderBy('id');
        if (! $user->isAdmin()) {
            $query->where('faculty_user_id', $user->id);
        }

        return $query;
    }

    /** The class a faculty screen shows, or a 403/404 (an admin with no class is sent to the admin area). */
    public static function pick(Request $request): Section
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isFaculty() || $user->isAdmin(), 403);
        $query = self::query($user);
        // The class asked for, else the one opened last in this session, else the first.
        $wanted = (int) ($request->query('section') ?: $request->session()->get(self::SESSION_KEY, 0));
        $section = $wanted > 0 ? (clone $query)->whereKey($wanted)->first() : null;
        $section ??= $query->first();
        if ($section === null && $user->isAdmin()) {
            abort(redirect()->route('admin.index'));
        }
        abort_if($section === null, 404, 'You have no class yet. Ask your admin to set one up.');
        $request->session()->put(self::SESSION_KEY, $section->id);

        return $section;
    }

    /**
     * Every class the user may open, for the switcher in the faculty headers.
     *
     * @return list<array{id: int, name: string, course: string}>
     */
    public static function choices(User $user): array
    {
        $out = [];
        foreach (self::query($user)->get() as $s) {
            $out[] = ['id' => $s->id, 'name' => $s->name, 'course' => $s->course_name];
        }

        return $out;
    }
}
