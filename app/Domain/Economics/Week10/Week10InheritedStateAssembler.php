<?php

namespace App\Domain\Economics\Week10;

use App\Models\CapitalAllocationEvaluation;
use App\Models\Counterparty;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\SectionSimulationWeek;
use App\Models\StandingState;
use App\Models\TeamSimulation;
use App\Models\Week5EconomicEvaluation;
use App\Models\Week8EconomicEvaluation;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

final class Week10InheritedStateAssembler
{
    public function assemble(DecisionSubmission $week10Submission): Week10InheritedState
    {
        $week10Submission->loadMissing('teamSimulation');

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $week10Submission->teamSimulation;

        $cancellableCapex = $this->cancellableCapex($teamSimulation);
        $hedgeCoverage = $this->crudeHedgeCoverage($teamSimulation);
        $batonRougeCondition = $this->batonRougeCondition($teamSimulation);
        $standing = $this->straitsPacificStanding($teamSimulation);
        $cashCushion = $this->cashCushion($teamSimulation);

        return new Week10InheritedState(
            cancellableCapexMusd: $this->availableDecimal($cancellableCapex),
            crudeHedgeCoverage: $this->availableDecimal($hedgeCoverage),
            batonRougeReportedMarginStrong: $this->availableBool($batonRougeCondition),
            straitsPacificStanding: $standing->isAvailable() ? $standing->sourceValue : null,
            cashCushionMusd: $this->availableDecimal($cashCushion),
            dependencies: [
                'cancellable_capex_musd' => $cancellableCapex,
                'crude_hedge_coverage' => $hedgeCoverage,
                'br_reported_margin_strong' => $batonRougeCondition,
                'straits_pacific_standing' => $standing,
                'cash_cushion_musd' => $cashCushion,
            ],
        );
    }

