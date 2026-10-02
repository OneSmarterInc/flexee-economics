<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'decision_submission_id', 'engine_identifier', 'engine_version', 'package_version', 'status', 'expected_wti', 'expected_upstream_impact_per_bbl', 'expected_refining_crack', 'prediction_expected_wti', 'prediction_expected_upstream_impact_per_bbl', 'prediction_expected_refining_crack', 'realized_scenario_key', 'realized_wti', 'realized_upstream_impact_per_bbl', 'realized_refining_crack', 'realized_retail_volume_percent', 'prediction_snapshot', 'realization_snapshot', 'input_snapshot', 'output_snapshot', 'unavailable_reason', 'evaluated_by_user_id', 'evaluated_by_process', 'evaluated_at'])]
class Week8EconomicEvaluation extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    public const STATUS_UNAVAILABLE_REFERENCE_PACKAGE = 'unavailable_reference_package';

    public const STATUS_CALCULATED = 'calculated';

    protected static function booted(): void
    {
        static::saving(function (Week8EconomicEvaluation $evaluation): void {
            $submission = DecisionSubmission::query()->findOrFail($evaluation->decision_submission_id);
            $runtimeWeek = SectionSimulationWeek::query()->findOrFail($evaluation->section_simulation_week_id);
            $teamSimulation = TeamSimulation::query()->findOrFail($evaluation->team_simulation_id);

            if ($submission->tenant_id !== $evaluation->tenant_id || $runtimeWeek->tenant_id !== $evaluation->tenant_id || $teamSimulation->tenant_id !== $evaluation->tenant_id) {
                throw new InvalidArgumentException('Week 8 economic evaluation tenant context is inconsistent.');
            }

            if ($submission->section_simulation_id !== $evaluation->section_simulation_id || $runtimeWeek->section_simulation_id !== $evaluation->section_simulation_id || $teamSimulation->section_simulation_id !== $evaluation->section_simulation_id) {
                throw new InvalidArgumentException('Week 8 economic evaluation section simulation context is inconsistent.');
            }

            if ($submission->section_simulation_week_id !== $evaluation->section_simulation_week_id || $submission->team_simulation_id !== $evaluation->team_simulation_id || $teamSimulation->team_id !== $evaluation->team_id) {
                throw new InvalidArgumentException('Week 8 economic evaluation decision context is inconsistent.');
            }

            if ($evaluation->evaluated_by_user_id !== null) {
                $actor = User::query()->findOrFail($evaluation->evaluated_by_user_id);

                if ($actor->tenant_id !== $evaluation->tenant_id) {
                    throw new InvalidArgumentException('Week 8 economic evaluation actor tenant is inconsistent.');
                }
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Week 8 economic evaluations are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Week 8 economic evaluations are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'expected_wti' => 'decimal:2',
            'expected_upstream_impact_per_bbl' => 'decimal:2',
            'expected_refining_crack' => 'decimal:2',
            'prediction_expected_wti' => 'decimal:2',
            'prediction_expected_upstream_impact_per_bbl' => 'decimal:2',
            'prediction_expected_refining_crack' => 'decimal:2',
            'realized_wti' => 'decimal:2',
            'realized_upstream_impact_per_bbl' => 'decimal:2',
            'realized_refining_crack' => 'decimal:2',
            'realized_retail_volume_percent' => 'decimal:3',
            'prediction_snapshot' => 'array',
            'realization_snapshot' => 'array',
            'input_snapshot' => 'array',
            'output_snapshot' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    public function expectedWtiValue(): ?string
    {
        return $this->nullableDecimal('expected_wti', 2);
    }

    public function predictionExpectedWtiValue(): ?string
    {
        return $this->nullableDecimal('prediction_expected_wti', 2);
    }

    public function realizedWtiValue(): ?string
    {
        return $this->nullableDecimal('realized_wti', 2);
    }

    /**
     * @param  int<0, max>  $scale
     */
    private function nullableDecimal(string $key, int $scale): ?string
    {
        $value = $this->getRawOriginal($key);

        if ($value === null) {
            return null;
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) BigDecimal::of((string) $value)->toScale($scale, RoundingMode::Unnecessary);
        }

        throw new InvalidArgumentException("Week 8 economic evaluation decimal [{$key}] is unavailable.");
    }

    /**
     * @return BelongsTo<DecisionSubmission, $this>
     */
    public function decisionSubmission(): BelongsTo
    {
        return $this->belongsTo(DecisionSubmission::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by_user_id');
    }
}
