<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One team's conversation with one advisor in one quarter. Every teammate reads the same thread.
 *
 * @property int $id
 * @property int $team_id
 * @property int $quarter_id
 * @property string $advisor
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['team_id', 'quarter_id', 'advisor'])]
class AdvisorThread extends Model
{
    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Quarter, $this> */
    public function quarter(): BelongsTo
    {
        return $this->belongsTo(Quarter::class);
    }

    /** @return HasMany<AdvisorMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AdvisorMessage::class)->orderBy('id');
    }
}
