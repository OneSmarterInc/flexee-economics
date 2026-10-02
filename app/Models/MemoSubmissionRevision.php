<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\MemoSubmissionRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ulid', 'tenant_id', 'memo_submission_id', 'memo_definition_id', 'revision_number', 'status', 'body', 'word_count', 'character_count', 'actor_user_id', 'submitted_at'])]
class MemoSubmissionRevision extends Model
{
    /** @use HasFactory<MemoSubmissionRevisionFactory> */
    use BelongsToTenant, HasFactory, HasUlidRouteKey;

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'status' => SubmissionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<MemoSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(MemoSubmission::class, 'memo_submission_id');
    }
}
