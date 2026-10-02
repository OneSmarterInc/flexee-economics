<?php

namespace App\Policies;

use App\Enums\SectionSimulationWeekStatus;
use App\Models\SectionSimulationWeek;
use App\Models\User;

class SectionSimulationWeekPolicy
{
    public function view(User $user, SectionSimulationWeek $runtimeWeek): bool
    {
        if ($user->tenant_id !== $runtimeWeek->tenant_id) {
            return false;
        }

        $sectionSimulation = $runtimeWeek->sectionSimulation;

        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->isFaculty()) {
            return $user->facultySections()->whereKey($sectionSimulation->section_id)->exists();
        }

        if (! in_array($runtimeWeek->statusValue(), [
            SectionSimulationWeekStatus::Released->value,
            SectionSimulationWeekStatus::Open->value,
            SectionSimulationWeekStatus::Closed->value,
            SectionSimulationWeekStatus::Published->value,
        ], true)) {
            return false;
        }

        return $user->enrollments()
            ->where('section_id', $sectionSimulation->section_id)
            ->where('status', 'active')
            ->exists();
    }

    public function update(User $user, SectionSimulationWeek $runtimeWeek): bool
    {
        if ($user->tenant_id !== $runtimeWeek->tenant_id) {
            return false;
        }

        $sectionId = $runtimeWeek->sectionSimulation->section_id;

        return $user->isAdministrator()
            || ($user->isFaculty() && $user->facultySections()->whereKey($sectionId)->exists());
    }
}
