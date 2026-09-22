<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'decision_submission_id', 'economic_engine', 'engine_version', 'input_snapshot', 'output_snapshot', 'transfer_price', 'integrated_margin', 'upstream_margin', 'refining_margin', 'upstream_vs_target', 'refining_vs_target', 'geneva_gap', 'geneva_capture_per_bbl', 'geneva_max_volume_bbl_day', 'resolved_by_user_id', 'resolved_by_process', 'resolved_at'])]
class EconomicResolution extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (EconomicResolution $resolution): void {
            $runtimeWeek = SectionSimulationWeek::query()->findOrFail($resolution->section_simulation_week_id);
            $teamSimulation = TeamSimulation::query()->findOrFail($resolution->team_simulation_id);
            $submission = DecisionSubmission::query()->findOrFail($resolution->decision_submission_id);

            if ($runtimeWeek->tenant_id !== $resolution->tenant_id || $teamSimulation->tenant_id !== $resolution->tenant_id || $submission->tenant_id !== $resolution->tenant_id) {
                throw new InvalidArgumentException('Economic resolution tenant must match runtime week, team simulation, and submission.');
            }

            if ($runtimeWeek->section_simulation_id !== $resolution->section_simulation_id || $teamSimulation->section_simulation_id !== $resolution->section_simulation_id || $submission->section_simulation_id !== $resolution->section_simulation_id) {
                throw new InvalidArgumentException('Economic resolution must belong to one section simulation.');
            }

            if ($teamSimulation->team_id !== $resolution->team_id || $submission->team_simulation_id !== $teamSimulation->id || $submission->section_simulation_week_id !== $runtimeWeek->id) {
                throw new InvalidArgumentException('Economic resolution context is inconsistent.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'input_snapshot' => 'array',
            'output_snapshot' => 'array',
            'transfer_price' => 'decimal:3',
            'integrated_margin' => 'decimal:3',
            'upstream_margin' => 'decimal:3',
            'refining_margin' => 'decimal:3',
            'upstream_vs_target' => 'decimal:3',
            'refining_vs_target' => 'decimal:3',
            'geneva_gap' => 'decimal:3',
            'geneva_capture_per_bbl' => 'decimal:3',
            'geneva_max_volume_bbl_day' => 'decimal:3',
            'resolved_at' => 'datetime',
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
     * @return BelongsTo<DecisionSubmission, $this>
     */
    public function decisionSubmission(): BelongsTo
    {
        return $this->belongsTo(DecisionSubmission::class);
    }
}
