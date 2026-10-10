<?php

namespace App\Halden\Admin;

use App\Halden\Game\QuarterView;
use App\Models\Quarter;
use App\Models\Section;
use App\Models\TeamQuarter;
use Illuminate\Support\Collection;

/**
 * How a class compares with every other class that has run the same company quarter: the median and best of each
 * score measure and EBITDA, this class against all classes. Other classes are counted, never named. Class-wide draws
 * (the OPEC+ outcome, the breakdown, the world) differ by class, so the page says the comparison is rough.
 */
final class Benchmarks
{
    /**
     * @return list<array{quarter: string, number: int, teams: int, otherTeams: int, otherClasses: int, measures: list<array{key: string, name: string, unit: string, here: float|null, all: float|null, best: float|null, hereBest: float|null}>}>
     */
    public static function rows(Section $section): array
    {
        $out = [];
        foreach ($section->quarters()->where('status', Quarter::PUBLISHED)->get() as $quarter) {
            $here = TeamQuarter::query()->where('quarter_id', $quarter->id)->whereNotNull('results')->get();
            if ($here->isEmpty()) {
                continue;
            }
            $everyQuarterId = Quarter::query()->where('company_quarter', $quarter->company_quarter)->where('status', Quarter::PUBLISHED)->pluck('id', 'section_id');
            $all = TeamQuarter::query()->whereIn('quarter_id', $everyQuarterId->values())->whereNotNull('results')->get();
            $measures = [];
            foreach (QuarterView::KPI_NAMES + ['ebitda' => ['EBITDA', '', 'musd', '']] as $key => [$name, , $unit]) {
                $field = $key === 'ebitda' ? 'money.ebitda' : "kpi.$key";
                $lower = $key === 'debt_to_earnings';
                $hereVals = self::values($here, $field);
                $allVals = self::values($all, $field);
                $measures[] = [
                    'key' => $key, 'name' => $name, 'unit' => $unit,
                    'here' => self::median($hereVals), 'all' => self::median($allVals),
                    'hereBest' => $hereVals === [] ? null : ($lower ? min($hereVals) : max($hereVals)),
                    'best' => $allVals === [] ? null : ($lower ? min($allVals) : max($allVals)),
                ];
            }
            $out[] = [
                'quarter' => $quarter->label(), 'number' => $quarter->number,
                'teams' => $here->count(), 'otherTeams' => $all->count() - $here->count(), 'otherClasses' => $everyQuarterId->count() - 1,
                'measures' => $measures,
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, TeamQuarter>  $tqs
     * @return list<float>
     */
    private static function values($tqs, string $field): array
    {
        $out = [];
        foreach ($tqs as $tq) {
            $out[] = (float) ($tq->results[$field] ?? 0);
        }

        return $out;
    }

    /** @param  list<float>  $values */
    private static function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $n = count($values);

        return $n % 2 === 1 ? $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
    }
}
