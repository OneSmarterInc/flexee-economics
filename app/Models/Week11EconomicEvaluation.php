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

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'decision_submission_id', 'engine_identifier', 'engine_version', 'package_version', 'status', 'realized_price', 'profit_oil', 'annual_mbbl', 'annuity_factor', 'margin_current', 'pv_stay_current_musd', 'margin_mid', 'pv_stay_mid_musd', 'margin_demanded', 'pv_stay_demanded_musd', 'margin_harsh', 'pv_stay_harsh_musd', 'exit_value_musd', 'stay_minus_exit_demanded_musd', 'indifference_take', 'comparables_min', 'comparables_max', 'demanded_take', 'demanded_take_inside_comparables', 'staying_beats_exit_across_take_grid', 'stay_value_falls_as_take_rises', 'sunk_invariant', 'take_results', 'worked_example_snapshot', 'input_snapshot', 'output_snapshot', 'unavailable_reason', 'evaluated_by_user_id', 'evaluated_by_process', 'evaluated_at'])]
class Week11EconomicEvaluation extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    public const STATUS_UNAVAILABLE_REFERENCE_PACKAGE = 'unavailable_reference_package';

    public const STATUS_CALCULATED = 'calculated';

    protected static function booted(): void
    {
        static::saving(function (Week11EconomicEvaluation $evaluation): void {
            $submission = DecisionSubmission::query()->findOrFail($evaluation->decision_submission_id);
            $runtimeWeek = SectionSimulationWeek::query()->findOrFail($evaluation->section_simulation_week_id);
            $teamSimulation = TeamSimulation::query()->findOrFail($evaluation->team_simulation_id);

            if ($submission->tenant_id !== $evaluation->tenant_id || $runtimeWeek->tenant_id !== $evaluation->tenant_id || $teamSimulation->tenant_id !== $evaluation->tenant_id) {
                throw new InvalidArgumentException('Week 11 economic evaluation tenant context is inconsistent.');
            }

            if ($submission->section_simulation_id !== $evaluation->section_simulation_id || $runtimeWeek->section_simulation_id !== $evaluation->section_simulation_id || $teamSimulation->section_simulation_id !== $evaluation->section_simulation_id) {
                throw new InvalidArgumentException('Week 11 economic evaluation section simulation context is inconsistent.');
            }

            if ($submission->section_simulation_week_id !== $evaluation->section_simulation_week_id || $submission->team_simulation_id !== $evaluation->team_simulation_id || $teamSimulation->team_id !== $evaluation->team_id) {
                throw new InvalidArgumentException('Week 11 economic evaluation decision context is inconsistent.');
            }

            if ($evaluation->evaluated_by_user_id !== null) {
                $actor = User::query()->findOrFail($evaluation->evaluated_by_user_id);

                if ($actor->tenant_id !== $evaluation->tenant_id) {
                    throw new InvalidArgumentException('Week 11 economic evaluation actor tenant is inconsistent.');
                }
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Week 11 economic evaluations are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Week 11 economic evaluations are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'realized_price' => 'decimal:6',
            'profit_oil' => 'decimal:6',
            'annual_mbbl' => 'decimal:6',
            'annuity_factor' => 'decimal:6',
            'margin_current' => 'decimal:6',
            'pv_stay_current_musd' => 'decimal:6',
            'margin_mid' => 'decimal:6',
            'pv_stay_mid_musd' => 'decimal:6',
            'margin_demanded' => 'decimal:6',
            'pv_stay_demanded_musd' => 'decimal:6',
            'margin_harsh' => 'decimal:6',
            'pv_stay_harsh_musd' => 'decimal:6',
            'exit_value_musd' => 'decimal:6',
            'stay_minus_exit_demanded_musd' => 'decimal:6',
            'indifference_take' => 'decimal:6',
            'comparables_min' => 'decimal:6',
            'comparables_max' => 'decimal:6',
            'demanded_take' => 'decimal:6',
            'demanded_take_inside_comparables' => 'boolean',
            'staying_beats_exit_across_take_grid' => 'boolean',
            'stay_value_falls_as_take_rises' => 'boolean',
            'sunk_invariant' => 'boolean',
            'take_results' => 'array',
            'worked_example_snapshot' => 'array',
            'input_snapshot' => 'array',
            'output_snapshot' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    public function profitOilValue(): ?string
    {
        return $this->nullableDecimal('profit_oil', 4);
    }

    public function pvStayDemandedValue(): ?string
    {
        return $this->nullableDecimal('pv_stay_demanded_musd', 4);
    }

    public function indifferenceTakeValue(): ?string
    {
        return $this->nullableDecimal('indifference_take', 6);
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
            return (string) BigDecimal::of((string) $value)->toScale($scale, RoundingMode::HalfUp);
        }

        throw new InvalidArgumentException("Week 11 economic evaluation decimal [{$key}] is unavailable.");
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
