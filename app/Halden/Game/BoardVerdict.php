<?php

namespace App\Halden\Game;

use App\Models\Quarter;
use App\Models\Team;
use App\Models\TeamQuarter;

/**
 * The board's verdict at the end of the course: four endings from the four-tier assessment. The instructor decides
 * whether the team's reasoning was strong (from the defense and the memos); the fourteen-quarter scorecard decides
 * whether its outcomes were strong (its average score against the class median). Tier 1 keeps the job and widens it;
 * strong outcomes with weak reasoning keep it with conditions; strong reasoning with weak outcomes splits the job;
 * weak and weak sells the European business over the EVP's head.
 */
final class BoardVerdict
{
    public const ENDINGS = ['widen', 'conditions', 'split', 'sold'];

    /** The team's average score over every published quarter, and whether that puts it in the top half of the class. */
    /** @return array{average: float|null, median: float|null, strong: bool|null} */
    public function outcomes(Team $team, Quarter $quarter): array
    {
        $averages = [];
        foreach ($quarter->section->teams()->get() as $other) {
            $scores = TeamQuarter::query()->where('team_id', $other->id)
                ->whereHas('quarter', fn ($q) => $q->where('status', Quarter::PUBLISHED)->where('number', '<=', $quarter->number))
                ->whereNotNull('score')->pluck('score');
            if ($scores->isNotEmpty()) {
                $averages[$other->id] = (float) $scores->avg();
            }
        }
        if (! isset($averages[$team->id])) {
            return ['average' => null, 'median' => null, 'strong' => null];
        }
        $sorted = array_values($averages);
        sort($sorted);
        $n = count($sorted);
        $median = $n % 2 === 1 ? $sorted[intdiv($n, 2)] : ($sorted[$n / 2 - 1] + $sorted[$n / 2]) / 2;

        return ['average' => $averages[$team->id], 'median' => $median, 'strong' => $averages[$team->id] >= $median - 1e-9];
    }

    /**
     * Where the team's course average stands in the class: 1 is best.
     *
     * @return array{rank: int, teams: int}
     */
    public function courseRank(Team $team, Quarter $quarter): array
    {
        $averages = [];
        foreach ($quarter->section->teams()->get() as $other) {
            $scores = TeamQuarter::query()->where('team_id', $other->id)
                ->whereHas('quarter', fn ($q) => $q->where('status', Quarter::PUBLISHED)->where('number', '<=', $quarter->number))
                ->whereNotNull('score')->pluck('score');
            $averages[$other->id] = $scores->isNotEmpty() ? (float) $scores->avg() : 0.0;
        }
        $mine = $averages[$team->id] ?? 0.0;
        $rank = 1 + count(array_filter($averages, fn (float $a) => $a > $mine + 1e-9));

        return ['rank' => $rank, 'teams' => max(1, count($averages))];
    }

    /** The ending the four-tier table gives, before the instructor's say. */
    public function suggested(?string $reasoning, ?bool $strongOutcomes): ?string
    {
        if ($reasoning === null || $strongOutcomes === null) {
            return null;
        }
        if ($reasoning === 'strong') {
            return $strongOutcomes ? 'widen' : 'split';
        }

        return $strongOutcomes ? 'conditions' : 'sold';
    }

    public function tierLabel(?string $reasoning, ?bool $strongOutcomes): ?string
    {
        if ($reasoning === null || $strongOutcomes === null) {
            return null;
        }

        return ($reasoning === 'strong' ? 'Strong reasoning' : 'Weak reasoning').', '.($strongOutcomes ? 'strong outcomes' : 'weaker outcomes');
    }
}
