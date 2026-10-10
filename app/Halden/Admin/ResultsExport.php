<?php

namespace App\Halden\Admin;

use App\Halden\Game\QuarterView;
use App\Models\Quarter;
use App\Models\Section;
use App\Models\Team;
use App\Models\TeamQuarter;
use Carbon\CarbonImmutable;

/**
 * A class's results as one CSV for grading in a spreadsheet: a row per team per quarter that has been run (or
 * written up), with the score and rank, the seven measures, the memo, the instructor's feedback and writing score,
 * and the board meeting's reasoning and verdict where there was one.
 */
final class ResultsExport
{
    /** Units as a spreadsheet wants them: plain numbers, with the unit in the column name. */
    private const UNITS = ['usd2' => '$/bbl', 'pct' => '%', 'musd' => '$M', 'kusd' => '$k', 'x' => 'x', 'pts' => 'of 100'];

    /** @return list<string> */
    public static function headings(): array
    {
        $cols = ['Quarter', 'Company quarter', 'Week', 'Team', 'Members', 'Score', 'Rank'];
        foreach (QuarterView::KPI_NAMES as [$name, $weight, $unit]) {
            $cols[] = "$name ($weight, ".self::UNITS[$unit].')';
        }

        return [...$cols, 'EBITDA ($M)', 'Advisor answers', 'Memo words', 'Memo', 'Memo saved (Eastern)', 'Ready (Eastern)',
            'Writing score', 'Feedback', 'Feedback published (Eastern)', 'Board reasoning', 'Board verdict'];
    }

    /** @return list<list<string|int|float|null>> */
    public static function rows(Section $section): array
    {
        $teams = $section->teams()->with('members.user')->orderBy('name')->get();
        $members = $teams->mapWithKeys(fn (Team $t) => [$t->id => $t->members->map(fn ($m) => $m->user->name)->sort()->implode('; ')]);
        $rows = [];
        foreach ($section->quarters()->get() as $quarter) {
            if ($quarter->status === Quarter::UPCOMING) {
                continue;
            }
            $tqs = TeamQuarter::query()->where('quarter_id', $quarter->id)->get()->keyBy('team_id');
            foreach ($teams as $team) {
                /** @var TeamQuarter|null $tq */
                $tq = $tqs[$team->id] ?? null;
                if ($tq === null) {
                    continue;
                }
                $r = $tq->results ?? [];
                $row = [$quarter->number, $quarter->label(), $quarter->week(), $team->name, $members[$team->id] ?? '',
                    $tq->score === null ? null : round((float) $tq->score, 1), $tq->rank];
                foreach (array_keys(QuarterView::KPI_NAMES) as $k) {
                    $row[] = isset($r["kpi.$k"]) ? round((float) $r["kpi.$k"], 2) : null;
                }
                $memo = trim(strip_tags((string) $tq->memo));
                $rows[] = [...$row,
                    isset($r['money.ebitda']) ? round((float) $r['money.ebitda'], 1) : null,
                    (int) ($r['advisor.answers'] ?? 0),
                    $memo === '' ? 0 : str_word_count($memo),
                    $memo,
                    self::when($tq->memo_saved_at), self::when($tq->ready_at),
                    $tq->writing_score,
                    $tq->feedback_published_at === null ? '' : trim((string) $tq->feedback),
                    self::when($tq->feedback_published_at),
                    $tq->reasoning,
                    $tq->verdict_published_at === null ? null : $tq->verdict,
                ];
            }
        }

        return $rows;
    }

    /** The whole file, with a byte-order mark so Excel reads the accents. */
    public static function csv(Section $section): string
    {
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return '';
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::headings(), escape: '');
        foreach (self::rows($section) as $row) {
            fputcsv($out, $row, escape: '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    public static function filename(Section $section): string
    {
        return preg_replace('/[^A-Za-z0-9]+/', '-', $section->name).'-results.csv';
    }

    private static function when(?\DateTimeInterface $at): ?string
    {
        return $at === null ? null : CarbonImmutable::instance($at)->setTimezone('America/New_York')->format('Y-m-d H:i');
    }
}
