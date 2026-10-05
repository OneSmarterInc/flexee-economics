<?php

namespace App\Domain\Capital;

use App\Models\DiscountRateConsequence;
use App\Models\DiscountRateSchedule;
use App\Models\EconomicResolution;
use App\Models\SectionSimulationWeek;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;

final class DiscountRateConsequenceService
{
    private const MARGINAL_COST_ANCHOR = '18.70';

    private const MARKET_BASED_ANCHOR = '73.70';

    private const CLASSIFICATION_TOLERANCE = '0.10';

    public function resolve(EconomicResolution $resolution, DiscountRateSchedule $schedule, User $actor): DiscountRateConsequence
    {
        $resolution->loadMissing(['runtimeWeek.definition', 'teamSimulation', 'teamSimulation.sectionSimulation.weeks.definition']);
        $this->assertCanResolve($actor, $resolution);
        $this->assertScheduleMatchesResolution($schedule, $resolution);

        /** @var SectionSimulationWeek $targetWeek */
        $targetWeek = $resolution->teamSimulation->sectionSimulation->weeks()
            ->whereHas('definition', fn ($query) => $query->where('week_number', $schedule->target_week_number))
            ->firstOrFail();

        return DB::transaction(function () use ($resolution, $schedule, $actor, $targetWeek): DiscountRateConsequence {
            $existing = DiscountRateConsequence::query()
                ->where('tenant_id', $resolution->tenant_id)
                ->where('economic_resolution_id', $resolution->id)
                ->where('discount_rate_schedule_id', $schedule->id)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof DiscountRateConsequence) {
                return $existing;
            }

            $classification = $this->classify($resolution, $schedule);
            $result = $classification !== null
                ? $this->resolvedResult($classification, $schedule)
                : $this->unresolvedResult($schedule);

            return DiscountRateConsequence::query()->create([
                'tenant_id' => $resolution->tenant_id,
                'section_simulation_id' => $resolution->section_simulation_id,
                'source_section_simulation_week_id' => $resolution->section_simulation_week_id,
                'target_section_simulation_week_id' => $targetWeek->id,
                'team_simulation_id' => $resolution->team_simulation_id,
                'team_id' => $resolution->team_id,
                'economic_resolution_id' => $resolution->id,
                'discount_rate_schedule_id' => $schedule->id,
                'schedule_key' => $schedule->key,
                'schedule_version' => $schedule->version,
                'status' => $result['status'],
                'classification' => $classification,
                'discount_rate_percent' => $result['discount_rate_percent'],
                'capital_envelope_musd' => $result['capital_envelope_musd'],
                'input_snapshot' => $this->inputSnapshot($resolution, $schedule),
                'result_snapshot' => $result,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => Carbon::now(),
            ]);
        });
    }

    /**
     * @return array<string, int>
     */
    public function resolveSectionCohort(SectionSimulationWeek $sourceWeek, DiscountRateSchedule $schedule, User $actor): array
    {
        $sourceWeek->loadMissing(['definition', 'sectionSimulation.weeks.definition']);

        if ($sourceWeek->definition->week_number !== $schedule->source_week_number) {
            throw new InvalidArgumentException('Discount-rate schedule source week does not match runtime week.');
        }

        if (! $schedule->is_active) {
            throw new InvalidArgumentException('Inactive discount-rate schedules cannot be resolved.');
        }

        $resolutions = EconomicResolution::query()
            ->where('tenant_id', $sourceWeek->tenant_id)
            ->where('section_simulation_week_id', $sourceWeek->id)
            ->orderBy('team_simulation_id')
            ->get();

        if ($resolutions->isEmpty()) {
            return [];
        }

        /** @var SectionSimulationWeek $targetWeek */
        $targetWeek = $sourceWeek->sectionSimulation->weeks()
            ->whereHas('definition', fn ($query) => $query->where('week_number', $schedule->target_week_number))
            ->firstOrFail();

        $classification = $this->sectionClassification($resolutions);
        $result = $this->resolvedResult($classification, $schedule);
        $counts = [];

        DB::transaction(function () use ($resolutions, $schedule, $actor, $targetWeek, $classification, $result, &$counts): void {
            $resolutions->each(function (EconomicResolution $resolution) use ($schedule, $actor, $targetWeek, $classification, $result, &$counts): void {
                $existing = DiscountRateConsequence::query()
                    ->where('tenant_id', $resolution->tenant_id)
                    ->where('economic_resolution_id', $resolution->id)
                    ->where('discount_rate_schedule_id', $schedule->id)
                    ->lockForUpdate()
                    ->first();

                $consequence = $existing instanceof DiscountRateConsequence
                    ? $existing
                    : DiscountRateConsequence::query()->create([
                        'tenant_id' => $resolution->tenant_id,
                        'section_simulation_id' => $resolution->section_simulation_id,
                        'source_section_simulation_week_id' => $resolution->section_simulation_week_id,
                        'target_section_simulation_week_id' => $targetWeek->id,
                        'team_simulation_id' => $resolution->team_simulation_id,
                        'team_id' => $resolution->team_id,
                        'economic_resolution_id' => $resolution->id,
                        'discount_rate_schedule_id' => $schedule->id,
                        'schedule_key' => $schedule->key,
                        'schedule_version' => $schedule->version,
                        'status' => $result['status'],
                        'classification' => $classification,
                        'discount_rate_percent' => $result['discount_rate_percent'],
                        'capital_envelope_musd' => $result['capital_envelope_musd'],
                        'input_snapshot' => $this->sectionInputSnapshot($resolution, $schedule, $classification),
                        'result_snapshot' => $result,
                        'resolved_by_user_id' => $actor->id,
                        'resolved_at' => Carbon::now(),
                    ]);

                $counts[$consequence->status] = ($counts[$consequence->status] ?? 0) + 1;
            });
        });

        return $counts;
    }

    private function assertCanResolve(User $actor, EconomicResolution $resolution): void
    {
        if ($actor->tenant_id !== $resolution->tenant_id) {
            throw new InvalidArgumentException('Actor cannot resolve discount-rate consequences for another tenant.');
        }

        if ($actor->isAdministrator()) {
            return;
        }

        if ($actor->isFaculty()) {
            $assigned = $actor->facultySections()
                ->wherePivot('tenant_id', $resolution->tenant_id)
                ->whereHas('sectionSimulations', fn ($query) => $query->whereKey($resolution->section_simulation_id))
                ->exists();

            if ($assigned) {
                return;
            }
        }

        throw new InvalidArgumentException('Only authorized faculty can resolve discount-rate consequences.');
    }

    private function assertScheduleMatchesResolution(DiscountRateSchedule $schedule, EconomicResolution $resolution): void
    {
        if (! $schedule->is_active) {
            throw new InvalidArgumentException('Inactive discount-rate schedules cannot be resolved.');
        }

        if ($resolution->runtimeWeek->definition->week_number !== $schedule->source_week_number) {
            throw new InvalidArgumentException('Discount-rate schedule source week does not match economic resolution.');
        }
    }

    /**
     * @throws JsonException
     */
    private function classify(EconomicResolution $resolution, DiscountRateSchedule $schedule): ?string
    {
        foreach ($schedule->classificationRules() as $rule) {
            $field = (string) ($rule['field'] ?? '');
            $operator = (string) ($rule['operator'] ?? '');
            $classification = (string) ($rule['classification'] ?? '');

            if ($field === '' || $operator === '' || $classification === '' || ! array_key_exists('value', $rule)) {
                continue;
            }

            $actual = $this->resolutionMetric($resolution, $field);

            if ($actual === null) {
                continue;
            }

            if ($this->matches($actual, $operator, BigDecimal::of((string) $rule['value']))) {
                return $classification;
            }
        }

        return null;
    }

    private function resolutionMetric(EconomicResolution $resolution, string $field): ?BigDecimal
    {
        $allowed = [
            'transfer_price',
            'integrated_margin',
            'upstream_margin',
            'refining_margin',
            'upstream_vs_target',
            'refining_vs_target',
            'geneva_gap',
            'geneva_capture_per_bbl',
            'geneva_max_volume_bbl_day',
        ];

        if (! in_array($field, $allowed, true)) {
            return null;
        }

        $value = $resolution->getRawOriginal($field);

        if (is_string($value) || is_int($value) || is_float($value)) {
            return BigDecimal::of((string) $value);
        }

        return null;
    }

    private function matches(BigDecimal $actual, string $operator, BigDecimal $expected): bool
    {
        return match ($operator) {
            '<' => $actual->isLessThan($expected),
            '<=' => $actual->isLessThanOrEqualTo($expected),
            '=' => $actual->isEqualTo($expected),
            '>=' => $actual->isGreaterThanOrEqualTo($expected),
            '>' => $actual->isGreaterThan($expected),
            default => false,
        };
    }

    /**
     * @param  Collection<int, EconomicResolution>  $resolutions
     */
    private function sectionClassification(Collection $resolutions): string
    {
        $total = $resolutions->count();
        $marginal = 0;
        $market = 0;

        foreach ($resolutions as $resolution) {
            $classification = $this->classifyTransferPriceChoice(BigDecimal::of((string) $resolution->transfer_price));

            if ($classification === 'marginal_cost') {
                $marginal++;
            } elseif ($classification === 'market_based') {
                $market++;
            }
        }

        $threshold = BigDecimal::of('0.70');
        $marginalShare = BigDecimal::of((string) $marginal)->dividedBy((string) $total, 12, RoundingMode::HalfUp);
        $marketShare = BigDecimal::of((string) $market)->dividedBy((string) $total, 12, RoundingMode::HalfUp);

        if ($marginalShare->isGreaterThanOrEqualTo($threshold)) {
            return 'disciplined';
        }

        if ($marketShare->isGreaterThanOrEqualTo($threshold)) {
            return 'lax';
        }

        return 'base';
    }

    public function classifyTransferPriceChoice(BigDecimal $transferPrice): ?string
    {
        if ($this->withinTolerance($transferPrice, BigDecimal::of(self::MARGINAL_COST_ANCHOR))) {
            return 'marginal_cost';
        }

        if ($this->withinTolerance($transferPrice, BigDecimal::of(self::MARKET_BASED_ANCHOR))) {
            return 'market_based';
        }

        return null;
    }

    private function withinTolerance(BigDecimal $actual, BigDecimal $anchor): bool
    {
        $band = $anchor->multipliedBy(self::CLASSIFICATION_TOLERANCE);

        return $actual->isGreaterThanOrEqualTo($anchor->minus($band))
            && $actual->isLessThanOrEqualTo($anchor->plus($band));
    }

    /**
     * @return array{status: string, classification: string, discount_rate_percent: string, capital_envelope_musd: string, reason: string|null}
     *
     * @throws JsonException
     */
    private function resolvedResult(string $classification, DiscountRateSchedule $schedule): array
    {
        $outcomes = $schedule->classificationOutcomes();
        $outcome = $outcomes[$classification] ?? null;

        if ($outcome === null) {
            throw new InvalidArgumentException("Discount-rate outcome [{$classification}] is not configured.");
        }

        return [
            'status' => DiscountRateConsequence::STATUS_RESOLVED,
            'classification' => $classification,
            'discount_rate_percent' => $this->decimal($outcome['discount_rate_percent'] ?? null),
            'capital_envelope_musd' => $this->decimal($outcome['capital_envelope_musd'] ?? null),
            'reason' => null,
        ];
    }

    /**
     * @return array{status: string, classification: null, discount_rate_percent: null, capital_envelope_musd: null, reason: string}
     */
    private function unresolvedResult(DiscountRateSchedule $schedule): array
    {
        return [
            'status' => DiscountRateConsequence::STATUS_UNRESOLVED,
            'classification' => null,
            'discount_rate_percent' => null,
            'capital_envelope_musd' => null,
            'reason' => $schedule->classificationRules() === []
                ? 'classification rules are not configured'
                : 'no classification rule matched the source resolution',
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function inputSnapshot(EconomicResolution $resolution, DiscountRateSchedule $schedule): array
    {
        return [
            'source_resolution_id' => $resolution->id,
            'source_week_id' => $resolution->section_simulation_week_id,
            'target_week_number' => $schedule->target_week_number,
            'schedule' => [
                'key' => $schedule->key,
                'version' => $schedule->version,
                'classification_rules' => $schedule->classificationRules(),
                'classification_outcomes' => $schedule->classificationOutcomes(),
            ],
            'resolution_metrics' => [
                'transfer_price' => $resolution->transfer_price,
                'integrated_margin' => $resolution->integrated_margin,
                'upstream_margin' => $resolution->upstream_margin,
                'refining_margin' => $resolution->refining_margin,
                'geneva_capture_per_bbl' => $resolution->geneva_capture_per_bbl,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionInputSnapshot(EconomicResolution $resolution, DiscountRateSchedule $schedule, string $classification): array
    {
        $base = $this->inputSnapshot($resolution, $schedule);
        $base['section_cohort_classification'] = [
            'classification' => $classification,
            'classification_source' => 'section_transfer_price_distribution',
            'marginal_cost_anchor' => self::MARGINAL_COST_ANCHOR,
            'market_based_anchor' => self::MARKET_BASED_ANCHOR,
            'tolerance' => self::CLASSIFICATION_TOLERANCE,
            'hidden_until_week6' => true,
        ];

        return $base;
    }

    private function decimal(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException('Discount-rate outcome decimals must be configured.');
        }

        return (string) BigDecimal::of((string) $value)->toScale(3, RoundingMode::Unnecessary);
    }
}
