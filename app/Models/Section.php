<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One class running Halden: a faculty member, its teams and its quarters.
 */
#[Fillable(['name', 'course_name', 'weeks', 'faculty_user_id', 'seats', 'advisors_enabled'])]
class Section extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['advisors_enabled' => 'boolean'];
    }

    /** Whether any quarter has opened: after that the class's shape (weeks, schedule) is fixed. */
    public function hasStarted(): bool
    {
        return $this->quarters()->where('status', '!=', Quarter::UPCOMING)->exists();
    }

    /** @return BelongsTo<User, $this> */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'faculty_user_id');
    }

    /** @return HasMany<Team, $this> */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /** @return HasMany<Quarter, $this> */
    public function quarters(): HasMany
    {
        return $this->hasMany(Quarter::class)->orderBy('number');
    }

    public function currentQuarter(): ?Quarter
    {
        // quarters() already orders by number, so the "latest published" lookup must replace that order, not add to it.
        $q = $this->quarters()->whereIn('status', [Quarter::OPEN, Quarter::CLOSED])->first()
            ?? $this->quarters()->where('status', Quarter::PUBLISHED)->reorder('number', 'desc')->first()
            ?? $this->quarters()->first();

        // In a 7-week course the team works on the first quarter of the week's pair.
        return $q?->weekStart();
    }
}
