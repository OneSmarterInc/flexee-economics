<?php

namespace App\Halden\Game;

use App\Halden\OperatingModel\CompanyState;
use App\Halden\OperatingModel\OperatingModel;
use App\Halden\OperatingModel\Scoring;
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
            foreach ($quarter->section->teams()->orderBy('id')->get() as $team) {
                $effective[$team->id] = $this->book->effective($team, $quarter);
                $results[$team->id] = $this->model->step(
                    $this->startState($team, $quarter),
                    $this->book->toEngine($effective[$team->id]),
                    $market,
                );
            }
            $scores = Scoring::composite(array_map(fn ($r) => $r->kpi, $results));
            $ranks = Scoring::rank($scores);

            foreach ($results as $teamId => $result) {
                TeamQuarter::query()->updateOrCreate(
                    ['team_id' => $teamId, 'quarter_id' => $quarter->id],
                    [
                        'effective_decisions' => $effective[$teamId],
                        'results' => $result->metrics() + ['ops.rot_status' => $result->ops['rot_status']],
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
