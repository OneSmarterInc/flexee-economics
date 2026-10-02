<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ulid', 'tenant_id', 'board_defense_assessment_id', 'feedback_body', 'is_published', 'published_by_user_id', 'published_at'])]
class BoardDefenseAssessmentFeedback extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    protected $table = 'board_defense_assessment_feedback';

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BoardDefenseAssessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(BoardDefenseAssessment::class, 'board_defense_assessment_id');
    }
}
