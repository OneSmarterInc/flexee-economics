<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'submitted_by_user_id', 'updated_by_user_id', 'status', 'final_synthesis_memo', 'artifact_references', 'lock_version', 'draft_saved_at', 'submitted_at'])]
class BoardDefenseSubmission extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    protected function casts(): array
    {
        return [
            'artifact_references' => 'array',
            'draft_saved_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
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
     * @return HasMany<BoardDefenseSubmissionRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(BoardDefenseSubmissionRevision::class);
    }

    /**
     * @return HasOne<BoardDefenseAssessment, $this>
     */
    public function assessment(): HasOne
    {
        return $this->hasOne(BoardDefenseAssessment::class);
    }
}
