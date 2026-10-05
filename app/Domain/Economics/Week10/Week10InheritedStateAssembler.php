<?php

namespace App\Domain\Economics\Week10;

use App\Domain\Consequences\DerivedWeek10ConstraintService;
use App\Models\ConsequenceLink;
use App\Models\Counterparty;
use App\Models\DecisionSubmission;
use App\Models\StandingState;
use App\Models\TeamSimulation;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;

final class Week10InheritedStateAssembler
{
    public function __construct(
        private readonly DerivedWeek10ConstraintService $derivedConstraints,
    ) {}

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
        return $this->consequenceDependency(
            teamSimulation: $teamSimulation,
            key: 'cancellable_capex_musd',
            sourceWeek: '6',
            sourceEntity: 'consequence_link.week6_cancellable_capex_musd',
            definitionKey: 'week6_cancellable_capex_musd',
            missingReason: 'Week 6 cancellable capex consequence is missing.',
        );
    }

    private function crudeHedgeCoverage(TeamSimulation $teamSimulation): Week10HistoricalDependency
    {
        return $this->consequenceDependency(
            teamSimulation: $teamSimulation,
            key: 'crude_hedge_coverage',
            sourceWeek: $this->isSevenWeekVariant($teamSimulation) ? '4' : '5',
            sourceEntity: 'consequence_link.week5_hedge_coverage',
            definitionKey: 'week5_hedge_coverage',
            missingReason: 'Week 5 hedge coverage consequence is missing.',
        );
    }

    private function batonRougeCondition(TeamSimulation $teamSimulation): Week10HistoricalDependency
    {
        $dependency = $this->consequenceDependency(
            teamSimulation: $teamSimulation,
            key: 'br_reported_margin_strong',
            sourceWeek: '4',
            sourceEntity: 'consequence_link.week4_tp_delacroix_cover',
            definitionKey: 'week4_tp_delacroix_cover',
            missingReason: 'Week 4 Delacroix cover consequence is missing.',
        );

        if ($dependency->isAvailable()) {
            return Week10HistoricalDependency::available(
                key: $dependency->key,
                sourceWeek: $dependency->sourceWeek,
                sourceEntity: $dependency->sourceEntity,
                sourceValue: ((string) $dependency->sourceValue) === '1' ? 'true' : 'false',
                sourceId: $dependency->sourceId,
                sourceVersion: $dependency->sourceVersion,
            );
        }

        return $dependency;
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
        return $this->consequenceDependency(
            teamSimulation: $teamSimulation,
            key: 'cash_cushion_musd',
            sourceWeek: '8',
            sourceEntity: 'consequence_link.week8_cash_cushion_musd',
            definitionKey: 'week8_cash_cushion_musd',
            missingReason: 'Week 8 cash cushion consequence is missing.',
        );
    }

    private function isSevenWeekVariant(TeamSimulation $teamSimulation): bool
    {
        $teamSimulation->loadMissing('sectionSimulation.version.variant');

        return (int) $teamSimulation->sectionSimulation->version->variant->duration_weeks === 7;
    }

    private function consequenceDependency(
        TeamSimulation $teamSimulation,
        string $key,
        string $sourceWeek,
        string $sourceEntity,
        string $definitionKey,
        string $missingReason,
    ): Week10HistoricalDependency {
        $link = $this->derivedConstraints->latestConsequenceValue($teamSimulation, $definitionKey);

        if (! $link instanceof ConsequenceLink) {
            return Week10HistoricalDependency::unresolved($key, $sourceWeek, $sourceEntity, $missingReason);
        }

        $metadata = $link->getAttribute('metadata');
        $value = is_array($metadata) ? ($metadata['target_value'] ?? null) : null;

        if ($value === null || $value === '') {
            return Week10HistoricalDependency::unresolved($key, $sourceWeek, $sourceEntity, "Consequence [{$definitionKey}] does not expose a target value.");
        }

        return Week10HistoricalDependency::available(
            key: $key,
            sourceWeek: $sourceWeek,
            sourceEntity: $sourceEntity,
            sourceValue: (string) $value,
            sourceId: (string) $link->id,
            sourceVersion: (string) $link->definition_version,
        );
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
}
