<?php

namespace App\Halden\Game;

use App\Halden\Content\ContentPack;
use App\Halden\OperatingModel\OperatingModel;
use App\Models\Quarter;
use App\Models\Team;
use App\Models\TeamQuarter;

/**
 * What the board has in front of it in the last quarter: the team's sentence from day one, the world its five-year
 * plan is valued in, and its record quarter by quarter. Read by the student's board page and by the faculty drafts.
 */
final class BoardRecord
{
    public function __construct(
        private readonly ContentPack $content,
        private readonly DecisionBook $book,
        private readonly OperatingModel $model,
        private readonly QuarterRunner $runner,
    ) {}

    public function sentence(Team $team): ?string
    {
        return $team->strategy_become ? "Halden should become a company that {$team->strategy_become} by {$team->strategy_by}." : null;
    }

    /**
     * The drawn world as labels, what the team's plan is worth in it, and the plan itself.
     *
     * @return array{carbon: string, demand: string, carbon_label: string, demand_label: string, value: float, chosen: list<string>}
     */
    public function world(Team $team, Quarter $quarter): array
    {
        $world = $this->runner->worldOf($quarter);
        // The plan the team placed: what the quarter before is running with (its working set while it is still open,
        // which is the case in the last week of a 7-week course, where the board quarter is the week's second half).
        $prev = $quarter->previous();
        $chosen = $this->book->portfolioChosen($prev === null ? $this->book->historyDefaults() : $this->book->effective($team, $prev));
        $lower = fn (string $s): string => strtolower(substr($s, 0, 1)).substr($s, 1);

        return [
            'carbon' => $world['carbon'], 'demand' => $world['demand'],
            'carbon_label' => $lower($this->model->data->scenarios['carbon'][$world['carbon']]['label']),
            'demand_label' => $lower($this->model->data->scenarios['demand'][$world['demand']]['label']),
            'value' => $this->model->portfolioValueInWorld($chosen, $world['carbon'], $world['demand']),
            'chosen' => $chosen,
        ];
    }

    /**
     * Every published quarter before this one: its big question, what Halden earned, the score and rank, and the memo in full.
     *
     * @return list<array{number: int, label: string, question: string, ebitda: float, score: float|null, rank: int|null, memo: string}>
     */
    public function record(Team $team, Quarter $quarter): array
    {
        $record = [];
        foreach (TeamQuarter::query()->where('team_id', $team->id)->whereHas('quarter', fn ($q) => $q->where('number', '<', $quarter->number)->where('status', Quarter::PUBLISHED))
            ->with('quarter')->get()->sortBy(fn (TeamQuarter $x) => $x->quarter->number) as $past) {
            $pc = $this->content->hasQuarter($past->quarter->number) ? $this->content->quarter($past->quarter->number) : null;
            $record[] = ['number' => $past->quarter->number, 'label' => $past->quarter->label(), 'question' => (string) ($pc['briefing']['question'] ?? ''),
                'ebitda' => (float) ($past->results['money.ebitda'] ?? 0), 'score' => $past->score === null ? null : (float) $past->score, 'rank' => $past->rank, 'memo' => (string) $past->memo];
        }

        return $record;
    }

    /**
     * The record as lines for a reader (faculty or an AI drafting for faculty): no scores or ranks, memos whole.
     *
     * @return list<string>
     */
    public function recordLines(Team $team, Quarter $quarter): array
    {
        $lines = [];
        foreach ($this->record($team, $quarter) as $r) {
            $memo = trim($r['memo']) === '' ? '(No memo that quarter.)' : $r['memo'];
            $lines[] = "{$r['label']} · {$r['question']} Halden earned ".ContentPack::money($r['ebitda'])." before interest, tax and depreciation. The memo: $memo";
        }

        return $lines;
    }
}
