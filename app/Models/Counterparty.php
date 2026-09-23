<?php

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ulid', 'key', 'name', 'description', 'sort_order', 'is_active', 'metadata'])]
class Counterparty extends Model
{
    use HasUlidRouteKey;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<StandingState, $this>
     */
    public function standingStates(): HasMany
    {
        return $this->hasMany(StandingState::class);
    }
}
