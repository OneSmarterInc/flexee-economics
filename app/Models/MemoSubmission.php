<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\MemoSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'memo_definition_id', 'status', 'body', 'word_count', 'character_count', 'lock_version', 'updated_by_user_id', 'submitted_by_user_id', 'draft_saved_at', 'submitted_at'])]
class MemoSubmission extends Model
{
    /** @use HasFactory<MemoSubmissionFactory> */
    use BelongsToTenant, HasFactory, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (MemoSubmission $submission): void {
            $runtimeWeek = SectionSimulationWeek::query()->findOrFail($submission->section_simulation_week_id);
            $teamSimulation = TeamSimulation::query()->findOrFail($submission->team_simulation_id);
            $definition = MemoDefinition::query()->findOrFail($submission->memo_definition_id);

            if ($runtimeWeek->tenant_id !== $submission->tenant_id || $teamSimulation->tenant_id !== $submission->tenant_id) {
                throw new InvalidArgumentException('Memo submission tenant must match runtime week and team simulation.');
            }

            if ($runtimeWeek->section_simulation_id !== $submission->section_simulation_id || $teamSimulation->section_simulation_id !== $submission->section_simulation_id) {
                throw new InvalidArgumentException('Memo submission must belong to one section simulation.');
            }

            if ($teamSimulation->team_id !== $submission->team_id || $definition->simulation_week_id !== $runtimeWeek->simulation_week_id) {
                throw new InvalidArgumentException('Memo submission context is inconsistent.');
            }
        });

        static::updating(function (MemoSubmission $submission): void {
            $materialFields = ['status', 'body', 'word_count', 'character_count', 'updated_by_user_id', 'submitted_by_user_id', 'draft_saved_at', 'submitted_at'];

            if ($submission->getRawOriginal('status') === SubmissionStatus::Submitted->value && $submission->isDirty($materialFields)) {
                throw new InvalidArgumentException('Submitted memo submissions cannot be modified.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'draft_saved_at' => 'datetime',
            'submitted_at' => 'datetime',
            'status' => SubmissionStatus::class,
        ];
    }

    public function statusEnum(): SubmissionStatus
    {
        $status = $this->getAttribute('status');

        if ($status instanceof SubmissionStatus) {
            return $status;
        }

        return SubmissionStatus::from((string) $status);
    }

    public function statusValue(): string
    {
        return $this->statusEnum()->value;
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
     * @return BelongsTo<MemoDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(MemoDefinition::class, 'memo_definition_id');
    }

    /**
     * @return HasMany<MemoSubmissionRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(MemoSubmissionRevision::class);
    }
}
