<?php

namespace App\Halden\OperatingModel;

/**
 * The published team score: seven measures, weights 30/15/15/10/10/10/10, each scaled 0-100
 * from the worst to the best team in the section, with a smallest difference that counts
 * (decision S1, approved 9 October 2026).
 */
final class Scoring
{
    /** @var array<string, array{0: float, 1: string}> */
    public const WEIGHTS = [
        'profit_per_barrel' => [0.30, 'higher'],
        'roace_pct' => [0.15, 'higher'],
        'free_cash_flow' => [0.15, 'higher'],
        'refining_vs_industry' => [0.10, 'higher'],
        'shop_profit_per_station_k' => [0.10, 'higher'],
        'debt_to_earnings' => [0.10, 'lower'],
        'plant_condition' => [0.10, 'higher'],
    ];

    /** @var array<string, float> */
    public const MATERIALITY = [
        'profit_per_barrel' => 0.50,
        'roace_pct' => 0.25,
        'free_cash_flow' => 50.0,
        'refining_vs_industry' => 0.25,
        'shop_profit_per_station_k' => 0.50,
        'debt_to_earnings' => 0.05,
        'plant_condition' => 2.0,
    ];

    /**
     * @param  array<string|int, array<string, float>>  $kpisByTeam
     * @return array<string|int, float>
     */
    public static function composite(array $kpisByTeam): array
    {
        if ($kpisByTeam === []) {
            return [];
        }
        $scores = array_fill_keys(array_keys($kpisByTeam), 0.0);
        foreach (self::WEIGHTS as $k => [$w, $direction]) {
            $vals = [];
            $lo = INF;
            $hi = -INF;
            foreach ($kpisByTeam as $team => $kpi) {
                $vals[$team] = $kpi[$k];
                $lo = min($lo, $kpi[$k]);
                $hi = max($hi, $kpi[$k]);
            }
            $band = self::MATERIALITY[$k];
            if ($hi - $lo < $band) {
                $mid = ($hi + $lo) / 2;
                $lo = $mid - $band / 2;
                $hi = $mid + $band / 2;
            }
            foreach ($vals as $team => $v) {
                if (abs($hi - $lo) <= 1e-9) {
                    $s = 50.0;
                } else {
                    $s = 100 * ($v - $lo) / ($hi - $lo);
                    if ($direction === 'lower') {
                        $s = 100 - $s;
                    }
                }
                $scores[$team] += $w * $s;
            }
        }

        return $scores;
    }

    /**
     * Ties share a rank.
     *
     * @param  array<string|int, float>  $scores
     * @return array<string|int, int>
     */
    public static function rank(array $scores): array
    {
        $ranks = [];
        foreach ($scores as $team => $s) {
            $better = 0;
            foreach ($scores as $other) {
                if ($other > $s + 1e-9) {
                    $better++;
                }
            }
            $ranks[$team] = 1 + $better;
        }

        return $ranks;
    }
}
