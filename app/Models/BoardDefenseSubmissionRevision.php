<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ulid', 'tenant_id', 'board_defense_submission_id', 'revision_number', 'status', 'final_synthesis_memo', 'artifact_references', 'actor_user_id', 'submitted_at'])]
class BoardDefenseSubmissionRevision extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    protected function casts(): array
    {
        return [
            'artifact_references' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BoardDefenseSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(BoardDefenseSubmission::class, 'board_defense_submission_id');
    }
}
