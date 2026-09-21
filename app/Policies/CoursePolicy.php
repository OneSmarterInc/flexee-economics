<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function view(User $user, Course $course): bool
    {
        if ($user->tenant_id !== $course->tenant_id) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->isFaculty()) {
            return $user->facultySections()
                ->whereHas('course', fn ($query) => $query->whereKey($course->id))
                ->exists();
        }

        return $user->enrollments()
            ->whereHas('section', fn ($query) => $query->where('course_id', $course->id))
            ->exists();
    }

    public function update(User $user, Course $course): bool
    {
        return $user->tenant_id === $course->tenant_id && $user->isAdministrator();
    }
}
