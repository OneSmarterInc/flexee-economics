<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\DecisionSubmissionRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ulid', 'tenant_id', 'decision_submission_id', 'decision_form_definition_id', 'revision_number', 'status', 'answers', 'actor_user_id', 'submitted_at'])]
class DecisionSubmissionRevision extends Model
{
    /** @use HasFactory<DecisionSubmissionRevisionFactory> */
    use BelongsToTenant, HasFactory, HasUlidRouteKey;

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'submitted_at' => 'datetime',
            'status' => SubmissionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<DecisionSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(DecisionSubmission::class, 'decision_submission_id');
    }
}
