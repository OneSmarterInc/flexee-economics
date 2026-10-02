<?php

namespace App\Policies;

use App\Models\SectionSimulation;
use App\Models\User;

class SectionSimulationPolicy
{
    public function view(User $user, SectionSimulation $sectionSimulation): bool
    {
        if ($user->tenant_id !== $sectionSimulation->tenant_id) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->isFaculty()) {
            return $user->facultySections()->whereKey($sectionSimulation->section_id)->exists();
        }

        return $user->enrollments()
            ->where('section_id', $sectionSimulation->section_id)
            ->where('status', 'active')
            ->exists();
    }

    public function update(User $user, SectionSimulation $sectionSimulation): bool
    {
        if ($user->tenant_id !== $sectionSimulation->tenant_id) {
            return false;
        }

        return $user->isAdministrator()
            || ($user->isFaculty() && $user->facultySections()->whereKey($sectionSimulation->section_id)->exists());
    }
}
