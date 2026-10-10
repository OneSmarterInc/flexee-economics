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
        $section = $request->query('section') ? (clone $query)->whereKey((int) $request->query('section'))->first() : $query->first();
        if ($section === null && $user->isAdmin()) {
            abort(redirect()->route('admin.index'));
        }
        abort_if($section === null, 404, 'You have no class yet. Ask your admin to set one up.');

        return $section;
    }
}
