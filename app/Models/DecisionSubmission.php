<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\DecisionSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'decision_form_definition_id', 'status', 'answers', 'lock_version', 'updated_by_user_id', 'submitted_by_user_id', 'draft_saved_at', 'submitted_at'])]
class DecisionSubmission extends Model
{
    /** @use HasFactory<DecisionSubmissionFactory> */
    use BelongsToTenant, HasFactory, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (DecisionSubmission $submission): void {
            $runtimeWeek = SectionSimulationWeek::query()->findOrFail($submission->section_simulation_week_id);
            $teamSimulation = TeamSimulation::query()->findOrFail($submission->team_simulation_id);
            $definition = DecisionFormDefinition::query()->findOrFail($submission->decision_form_definition_id);

            if ($runtimeWeek->tenant_id !== $submission->tenant_id || $teamSimulation->tenant_id !== $submission->tenant_id) {
                throw new InvalidArgumentException('Decision submission tenant must match runtime week and team simulation.');
            }

            if ($runtimeWeek->section_simulation_id !== $submission->section_simulation_id || $teamSimulation->section_simulation_id !== $submission->section_simulation_id) {
                throw new InvalidArgumentException('Decision submission must belong to one section simulation.');
            }

            if ($teamSimulation->team_id !== $submission->team_id || $definition->simulation_week_id !== $runtimeWeek->simulation_week_id) {
                throw new InvalidArgumentException('Decision submission context is inconsistent.');
            }
        });

        static::updating(function (DecisionSubmission $submission): void {
            $materialFields = ['status', 'answers', 'updated_by_user_id', 'submitted_by_user_id', 'draft_saved_at', 'submitted_at'];

            if ($submission->getRawOriginal('status') === SubmissionStatus::Submitted->value && $submission->isDirty($materialFields)) {
                throw new InvalidArgumentException('Submitted decision submissions cannot be modified.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'answers' => 'array',
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
     * @return BelongsTo<DecisionFormDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(DecisionFormDefinition::class, 'decision_form_definition_id');
    }

    /**
     * @return HasMany<DecisionSubmissionRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(DecisionSubmissionRevision::class);
    }

    /**
     * @return HasOne<EconomicResolution, $this>
     */
    public function economicResolution(): HasOne
    {
        return $this->hasOne(EconomicResolution::class);
    }

    /**
     * @return HasOne<Week5EconomicEvaluation, $this>
     */
    public function week5EconomicEvaluation(): HasOne
    {
        return $this->hasOne(Week5EconomicEvaluation::class);
    }

    /**
     * @return HasOne<Week8EconomicEvaluation, $this>
     */
    public function week8EconomicEvaluation(): HasOne
    {
        return $this->hasOne(Week8EconomicEvaluation::class);
    }

    /**
     * @return HasOne<Week9EconomicEvaluation, $this>
     */
    public function week9EconomicEvaluation(): HasOne
    {
        return $this->hasOne(Week9EconomicEvaluation::class);
    }

    /**
     * @return HasOne<Week10EconomicEvaluation, $this>
     */
    public function week10EconomicEvaluation(): HasOne
    {
        return $this->hasOne(Week10EconomicEvaluation::class);
    }
}
