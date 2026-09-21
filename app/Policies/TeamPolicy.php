<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function view(User $user, Team $team): bool
    {
        if ($user->tenant_id !== $team->tenant_id) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->isFaculty()) {
            return $user->facultySections()->whereKey($team->section_id)->exists();
        }

        return $user->teams()->whereKey($team->id)->exists();
    }

    public function update(User $user, Team $team): bool
    {
        if ($user->tenant_id !== $team->tenant_id) {
            return false;
        }

        return $user->isAdministrator()
            || ($user->isFaculty() && $user->facultySections()->whereKey($team->section_id)->exists());
    }
}
