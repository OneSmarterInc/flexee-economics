<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Five students running one Halden.
 */
#[Fillable(['section_id', 'name', 'first_meeting', 'strategy_become', 'strategy_by'])]
class Team extends Model
{
    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return HasMany<TeamMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /** @return HasMany<TeamQuarter, $this> */
    public function teamQuarters(): HasMany
    {
        return $this->hasMany(TeamQuarter::class);
    }
}
