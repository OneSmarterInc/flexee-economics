<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ulid', 'tenant_id', 'course_id', 'name', 'starts_at', 'ends_at', 'status'])]
class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
    use BelongsToTenant, HasFactory, HasUlidRouteKey;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function faculty(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'section_faculty')
            ->withPivot(['tenant_id', 'role'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * @return HasMany<SectionSimulation, $this>
     */
    public function sectionSimulations(): HasMany
    {
        return $this->hasMany(SectionSimulation::class);
    }
}