    private function cancellableCapex(TeamSimulation $teamSimulation): Week10HistoricalDependency
    {
        $week = $this->runtimeWeek($teamSimulation, 6);

        if (! $week instanceof SectionSimulationWeek) {
            return Week10HistoricalDependency::unresolved('cancellable_capex_musd', '6', 'capital_allocation_evaluation', 'Week 6 runtime week is unavailable.');
        }

        $evaluation = CapitalAllocationEvaluation::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('section_simulation_week_id', $week->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('status', CapitalAllocationEvaluation::STATUS_CALCULATED)
            ->latest('evaluated_at')
            ->first();

        if (! $evaluation instanceof CapitalAllocationEvaluation) {
            return Week10HistoricalDependency::unresolved('cancellable_capex_musd', '6', 'capital_allocation_evaluation', 'Week 6 calculated capital allocation evaluation is missing.');
        }

        $value = $this->inheritedValue($this->arrayAttribute($evaluation, 'output_snapshot'), 'cancellable_capex_musd')
            ?? $this->inheritedValue($this->arrayAttribute($evaluation, 'input_snapshot'), 'cancellable_capex_musd');

        if ($value === null) {
            return Week10HistoricalDependency::unresolved('cancellable_capex_musd', '6', 'capital_allocation_evaluation', 'Week 6 evaluation does not expose cancellable capex for Week 10.');
        }

        return Week10HistoricalDependency::available(
            key: 'cancellable_capex_musd',
            sourceWeek: '6',
            sourceEntity: 'capital_allocation_evaluation',
            sourceValue: (string) $value,
            sourceId: (string) $evaluation->id,
            sourceVersion: (string) $evaluation->engine_version,
        );
    }

    private function crudeHedgeCoverage(TeamSimulation $teamSimulation): Week10HistoricalDependency
    {
        $week = $this->runtimeWeek($teamSimulation, 5);

        if (! $week instanceof SectionSimulationWeek) {
            return Week10HistoricalDependency::unresolved('crude_hedge_coverage', '5', 'week5_economic_evaluation', 'Week 5 runtime week is unavailable.');
        }

        $evaluation = Week5EconomicEvaluation::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('section_simulation_week_id', $week->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('status', Week5EconomicEvaluation::STATUS_CALCULATED)
            ->latest('evaluated_at')
            ->first();

        if (! $evaluation instanceof Week5EconomicEvaluation) {
            return Week10HistoricalDependency::unresolved('crude_hedge_coverage', '5', 'week5_economic_evaluation', 'Week 5 calculated currency evaluation is missing.');
        }

        $value = $this->inheritedValue($this->arrayAttribute($evaluation, 'output_snapshot'), 'crude_hedge_coverage')
            ?? $this->inheritedValue($this->arrayAttribute($evaluation, 'input_snapshot'), 'crude_hedge_coverage')
            ?? $this->inheritedValue($this->arrayAttribute($evaluation, 'decision_snapshot'), 'crude_hedge_coverage')
            ?? $this->inheritedValue($this->arrayAttribute($evaluation, 'decision_snapshot'), 'hedge_coverage');

        if ($value === null) {
            return Week10HistoricalDependency::unresolved('crude_hedge_coverage', '5', 'week5_economic_evaluation', 'Week 5 evaluation does not expose crude hedge coverage for Week 10.');
        }

        return Week10HistoricalDependency::available(
            key: 'crude_hedge_coverage',
            sourceWeek: '5',
            sourceEntity: 'week5_economic_evaluation',
            sourceValue: (string) $value,
            sourceId: (string) $evaluation->id,
            sourceVersion: (string) $evaluation->engine_version,
        );
    }

    private function batonRougeCondition(TeamSimulation $teamSimulation): Week10HistoricalDependency
    {
        $week = $this->runtimeWeek($teamSimulation, 4);

        if (! $week instanceof SectionSimulationWeek) {
            return Week10HistoricalDependency::unresolved('br_reported_margin_strong', '4', 'economic_resolution', 'Week 4 runtime week is unavailable.');
        }

        $resolution = EconomicResolution::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('section_simulation_week_id', $week->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->latest('resolved_at')
            ->first();

        if (! $resolution instanceof EconomicResolution) {
            return Week10HistoricalDependency::unresolved('br_reported_margin_strong', '4', 'economic_resolution', 'Week 4 economic resolution is missing.');
        }

        $value = $this->inheritedValue($this->arrayAttribute($resolution, 'output_snapshot'), 'br_reported_margin_strong')
            ?? $this->inheritedValue($this->arrayAttribute($resolution, 'input_snapshot'), 'br_reported_margin_strong');

        if ($value === null) {
            return Week10HistoricalDependency::unresolved('br_reported_margin_strong', '4', 'economic_resolution', 'Week 4 resolution does not expose Baton Rouge reported-margin condition for Week 10.');
        }

        return Week10HistoricalDependency::available(
            key: 'br_reported_margin_strong',
            sourceWeek: '4',
            sourceEntity: 'economic_resolution',
            sourceValue: $this->boolString($value),
            sourceId: (string) $resolution->id,
            sourceVersion: (string) $resolution->engine_version,
        );
    }

    private function straitsPacificStanding(TeamSimulation $teamSimulation): Week10HistoricalDependency
    {
        $counterparty = Counterparty::query()->where('key', 'straits_pacific')->first();

        if (! $counterparty instanceof Counterparty) {
            return Week10HistoricalDependency::unresolved('straits_pacific_standing', 'standing_history', 'standing_state', 'Straits Pacific counterparty is missing.');
        }

        $standing = StandingState::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('counterparty_id', $counterparty->id)
            ->first();

        if (! $standing instanceof StandingState) {
            return Week10HistoricalDependency::unresolved('straits_pacific_standing', 'standing_history', 'standing_state', 'Straits Pacific standing state is missing.');
        }

        $changedAt = $standing->getAttribute('state_changed_at');

        return Week10HistoricalDependency::available(
            key: 'straits_pacific_standing',
            sourceWeek: 'standing_history',
            sourceEntity: 'standing_state',
            sourceValue: $standing->stateEnum()->value,
            sourceId: (string) $standing->id,
            sourceVersion: $changedAt instanceof CarbonInterface ? $changedAt->toIso8601String() : null,
        );
    }

    private function cashCushion(TeamSimulation $teamSimulation): Week10HistoricalDependency
    {
        $week = $this->runtimeWeek($teamSimulation, 8);

        if (! $week instanceof SectionSimulationWeek) {
            return Week10HistoricalDependency::unresolved('cash_cushion_musd', '8', 'week8_economic_evaluation', 'Week 8 runtime week is unavailable.');
        }

        $evaluation = Week8EconomicEvaluation::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('section_simulation_week_id', $week->id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('status', Week8EconomicEvaluation::STATUS_CALCULATED)
            ->latest('evaluated_at')
            ->first();

        if (! $evaluation instanceof Week8EconomicEvaluation) {
            return Week10HistoricalDependency::unresolved('cash_cushion_musd', '8', 'week8_economic_evaluation', 'Week 8 calculated economic evaluation is missing.');
        }

        $value = $this->inheritedValue($this->arrayAttribute($evaluation, 'output_snapshot'), 'cash_cushion_musd')
            ?? $this->inheritedValue($this->arrayAttribute($evaluation, 'input_snapshot'), 'cash_cushion_musd');

        if ($value === null) {
            return Week10HistoricalDependency::unresolved('cash_cushion_musd', '8', 'week8_economic_evaluation', 'Week 8 evaluation does not expose cash cushion for Week 10.');
        }

        return Week10HistoricalDependency::available(
            key: 'cash_cushion_musd',
            sourceWeek: '8',
            sourceEntity: 'week8_economic_evaluation',
            sourceValue: (string) $value,
            sourceId: (string) $evaluation->id,
            sourceVersion: (string) $evaluation->engine_version,
        );
    }

    private function runtimeWeek(TeamSimulation $teamSimulation, int $weekNumber): ?SectionSimulationWeek
    {
        return SectionSimulationWeek::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('section_simulation_id', $teamSimulation->section_simulation_id)
            ->whereHas('definition', fn ($query) => $query->where('week_number', $weekNumber))
            ->first();
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    private function inheritedValue(?array $snapshot, string $key): mixed
    {
        if ($snapshot === null) {
            return null;
        }

        foreach (['week10_inherited_state', 'week10', 'historical_dependencies', 'values'] as $container) {
            $value = $snapshot[$container][$key] ?? null;

            if ($value !== null) {
                return $value;
            }
        }

        return $snapshot[$key] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function arrayAttribute(Model $model, string $key): ?array
    {
        $value = $model->getAttribute($key);

        return is_array($value) ? $value : null;
    }

    private function availableDecimal(Week10HistoricalDependency $dependency): ?BigDecimal
    {
        return $dependency->isAvailable() && $dependency->sourceValue !== null
            ? BigDecimal::of($dependency->sourceValue)
            : null;
    }

    private function availableBool(Week10HistoricalDependency $dependency): ?bool
    {
        if (! $dependency->isAvailable() || $dependency->sourceValue === null) {
            return null;
        }

        return filter_var($dependency->sourceValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function boolString(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }
}
