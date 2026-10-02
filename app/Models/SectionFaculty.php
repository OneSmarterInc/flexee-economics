<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['tenant_id', 'section_id', 'user_id', 'role'])]
class SectionFaculty extends Model
{
    use BelongsToTenant;

    protected $table = 'section_faculty';

    protected static function booted(): void
    {
        static::saving(function (SectionFaculty $assignment): void {
            $section = Section::query()->findOrFail($assignment->section_id);
            $user = User::query()->findOrFail($assignment->user_id);

            if ($section->tenant_id !== $assignment->tenant_id || $user->tenant_id !== $assignment->tenant_id) {
                throw new InvalidArgumentException('Faculty assignment tenant does not match section and user.');
            }

            if (! $user->isFaculty() && ! $user->isAdministrator()) {
                throw new InvalidArgumentException('Only faculty or administrators can be assigned as section faculty.');
            }
        });
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
