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

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'decision_submission_id', 'engine_identifier', 'engine_version', 'package_identifier', 'package_version', 'status', 'norway_gross_cost_musd', 'norway_after_tax_cost_musd', 'norway_after_tax_share', 'permian_mrp_k', 'mrp_to_wage', 'turnaround_peak_cost_musd', 'delay_expected_cost_musd', 'delay_saving_musd', 'delay_saving_pct', 'asset_health_penalty_pts', 'wage_benchmarks', 'ordering_assertions', 'worked_example_snapshot', 'input_snapshot', 'output_snapshot', 'unavailable_reason', 'evaluated_by_user_id', 'evaluated_by_process', 'evaluated_at'])]
class Week13EconomicEvaluation extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    public const STATUS_UNAVAILABLE_REFERENCE_PACKAGE = 'unavailable_reference_package';

    public const STATUS_CALCULATED = 'calculated';

    protected static function booted(): void
    {
        static::saving(function (Week13EconomicEvaluation $evaluation): void {
            $submission = DecisionSubmission::query()->findOrFail($evaluation->decision_submission_id);
            $runtimeWeek = SectionSimulationWeek::query()->findOrFail($evaluation->section_simulation_week_id);
            $teamSimulation = TeamSimulation::query()->findOrFail($evaluation->team_simulation_id);

            if ($submission->tenant_id !== $evaluation->tenant_id || $runtimeWeek->tenant_id !== $evaluation->tenant_id || $teamSimulation->tenant_id !== $evaluation->tenant_id) {
                throw new InvalidArgumentException('Week 13 economic evaluation tenant context is inconsistent.');
            }

            if ($submission->section_simulation_id !== $evaluation->section_simulation_id || $runtimeWeek->section_simulation_id !== $evaluation->section_simulation_id || $teamSimulation->section_simulation_id !== $evaluation->section_simulation_id) {
                throw new InvalidArgumentException('Week 13 economic evaluation section simulation context is inconsistent.');
            }

            if ($submission->section_simulation_week_id !== $evaluation->section_simulation_week_id || $submission->team_simulation_id !== $evaluation->team_simulation_id || $teamSimulation->team_id !== $evaluation->team_id) {
                throw new InvalidArgumentException('Week 13 economic evaluation decision context is inconsistent.');
            }

            if ($evaluation->evaluated_by_user_id !== null) {
                $actor = User::query()->findOrFail($evaluation->evaluated_by_user_id);

                if ($actor->tenant_id !== $evaluation->tenant_id) {
                    throw new InvalidArgumentException('Week 13 economic evaluation actor tenant is inconsistent.');
                }
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Week 13 economic evaluations are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Week 13 economic evaluations are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'norway_gross_cost_musd' => 'decimal:6',
            'norway_after_tax_cost_musd' => 'decimal:6',
            'norway_after_tax_share' => 'decimal:6',
            'permian_mrp_k' => 'decimal:6',
            'mrp_to_wage' => 'decimal:6',
            'turnaround_peak_cost_musd' => 'decimal:6',
            'delay_expected_cost_musd' => 'decimal:6',
            'delay_saving_musd' => 'decimal:6',
            'delay_saving_pct' => 'decimal:6',
            'asset_health_penalty_pts' => 'decimal:6',
            'wage_benchmarks' => 'array',
            'ordering_assertions' => 'array',
            'worked_example_snapshot' => 'array',
            'input_snapshot' => 'array',
            'output_snapshot' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    public function norwayAfterTaxCostValue(): ?string
    {
        return $this->nullableDecimal('norway_after_tax_cost_musd', 4);
    }

    public function mrpToWageValue(): ?string
    {
        return $this->nullableDecimal('mrp_to_wage', 6);
    }

    public function delaySavingPctValue(): ?string
    {
        return $this->nullableDecimal('delay_saving_pct', 6);
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

        throw new InvalidArgumentException("Week 13 economic evaluation decimal [{$key}] is unavailable.");
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
