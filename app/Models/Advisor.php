<?php

namespace App\Models;

use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ulid', 'key', 'name', 'title', 'perspective', 'default_guidance', 'content_version', 'sort_order', 'is_active', 'metadata'])]
class Advisor extends Model
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
     * @return HasMany<AdvisorConsultationSession, $this>
     */
    public function consultationSessions(): HasMany
    {
        return $this->hasMany(AdvisorConsultationSession::class);
    }
}
