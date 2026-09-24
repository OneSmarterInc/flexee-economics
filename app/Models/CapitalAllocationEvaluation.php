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

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'capital_allocation_decision_id', 'engine_identifier', 'engine_version', 'status', 'portfolio_npv_musd', 'portfolio_irr_percent', 'capital_required_musd', 'capital_envelope_feasible', 'input_snapshot', 'output_snapshot', 'unavailable_reason', 'evaluated_by_user_id', 'evaluated_by_process', 'evaluated_at'])]
class CapitalAllocationEvaluation extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    public const STATUS_UNAVAILABLE_REFERENCE_PACKAGE = 'unavailable_reference_package';

    public const STATUS_UNAVAILABLE_DISCOUNT_RATE_CONTEXT = 'unavailable_discount_rate_context';

    public const STATUS_CALCULATED = 'calculated';

    protected static function booted(): void
    {
        static::saving(function (CapitalAllocationEvaluation $evaluation): void {
            $decision = CapitalAllocationDecision::query()->findOrFail($evaluation->capital_allocation_decision_id);
            $runtimeWeek = SectionSimulationWeek::query()->findOrFail($evaluation->section_simulation_week_id);
            $teamSimulation = TeamSimulation::query()->findOrFail($evaluation->team_simulation_id);

            if ($decision->tenant_id !== $evaluation->tenant_id || $runtimeWeek->tenant_id !== $evaluation->tenant_id || $teamSimulation->tenant_id !== $evaluation->tenant_id) {
                throw new InvalidArgumentException('Capital allocation evaluation tenant context is inconsistent.');
            }

            if ($decision->section_simulation_id !== $evaluation->section_simulation_id || $runtimeWeek->section_simulation_id !== $evaluation->section_simulation_id || $teamSimulation->section_simulation_id !== $evaluation->section_simulation_id) {
                throw new InvalidArgumentException('Capital allocation evaluation section simulation context is inconsistent.');
            }

            if ($decision->section_simulation_week_id !== $evaluation->section_simulation_week_id || $decision->team_simulation_id !== $evaluation->team_simulation_id || $teamSimulation->team_id !== $evaluation->team_id) {
                throw new InvalidArgumentException('Capital allocation evaluation decision context is inconsistent.');
            }

            if ($evaluation->evaluated_by_user_id !== null) {
                $actor = User::query()->findOrFail($evaluation->evaluated_by_user_id);

                if ($actor->tenant_id !== $evaluation->tenant_id) {
                    throw new InvalidArgumentException('Capital allocation evaluation actor tenant is inconsistent.');
                }
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Capital allocation evaluations are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Capital allocation evaluations are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'portfolio_npv_musd' => 'decimal:3',
            'portfolio_irr_percent' => 'decimal:4',
            'capital_required_musd' => 'decimal:3',
            'capital_envelope_feasible' => 'boolean',
            'input_snapshot' => 'array',
            'output_snapshot' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    public function portfolioNpvMusdValue(): ?string
    {
        return $this->nullableDecimal('portfolio_npv_musd', 3);
    }

    public function portfolioIrrPercentValue(): ?string
    {
        return $this->nullableDecimal('portfolio_irr_percent', 4);
    }

    public function capitalRequiredMusdValue(): ?string
    {
        return $this->nullableDecimal('capital_required_musd', 3);
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

        throw new InvalidArgumentException("Capital allocation evaluation decimal [{$key}] is unavailable.");
    }

    /**
     * @return BelongsTo<CapitalAllocationDecision, $this>
     */
    public function decision(): BelongsTo
    {
        return $this->belongsTo(CapitalAllocationDecision::class, 'capital_allocation_decision_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by_user_id');
    }
}
