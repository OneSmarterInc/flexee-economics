<?php

namespace App\Models;

use App\Domain\Economics\Week10\Week10EconomicResult;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'decision_submission_id', 'engine_identifier', 'engine_version', 'package_version', 'status', 'gasoline_demand_hit', 'diesel_demand_hit', 'jet_demand_hit', 'blended_demand_hit', 'baton_rouge_demand_hit', 'rotterdam_demand_hit', 'singapore_demand_hit', 'hardest_hit_refinery', 'binding_constraint_count', 'unresolved_dependencies', 'inherited_state_snapshot', 'input_snapshot', 'output_snapshot', 'unavailable_reason', 'evaluated_by_user_id', 'evaluated_by_process', 'evaluated_at'])]
class Week10EconomicEvaluation extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    public const STATUS_UNAVAILABLE_REFERENCE_PACKAGE = 'unavailable_reference_package';

    public const STATUS_UNRESOLVED_DEPENDENCY = Week10EconomicResult::STATUS_UNRESOLVED_DEPENDENCY;

    public const STATUS_CALCULATED = Week10EconomicResult::STATUS_CALCULATED;

    protected static function booted(): void
    {
        static::saving(function (Week10EconomicEvaluation $evaluation): void {
            $submission = DecisionSubmission::query()->findOrFail($evaluation->decision_submission_id);
            $runtimeWeek = SectionSimulationWeek::query()->findOrFail($evaluation->section_simulation_week_id);
            $teamSimulation = TeamSimulation::query()->findOrFail($evaluation->team_simulation_id);

            if ($submission->tenant_id !== $evaluation->tenant_id || $runtimeWeek->tenant_id !== $evaluation->tenant_id || $teamSimulation->tenant_id !== $evaluation->tenant_id) {
                throw new InvalidArgumentException('Week 10 economic evaluation tenant context is inconsistent.');
            }

            if ($submission->section_simulation_id !== $evaluation->section_simulation_id || $runtimeWeek->section_simulation_id !== $evaluation->section_simulation_id || $teamSimulation->section_simulation_id !== $evaluation->section_simulation_id) {
                throw new InvalidArgumentException('Week 10 economic evaluation section simulation context is inconsistent.');
            }

            if ($submission->section_simulation_week_id !== $evaluation->section_simulation_week_id || $submission->team_simulation_id !== $evaluation->team_simulation_id || $teamSimulation->team_id !== $evaluation->team_id) {
                throw new InvalidArgumentException('Week 10 economic evaluation decision context is inconsistent.');
            }

            if ($evaluation->evaluated_by_user_id !== null) {
                $actor = User::query()->findOrFail($evaluation->evaluated_by_user_id);

                if ($actor->tenant_id !== $evaluation->tenant_id) {
                    throw new InvalidArgumentException('Week 10 economic evaluation actor tenant is inconsistent.');
                }
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Week 10 economic evaluations are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Week 10 economic evaluations are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'gasoline_demand_hit' => 'decimal:6',
            'diesel_demand_hit' => 'decimal:6',
            'jet_demand_hit' => 'decimal:6',
            'blended_demand_hit' => 'decimal:6',
            'baton_rouge_demand_hit' => 'decimal:6',
            'rotterdam_demand_hit' => 'decimal:6',
            'singapore_demand_hit' => 'decimal:6',
            'binding_constraint_count' => 'integer',
            'unresolved_dependencies' => 'array',
            'inherited_state_snapshot' => 'array',
            'input_snapshot' => 'array',
            'output_snapshot' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    public function blendedDemandHitValue(): ?string
    {
        return $this->nullableDecimal('blended_demand_hit', 6);
    }

    public function singaporeDemandHitValue(): ?string
    {
        return $this->nullableDecimal('singapore_demand_hit', 6);
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

        throw new InvalidArgumentException("Week 10 economic evaluation decimal [{$key}] is unavailable.");
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
