<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ulid', 'tenant_id', 'section_id', 'name', 'slug'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use BelongsToTenant, HasFactory, HasUlidRouteKey;

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot(['tenant_id', 'seat_id'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<TeamSimulation, $this>
     */
    public function teamSimulations(): HasMany
    {
        return $this->hasMany(TeamSimulation::class);
    }
}
