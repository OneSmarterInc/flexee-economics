<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One team's work and results for one quarter.
 *
 * @property int $id
 * @property int $team_id
 * @property int $quarter_id
 * @property array<string, string|float|int|null>|null $decisions
 * @property array<string, string|float|int|null>|null $effective_decisions
 * @property array<string, array{by: string, at: string}>|null $saved_pages
 * @property string|null $memo
 * @property int|null $memo_saved_by
 * @property CarbonImmutable|null $memo_saved_at
 * @property int|null $ready_by
 * @property CarbonImmutable|null $ready_at
 * @property array<string, float|string>|null $results
 * @property array<string, mixed>|null $state_after
 * @property float|null $score
 * @property int|null $rank
 */
#[Fillable(['team_id', 'quarter_id', 'decisions', 'effective_decisions', 'saved_pages', 'memo', 'memo_saved_by', 'memo_saved_at',
    'ready_by', 'ready_at', 'results', 'state_after', 'score', 'rank'])]
class TeamQuarter extends Model
{
    protected function casts(): array
    {
        return [
            'decisions' => 'array',
            'effective_decisions' => 'array',
            'saved_pages' => 'array',
            'results' => 'array',
            'state_after' => 'array',
            'memo_saved_at' => 'datetime',
            'ready_at' => 'datetime',
            'score' => 'float',
        ];
    }

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
}
