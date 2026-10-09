<?php

namespace App\Halden\Game;

use App\Halden\OperatingModel\CompanyState;
use App\Halden\OperatingModel\OperatingModel;
use App\Halden\OperatingModel\Scoring;
use App\Models\AdvisorMessage;
use App\Models\Quarter;
use App\Models\Team;
use App\Models\TeamQuarter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Opens, closes and publishes quarters. Closing a quarter runs every team's company through the
 * operating model, then scores and ranks the teams against each other.
 */
final class QuarterRunner
{
    public function __construct(private readonly OperatingModel $model, private readonly DecisionBook $book) {}

    public function open(Quarter $quarter): void
    {
        $prev = $quarter->previous();
        if ($quarter->status !== Quarter::UPCOMING) {
            throw new RuntimeException('This quarter has already been opened.');
        }
        if ($prev !== null && $prev->status !== Quarter::PUBLISHED) {
            throw new RuntimeException("Publish {$prev->label()} results before opening {$quarter->label()}.");
        }
        $this->marketFor($quarter);
        $quarter->update(['status' => Quarter::OPEN, 'opened_at' => now()]);
    }

    public function startState(Team $team, Quarter $quarter): CompanyState
    {
        $prev = $quarter->previous();
        if ($prev !== null) {
            $tq = TeamQuarter::query()->where('team_id', $team->id)->where('quarter_id', $prev->id)->first();
            if ($tq === null || ! is_array($tq->state_after)) {
                throw new RuntimeException("{$team->name} has no results for {$prev->label()}.");
            }

            return CompanyState::fromArray($tq->state_after);
        }

        return $this->model->runHistory()[0];
    }

    public function close(Quarter $quarter): void
    {
        if ($quarter->status !== Quarter::OPEN) {
            throw new RuntimeException('Only an open quarter can be closed.');
        }
        $market = $this->marketFor($quarter);

        DB::transaction(function () use ($quarter, $market): void {
            $results = [];
            $effective = [];
            $bridges = [];
            $answers = [];
            foreach ($quarter->section->teams()->orderBy('id')->get() as $team) {
                $effective[$team->id] = $this->book->effective($team, $quarter);
                $answers[$team->id] = AdvisorMessage::billable($team->id, $quarter->id);
                $start = $this->startState($team, $quarter);
                $results[$team->id] = $this->model->step(clone $start, $this->book->toEngine($effective[$team->id], $answers[$team->id]), $market);
                $bridges[$team->id] = $this->bridge($team, $quarter, $start, $effective[$team->id], $market, $results[$team->id]->money['ebitda']);
            }
            $scores = Scoring::composite(array_map(fn ($r) => $r->kpi, $results));
            $ranks = Scoring::rank($scores);

            foreach ($results as $teamId => $result) {
                TeamQuarter::query()->updateOrCreate(
                    ['team_id' => $teamId, 'quarter_id' => $quarter->id],
                    [
                        'effective_decisions' => $effective[$teamId],
                        'results' => $result->metrics() + $bridges[$teamId] + ['ops.rot_status' => $result->ops['rot_status'], 'advisor.answers' => $answers[$teamId]],
                        'state_after' => $result->state->toArray(),
                        'score' => $scores[$teamId],
                        'rank' => $ranks[$teamId],
                    ],
                );
            }
            $quarter->update(['status' => Quarter::CLOSED, 'closed_at' => now()]);
        });
    }

    public function publish(Quarter $quarter): void
    {
        if ($quarter->status !== Quarter::CLOSED) {
            throw new RuntimeException('Close the quarter before publishing results.');
        }
        $quarter->update(['status' => Quarter::PUBLISHED, 'published_at' => now()]);
    }

    /**
     * Splits the change in EBITDA from last quarter into three exact parts by rerunning this quarter:
     * prices (last quarter's decisions at this quarter's prices vs last quarter's prices),
     * decisions (this quarter's decisions vs last quarter's, at this quarter's prices), and
     * carried over (everything earlier quarters left behind: output decline, earlier rigs, wear).
     *
     * @param  array<string, string|float|int|null>  $effective
     * @param  array<string, mixed>  $market
     * @return array<string, float>
     */
    private function bridge(Team $team, Quarter $quarter, CompanyState $start, array $effective, array $market, float $actual): array
    {
        $prevQuarter = $quarter->previous();
        $prevDecisions = $this->book->previousEffective($team, $quarter);
        $prevAnswers = 0;
        if ($prevQuarter === null) {
            $history = $this->model->runHistory()[1];
            $last = end($history);
            if ($last === false) {
                throw new RuntimeException('The 2026 history is empty.');
            }
            $prevMarket = $last['quarter'];
            $prevEbitda = $last['result']->money['ebitda'];
        } else {
            $prevMarket = $this->marketFor($prevQuarter);
            $tq = TeamQuarter::query()->where('team_id', $team->id)->where('quarter_id', $prevQuarter->id)->firstOrFail();
            $prevEbitda = (float) ($tq->results['money.ebitda'] ?? 0.0);
            $prevAnswers = (int) ($tq->results['advisor.answers'] ?? 0);
        }
        $a = $this->model->step(clone $start, $this->book->toEngine($prevDecisions, $prevAnswers), $prevMarket)->money['ebitda'];
        $b = $this->model->step(clone $start, $this->book->toEngine($prevDecisions, $prevAnswers), $market)->money['ebitda'];

        return [
            'bridge.previous' => $prevEbitda,
            'bridge.prices' => $b - $a,
            'bridge.decisions' => $actual - $b,
            'bridge.carried_over' => $a - $prevEbitda,
        ];
    }

    public function hasMarket(Quarter $quarter): bool
    {
        return in_array($quarter->company_quarter, array_column($this->model->data->market, 'quarter'), true);
    }

    /** @return array<string, mixed> */
    private function marketFor(Quarter $quarter): array
    {
        try {
            return $this->model->data->quarter($quarter->company_quarter);
        } catch (RuntimeException) {
            throw new RuntimeException("The economics for {$quarter->label()} aren't built yet.");
        }
    }
}
