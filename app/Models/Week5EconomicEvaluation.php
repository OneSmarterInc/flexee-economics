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

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'team_simulation_id', 'team_id', 'decision_submission_id', 'engine_identifier', 'engine_version', 'package_version', 'status', 'eur_change', 'nok_usd_value_change', 'sgd_usd_value_change', 'norway_benefit_musd', 'norway_lifting_post', 'euro_retail_translation_musd', 'existing_hedge_gain_musd', 'rot_net_eur_musd', 'rot_natural_hedge_ratio', 'rot_net_impact_musd', 'rot_overhedge_loss_musd', 'sing_impact_musd', 'decision_snapshot', 'input_snapshot', 'output_snapshot', 'unavailable_reason', 'evaluated_by_user_id', 'evaluated_by_process', 'evaluated_at'])]
class Week5EconomicEvaluation extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    public const STATUS_UNAVAILABLE_REFERENCE_PACKAGE = 'unavailable_reference_package';

    public const STATUS_CALCULATED = 'calculated';

    protected static function booted(): void
    {
        static::saving(function (Week5EconomicEvaluation $evaluation): void {
            $submission = DecisionSubmission::query()->findOrFail($evaluation->decision_submission_id);
            $runtimeWeek = SectionSimulationWeek::query()->findOrFail($evaluation->section_simulation_week_id);
            $teamSimulation = TeamSimulation::query()->findOrFail($evaluation->team_simulation_id);

            if ($submission->tenant_id !== $evaluation->tenant_id || $runtimeWeek->tenant_id !== $evaluation->tenant_id || $teamSimulation->tenant_id !== $evaluation->tenant_id) {
                throw new InvalidArgumentException('Week 5 economic evaluation tenant context is inconsistent.');
            }

            if ($submission->section_simulation_id !== $evaluation->section_simulation_id || $runtimeWeek->section_simulation_id !== $evaluation->section_simulation_id || $teamSimulation->section_simulation_id !== $evaluation->section_simulation_id) {
                throw new InvalidArgumentException('Week 5 economic evaluation section simulation context is inconsistent.');
            }

            if ($submission->section_simulation_week_id !== $evaluation->section_simulation_week_id || $submission->team_simulation_id !== $evaluation->team_simulation_id || $teamSimulation->team_id !== $evaluation->team_id) {
                throw new InvalidArgumentException('Week 5 economic evaluation decision context is inconsistent.');
            }

            if ($evaluation->evaluated_by_user_id !== null) {
                $actor = User::query()->findOrFail($evaluation->evaluated_by_user_id);

                if ($actor->tenant_id !== $evaluation->tenant_id) {
                    throw new InvalidArgumentException('Week 5 economic evaluation actor tenant is inconsistent.');
                }
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Week 5 economic evaluations are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Week 5 economic evaluations are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'eur_change' => 'decimal:6',
            'nok_usd_value_change' => 'decimal:6',
            'sgd_usd_value_change' => 'decimal:6',
            'norway_benefit_musd' => 'decimal:6',
            'norway_lifting_post' => 'decimal:6',
            'euro_retail_translation_musd' => 'decimal:6',
            'existing_hedge_gain_musd' => 'decimal:6',
            'rot_net_eur_musd' => 'decimal:6',
            'rot_natural_hedge_ratio' => 'decimal:6',
            'rot_net_impact_musd' => 'decimal:6',
            'rot_overhedge_loss_musd' => 'decimal:6',
            'sing_impact_musd' => 'decimal:6',
            'decision_snapshot' => 'array',
            'input_snapshot' => 'array',
            'output_snapshot' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    public function norwayLiftingPostValue(): ?string
    {
        return $this->nullableDecimal('norway_lifting_post', 6);
    }

    public function rotOverhedgeLossValue(): ?string
    {
        return $this->nullableDecimal('rot_overhedge_loss_musd', 6);
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

        throw new InvalidArgumentException("Week 5 economic evaluation decimal [{$key}] is unavailable.");
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
