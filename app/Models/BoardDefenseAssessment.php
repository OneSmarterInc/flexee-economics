<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'board_defense_submission_id', 'reviewer_user_id', 'rubric_version', 'status', 'reasoning_outcome_tier', 'faculty_private_notes', 'history_packet_snapshot', 'completed_at'])]
class BoardDefenseAssessment extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_COMPLETED = 'completed';

    public const RUBRIC_VERSION = 'week14-board-defense-v1';

    protected function casts(): array
    {
        return [
            'history_packet_snapshot' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SectionSimulationWeek, $this>
     */
    public function runtimeWeek(): BelongsTo
    {
        return $this->belongsTo(SectionSimulationWeek::class, 'section_simulation_week_id');
    }

    /**
     * @return BelongsTo<TeamSimulation, $this>
     */
    public function teamSimulation(): BelongsTo
    {
        return $this->belongsTo(TeamSimulation::class);
    }

    /**
     * @return BelongsTo<BoardDefenseSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(BoardDefenseSubmission::class, 'board_defense_submission_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    /**
     * @return HasMany<BoardDefenseAssessmentDimension, $this>
     */
    public function dimensions(): HasMany
    {
        return $this->hasMany(BoardDefenseAssessmentDimension::class);
    }

    /**
     * @return HasOne<BoardDefenseAssessmentFeedback, $this>
     */
    public function feedback(): HasOne
    {
        return $this->hasOne(BoardDefenseAssessmentFeedback::class);
    }
}
