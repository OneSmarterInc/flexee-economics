<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's place in a class, whether or not they are on a team yet. An instructor can pause a student's
 * access (blocked) and lift it again; removing a student deletes the enrolment and their team place, and the
 * login stays so they can be added back.
 */
#[Fillable(['section_id', 'user_id', 'status', 'paid_at'])]
class Enrolment extends Model
{
    public const ACTIVE = 'active';

    public const BLOCKED = 'blocked';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
