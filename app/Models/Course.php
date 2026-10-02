<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ulid', 'tenant_id', 'institution_id', 'code', 'name', 'term'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use BelongsToTenant, HasFactory, HasUlidRouteKey;

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
