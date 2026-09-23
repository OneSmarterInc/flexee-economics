<?php

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ulid', 'key', 'name', 'description', 'source_type', 'target_type', 'effect_type', 'version', 'is_active', 'metadata'])]
class ConsequenceDefinition extends Model
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
     * @return HasMany<ConsequenceLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(ConsequenceLink::class);
    }
}
