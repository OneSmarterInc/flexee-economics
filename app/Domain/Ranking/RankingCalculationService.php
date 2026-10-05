<?php

namespace App\Domain\Ranking;

use App\Domain\Scoring\KpiDefinitionCatalog;
use App\Enums\KpiSnapshotStatus;
use App\Enums\RankingScope;
use App\Enums\RankingSnapshotStatus;
use App\Models\KpiDefinition;
use App\Models\KpiSnapshot;
use App\Models\RankingSnapshot;
use App\Models\SectionSimulationWeek;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RankingCalculationService
{
    public const RANKING_VERSION = 'ranking_v1';

    /**
     * @return list<RankingSnapshot>
     */
    public function calculateForSectionWeek(
        SectionSimulationWeek $runtimeWeek,
        RankingScope $scope = RankingScope::WithinSection,
        string $kpiDefinitionVersion = KpiDefinitionCatalog::HALDEN_KPI_VERSION,
    ): array {
        if ($scope !== RankingScope::WithinSection) {
            throw new InvalidArgumentException('Cross-section ranking scope is reserved for a future batch.');
        }

        $catalog = app(KpiDefinitionCatalog::class);
        if ($kpiDefinitionVersion === KpiDefinitionCatalog::HALDEN_KPI_VERSION) {
            $catalog->publishHaldenV1();
        }

        $definitions = KpiDefinition::query()
            ->where('version', $kpiDefinitionVersion)
            ->orderBy('id')
            ->get();

        $catalog->assertWeightsSumToOne($definitions);

        return DB::transaction(function () use ($runtimeWeek, $scope, $definitions, $kpiDefinitionVersion): array {
            $teamSimulations = TeamSimulation::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_id', $runtimeWeek->section_simulation_id)
                ->orderBy('id')
                ->get();
            $latestSnapshots = $this->latestSnapshotsByTeamAndDefinition($runtimeWeek, $definitions);
            $normalization = $this->normalizationByDefinition($definitions, $latestSnapshots);
            $teamResults = [];

            foreach ($teamSimulations as $teamSimulation) {
                $teamResults[] = $this->calculateTeamResult($teamSimulation, $definitions, $latestSnapshots, $normalization);
            }

            $teamResults = $this->assignRanks($teamResults);

            $snapshots = [];
            foreach ($teamResults as $result) {
                $snapshots[] = RankingSnapshot::query()->create([
                    'tenant_id' => $runtimeWeek->tenant_id,
                    'section_simulation_id' => $runtimeWeek->section_simulation_id,
                    'section_simulation_week_id' => $runtimeWeek->id,
                    'team_simulation_id' => $result->teamSimulation->id,
                    'team_id' => $result->teamSimulation->team_id,
                    'scope' => $scope->value,
                    'status' => $result->status->value,
                    'composite_score' => $result->compositeScore instanceof BigDecimal ? $this->scoreDecimal($result->compositeScore) : null,
                    'rank' => $result->rank,
                    'ranking_version' => self::RANKING_VERSION,
                    'input_snapshot' => [
                        'kpi_definition_version' => $kpiDefinitionVersion,
                        'ranking_version' => self::RANKING_VERSION,
                        'required_kpis' => $definitions->map(fn (KpiDefinition $definition): array => [
                            'id' => $definition->id,
                            'key' => $definition->key,
                            'weight' => $definition->weight,
                            'calculation_source' => $definition->calculation_source,
                        ])->values()->all(),
                        'kpi_snapshots' => $result->inputSnapshots,
                        'normalization' => $normalization,
                    ],
                    'incomplete_reason' => $result->incompleteReason,
                    'calculated_at' => Carbon::now(),
                ]);
            }

            return $snapshots;
        });
    }

    public function assertCanView(User $actor, RankingSnapshot $snapshot): void
    {
        if ($actor->tenant_id !== $snapshot->tenant_id) {
            throw new InvalidArgumentException('Actor cannot access ranking snapshots for another tenant.');
        }

        if ($actor->isAdministrator()) {
            return;
        }

        if ($actor->isFaculty()) {
            $assigned = $actor->facultySections()
                ->wherePivot('tenant_id', $snapshot->tenant_id)
                ->whereHas('sectionSimulations', fn ($query) => $query->whereKey($snapshot->section_simulation_id))
                ->exists();

            if ($assigned) {
                return;
            }
        }

        if ($actor->isStudent()) {
            $member = TeamMember::query()
                ->where('tenant_id', $snapshot->tenant_id)
                ->where('team_id', $snapshot->team_id)
                ->where('user_id', $actor->id)
                ->exists();

            if ($member) {
                return;
            }
        }

        throw new InvalidArgumentException('Actor cannot access this team ranking snapshot.');
    }

    /**
     * @param  Collection<int, KpiDefinition>  $definitions
     * @return array<int, array<int, KpiSnapshot>>
     */
    private function latestSnapshotsByTeamAndDefinition(SectionSimulationWeek $runtimeWeek, Collection $definitions): array
    {
        $definitionIds = $definitions->pluck('id')->all();
        $latest = [];

        KpiSnapshot::query()
            ->with('definition')
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->whereIn('kpi_definition_id', $definitionIds)
            ->orderBy('id')
            ->get()
            ->each(function (KpiSnapshot $snapshot) use (&$latest): void {
                $latest[$snapshot->team_simulation_id][$snapshot->kpi_definition_id] = $snapshot;
            });

        return $latest;
    }

    /**
     * @param  Collection<int, KpiDefinition>  $definitions
     * @param  array<int, array<int, KpiSnapshot>>  $snapshots
     * @param  array<int, array{kpi_key: string, direction: string, min?: string|null, max?: string|null, scores: array<int, string>}>  $normalization
     */
    private function calculateTeamResult(TeamSimulation $teamSimulation, Collection $definitions, array $snapshots, array $normalization): RankingTeamResult
    {
        $score = BigDecimal::zero();
        $inputSnapshots = [];
        $unavailable = [];

        foreach ($definitions as $definition) {
            $snapshot = $snapshots[$teamSimulation->id][$definition->id] ?? null;

            if (! $snapshot instanceof KpiSnapshot) {
                $unavailable[] = "{$definition->key}: missing KPI snapshot";

                continue;
            }

            $inputSnapshots[] = [
                'id' => $snapshot->id,
                'kpi_key' => $definition->key,
                'status' => $snapshot->statusEnum()->value,
                'value' => $snapshot->value,
                'weight' => $definition->weight,
                'calculation_version' => $snapshot->calculation_version,
                'normalized_score' => $normalization[$definition->id]['scores'][$teamSimulation->id] ?? null,
            ];

            if ($snapshot->statusEnum() !== KpiSnapshotStatus::Available || $snapshot->value === null) {
                $unavailable[] = "{$definition->key}: {$snapshot->unavailable_reason}";

                continue;
            }

            $normalizedScore = $normalization[$definition->id]['scores'][$teamSimulation->id] ?? null;
            if ($normalizedScore === null) {
                $unavailable[] = "{$definition->key}: normalization unavailable";

                continue;
            }

            $score = $score->plus(BigDecimal::of($normalizedScore)->multipliedBy((string) $definition->weight));
        }

        if ($unavailable !== []) {
            return new RankingTeamResult(
                teamSimulation: $teamSimulation,
                status: RankingSnapshotStatus::Incomplete,
                compositeScore: null,
                rank: null,
                inputSnapshots: $inputSnapshots,
                incompleteReason: implode('; ', $unavailable),
            );
        }

        return new RankingTeamResult(
            teamSimulation: $teamSimulation,
            status: RankingSnapshotStatus::Complete,
            compositeScore: $score,
            rank: null,
            inputSnapshots: $inputSnapshots,
            incompleteReason: null,
        );
    }

    /**
     * @param  Collection<int, KpiDefinition>  $definitions
     * @param  array<int, array<int, KpiSnapshot>>  $snapshots
     * @return array<int, array{kpi_key: string, direction: string, min?: string|null, max?: string|null, scores: array<int, string>}>
     */
    private function normalizationByDefinition(Collection $definitions, array $snapshots): array
    {
        $normalization = [];

        foreach ($definitions as $definition) {
            $values = [];
            foreach ($snapshots as $teamSimulationId => $teamSnapshots) {
                $snapshot = $teamSnapshots[$definition->id] ?? null;
                if ($snapshot instanceof KpiSnapshot && $snapshot->statusEnum() === KpiSnapshotStatus::Available && $snapshot->value !== null) {
                    $values[(int) $teamSimulationId] = BigDecimal::of((string) $snapshot->value);
                }
            }

            if ($values === []) {
                $normalization[$definition->id] = [
                    'kpi_key' => $definition->key,
                    'direction' => $this->direction($definition),
                    'scores' => [],
                ];

                continue;
            }

            $firstTeamSimulationId = array_key_first($values);
            $min = $values[$firstTeamSimulationId];
            $max = $values[$firstTeamSimulationId];
            foreach ($values as $value) {
                $min = $min->isLessThanOrEqualTo($value) ? $min : $value;
                $max = $max->isGreaterThanOrEqualTo($value) ? $max : $value;
            }

            $scores = [];
            $direction = $this->direction($definition);
            foreach ($values as $teamSimulationId => $value) {
                if ($max->isEqualTo($min)) {
                    $scores[$teamSimulationId] = '50.000000';

                    continue;
                }

                $range = $max->minus($min);
                $numerator = $direction === 'lower'
                    ? $max->minus($value)
                    : $value->minus($min);

                $scores[$teamSimulationId] = (string) $numerator
                    ->dividedBy($range, 8, RoundingMode::HalfUp)
                    ->multipliedBy('100')
                    ->toScale(6, RoundingMode::HalfUp);
            }

            $normalization[$definition->id] = [
                'kpi_key' => $definition->key,
                'direction' => $direction,
                'min' => (string) $min,
                'max' => (string) $max,
                'scores' => $scores,
            ];
        }

        return $normalization;
    }

    private function direction(KpiDefinition $definition): string
    {
        $direction = data_get($definition->getAttribute('metadata'), 'direction');

        return $direction === 'lower' ? 'lower' : 'higher';
    }

    /**
     * @param  list<RankingTeamResult>  $teamResults
     * @return list<RankingTeamResult>
     */
    private function assignRanks(array $teamResults): array
    {
        $completeIndexes = [];
        foreach ($teamResults as $index => $result) {
            if ($result->status === RankingSnapshotStatus::Complete && $result->compositeScore instanceof BigDecimal) {
                $completeIndexes[] = $index;
            }
        }

        usort($completeIndexes, function (int $left, int $right) use ($teamResults): int {
            $leftScore = $teamResults[$left]->compositeScore;
            $rightScore = $teamResults[$right]->compositeScore;

            if (! $leftScore instanceof BigDecimal || ! $rightScore instanceof BigDecimal) {
                return 0;
            }

            $scoreComparison = $rightScore->compareTo($leftScore);

            return $scoreComparison !== 0
                ? $scoreComparison
                : $teamResults[$left]->teamSimulation->id <=> $teamResults[$right]->teamSimulation->id;
        });

        $lastScore = null;
        $lastRank = 0;
        foreach ($completeIndexes as $position => $index) {
            $score = $teamResults[$index]->compositeScore;
            $rank = $lastScore instanceof BigDecimal && $score instanceof BigDecimal && $score->isEqualTo($lastScore)
                ? $lastRank
                : $position + 1;

            $teamResults[$index] = $teamResults[$index]->withRank($rank);
            $lastRank = $rank;
            $lastScore = $score;
        }

        return array_values($teamResults);
    }

    private function scoreDecimal(BigDecimal $score): string
    {
        return (string) $score->toScale(6, RoundingMode::HalfUp);
    }
}
