<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ulid', 'tenant_id', 'board_defense_assessment_id', 'dimension_key', 'label', 'description', 'faculty_evaluation', 'comments'])]
class BoardDefenseAssessmentDimension extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    /**
     * @return BelongsTo<BoardDefenseAssessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(BoardDefenseAssessment::class, 'board_defense_assessment_id');
    }
}
