<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One class running Halden: a faculty member, its teams and its quarters.
 */
#[Fillable(['name', 'course_name', 'weeks', 'faculty_user_id'])]
class Section extends Model
{
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
        return $this->quarters()->whereIn('status', [Quarter::OPEN, Quarter::CLOSED])->orderBy('number')->first()
            ?? $this->quarters()->where('status', Quarter::PUBLISHED)->orderByDesc('number')->first()
            ?? $this->quarters()->orderBy('number')->first();
    }
}
