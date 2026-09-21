<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\User;

class SectionPolicy
{
    public function view(User $user, Section $section): bool
    {
        if ($user->tenant_id !== $section->tenant_id) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->isFaculty()) {
            return $user->facultySections()->whereKey($section->id)->exists();
        }

        return $user->enrollments()
            ->where('section_id', $section->id)
            ->where('status', 'active')
            ->exists();
    }

    public function update(User $user, Section $section): bool
    {
        if ($user->tenant_id !== $section->tenant_id) {
            return false;
        }

        return $user->isAdministrator()
            || ($user->isFaculty() && $user->facultySections()->whereKey($section->id)->exists());
    }
}
