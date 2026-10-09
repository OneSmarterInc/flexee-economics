<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_quarter_id
 * @property string $kind
 * @property string|null $text
 * @property array<string, mixed>|null $data
 * @property string $status
 * @property string|null $dropped_reason
 * @property int $input_tokens
 * @property int $output_tokens
 * @property string|null $model
 * @property int|null $requested_by
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['team_quarter_id', 'kind', 'text', 'data', 'status', 'dropped_reason', 'input_tokens', 'output_tokens', 'model', 'requested_by'])]
class FacultyDraft extends Model
{
    public const KINDS = ['feedback', 'writing', 'mismatch'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    /** @return BelongsTo<TeamQuarter, $this> */
    public function teamQuarter(): BelongsTo
    {
        return $this->belongsTo(TeamQuarter::class);
    }
}
