<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'decision_submission_id', 'engine_identifier', 'engine_version', 'package_identifier', 'package_version', 'status', 'ev_hold_musd', 'ev_match_musd', 'breakeven_build_probability', 'capacity_decision', 'cluster_results', 'window3_snapshot', 'worked_example_snapshot', 'decision_snapshot', 'input_snapshot', 'output_snapshot', 'unavailable_reason', 'evaluated_by_user_id', 'evaluated_by_process', 'evaluated_at'])]
class Week7EconomicEvaluation extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    public const STATUS_UNAVAILABLE_REFERENCE_PACKAGE = 'unavailable_reference_package';

    public const STATUS_CALCULATED = 'calculated';

    protected static function booted(): void
    {
        static::saving(function (Week7EconomicEvaluation $evaluation): void {
            $submission = DecisionSubmission::query()->findOrFail($evaluation->decision_submission_id);
            $runtimeWeek = SectionSimulationWeek::query()->findOrFail($evaluation->section_simulation_week_id);
            $teamSimulation = TeamSimulation::query()->findOrFail($evaluation->team_simulation_id);

            if ($submission->tenant_id !== $evaluation->tenant_id || $runtimeWeek->tenant_id !== $evaluation->tenant_id || $teamSimulation->tenant_id !== $evaluation->tenant_id) {
                throw new InvalidArgumentException('Week 7 economic evaluation tenant context is inconsistent.');
            }

            if ($submission->section_simulation_id !== $evaluation->section_simulation_id || $runtimeWeek->section_simulation_id !== $evaluation->section_simulation_id || $teamSimulation->section_simulation_id !== $evaluation->section_simulation_id) {
                throw new InvalidArgumentException('Week 7 economic evaluation section simulation context is inconsistent.');
            }

            if ($submission->section_simulation_week_id !== $evaluation->section_simulation_week_id || $submission->team_simulation_id !== $evaluation->team_simulation_id || $teamSimulation->team_id !== $evaluation->team_id) {
                throw new InvalidArgumentException('Week 7 economic evaluation decision context is inconsistent.');
            }
        });

        static::updating(fn () => throw new InvalidArgumentException('Week 7 economic evaluations are immutable once created.'));
        static::deleting(fn () => throw new InvalidArgumentException('Week 7 economic evaluations are immutable once created.'));
    }

    protected function casts(): array
    {
        return [
            'ev_hold_musd' => 'decimal:6',
            'ev_match_musd' => 'decimal:6',
            'breakeven_build_probability' => 'decimal:6',
            'cluster_results' => 'array',
            'window3_snapshot' => 'array',
            'worked_example_snapshot' => 'array',
            'decision_snapshot' => 'array',
            'input_snapshot' => 'array',
            'output_snapshot' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DecisionSubmission, $this>
     */
    public function decisionSubmission(): BelongsTo
    {
        return $this->belongsTo(DecisionSubmission::class);
    }
}
