<?php

namespace App\Halden\Game;

use App\Halden\OperatingModel\CompanyState;
use App\Halden\OperatingModel\OperatingModel;
use App\Halden\OperatingModel\Scoring;
use App\Models\AdvisorMessage;
use App\Models\Quarter;
use App\Models\Team;
use App\Models\TeamMember;
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
        if ($quarter->isRotationQuarter() && $quarter->seats_rotated_at === null) {
            $this->rotateSeats($quarter);
        }
        $quarter->update(['status' => Quarter::OPEN, 'opened_at' => now()]);
    }

    /**
     * Every student moves one seat along: EVP -> Oil fields -> Refineries -> Gas stations -> Trading & finance -> EVP.
     * A team with a seat missing keeps the gap; nobody is dropped.
     */
    public function rotateSeats(Quarter $quarter): void
    {
        $order = array_keys(TeamMember::SEATS);
        DB::transaction(function () use ($quarter, $order): void {
            foreach ($quarter->section->teams()->get() as $team) {
                foreach ($team->members()->get() as $member) {
                    $i = array_search($member->seat, $order, true);
                    if ($i === false) {
                        continue;
                    }
                    $member->update(['seat' => $order[($i + 1) % count($order)]]);
                }
            }
            $quarter->update(['seats_rotated_at' => now()]);
        });
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
        if ($this->model->data->quarter($quarter->company_quarter)['opec'] && $quarter->event_outcome === null) {
            $quarter->update(['event_outcome' => $this->drawOpecOutcome()]);
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
                $history = $this->history($team, $quarter);
                $results[$team->id] = $this->model->step(clone $start, $this->book->toEngine($effective[$team->id], $answers[$team->id], $history), $market);
                $bridges[$team->id] = $this->bridge($team, $quarter, $start, $effective[$team->id], $market, $results[$team->id]->money['ebitda'], $history);
            }
            $scores = Scoring::composite(array_map(fn ($r) => $r->kpi, $results));
            $ranks = Scoring::rank($scores);

            foreach ($results as $teamId => $result) {
                TeamQuarter::query()->updateOrCreate(
                    ['team_id' => $teamId, 'quarter_id' => $quarter->id],
                    [
                        'effective_decisions' => $effective[$teamId],
                        'results' => $result->metrics() + $bridges[$teamId] + ['ops.rot_status' => $result->ops['rot_status'], 'advisor.answers' => $answers[$teamId]]
                            + ['history.delacroix_cover' => (int) ($result->notes['br_run_resisted'] ?? 0) > 0 ? 1.0 : 0.0, 'history.sg_cut_by_partner' => isset($result->notes['sg_cut_by_partner']) ? 1.0 : 0.0],
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
     * @param  array{delacroix_cover?: bool, straits_strained?: bool}  $carried  what the team carries from its own record
     * @return array<string, float>
     */
    private function bridge(Team $team, Quarter $quarter, CompanyState $start, array $effective, array $market, float $actual, array $carried = []): array
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
        $a = $this->model->step(clone $start, $this->book->toEngine($prevDecisions, $prevAnswers, $carried), $prevMarket)->money['ebitda'];
        $b = $this->model->step(clone $start, $this->book->toEngine($prevDecisions, $prevAnswers, $carried), $market)->money['ebitda'];

        return [
            'bridge.previous' => $prevEbitda,
            'bridge.prices' => $b - $a,
            'bridge.decisions' => $actual - $b,
            'bridge.carried_over' => $a - $prevEbitda,
        ];
    }

    /**
     * What a team carries from its own record into a quarter: whether Marcus has cover to resist a Baton Rouge run
     * cut (its Q4 2027 crude price) and whether Straits Pacific is strained (its last four Singapore asks).
     *
     * @return array{delacroix_cover: bool, straits_strained: bool}
     */
    public function history(Team $team, Quarter $quarter): array
    {
        $cover = false;
        $q4 = Quarter::query()->where('section_id', $quarter->section_id)->where('company_quarter', '2027Q4')->first();
        if ($q4 !== null && $q4->number < $quarter->number) {
            $tq = TeamQuarter::query()->where('team_id', $team->id)->where('quarter_id', $q4->id)->first();
            if ($tq !== null && is_array($tq->effective_decisions)) {
                $cover = $this->model->delacroixHasCover($this->book->toEngine($tq->effective_decisions), (float) $this->model->data->quarter('2027Q4')['wti']);
            }
        }
        $recent = [];
        foreach (TeamQuarter::query()->where('team_id', $team->id)->whereNotNull('effective_decisions')
            ->whereHas('quarter', fn ($q) => $q->where('number', '<', $quarter->number)->where('number', '>=', $quarter->number - 4))->get() as $tq) {
            $recent[] = $this->book->toEngine($tq->effective_decisions ?? []);
        }

        return ['delacroix_cover' => $cover, 'straits_strained' => $this->model->straitsIsStrained($recent)];
    }

    /** Draws the OPEC+ outcome from the stated chances (35% holds in full, 40% partly, 25% falls apart). */
    public function drawOpecOutcome(): string
    {
        $r = random_int(1, 1000) / 1000;
        $cum = 0.0;
        $last = 'partial';
        foreach ($this->model->data->opec as $key => $s) {
            $cum += $s['p'];
            $last = $key;
            if ($r <= $cum + 1e-9) {
                return $key;
            }
        }

        return $last;
    }

    /** Share of this class that went ahead with the Baton Rouge upgrade in Q2 2028 (Window 2's input). */
    public function classBrUpgradeShare(Quarter $quarter): ?float
    {
        return $this->classAverage($quarter, '2028Q2', fn (array $d): float => ($d['proj_br_upgrade'] ?? 'hold') === 'commit' ? 1.0 : 0.0);
    }

    public function hasMarket(Quarter $quarter): bool
    {
        return in_array($quarter->company_quarter, array_column($this->model->data->market, 'quarter'), true);
    }

    /**
     * This quarter's prices for this class, including what the whole class did earlier (hidden until it lands).
     * Window 1: the class's Q3 2027 European run rates set the Q1 2028 European refining margin.
     * Window 3: the class's Q3 2028 price aggression sets the Q1 2029 Cordell shop margin.
     * OPEC+ (Q4 2028): once the outcome is drawn at the close, WTI and the Gulf Coast margin move by it, and
     * Window 2 (the class's Q2 2028 Baton Rouge upgrades) lands on the same margin.
     *
     * @return array<string, mixed>
     */
    public function marketFor(Quarter $quarter): array
    {
        try {
            $m = $this->model->data->quarter($quarter->company_quarter);
        } catch (RuntimeException) {
            throw new RuntimeException("The economics for {$quarter->label()} aren't built yet.");
        }
        if ($quarter->company_quarter === '2028Q1') {
            $util = $this->classAverage($quarter, '2027Q3', fn (array $d): float => $this->model->europeanUtil($this->book->toEngine($d)));
            if ($util !== null) {
                $m['nwe'] = $this->model->window1Nwe($util);
            }
        }
        if ((bool) $m['opec'] && $quarter->event_outcome !== null) {
            $m = $this->model->opecMarket($m, $quarter->event_outcome, $this->classBrUpgradeShare($quarter) ?? 0.5);
        }
        if (str_starts_with($quarter->company_quarter, '2029')) {   // Window 3 lands for all of 2029
            $aggression = $this->classAggression($quarter);
            if ($aggression !== null) {
                $m['cordell_nonfuel'] = $this->model->window3Nonfuel($aggression);
            }
        }

        return $m;
    }

    /**
     * Q2 2028 cost of capital and spending envelope for this class, set by its Q4 2027 crude-price choices.
     *
     * @return array{behaviour: string, rate: float, envelope: float}
     */
    public function capitalTerms(Quarter $quarter): array
    {
        $wti = (float) $this->model->data->quarter('2027Q4')['wti'];
        $avg = $this->classAverage($quarter, '2027Q4', fn (array $d): float => $this->model->crudePriceDiscipline($this->book->toEngine($d), $wti));

        return $this->model->capitalTerms($avg ?? 0.5);
    }

    /** How aggressively this class answered the rival's Q3 2028 price cut, 0 (nobody matched) to 1 (everyone matched everywhere). */
    public function classAggression(Quarter $quarter): ?float
    {
        return $this->classAverage($quarter, '2028Q3', fn (array $d): float => $this->model->priceAggression($this->book->toEngine($d)));
    }

    /**
     * Average over the class's teams of something about what ran in an earlier company quarter.
     *
     * @param  callable(array<string, string|float|int|null>): float  $measure
     */
    private function classAverage(Quarter $quarter, string $companyQuarter, callable $measure): ?float
    {
        $source = Quarter::query()->where('section_id', $quarter->section_id)->where('company_quarter', $companyQuarter)->first();
        if ($source === null) {
            return null;
        }
        $values = [];
        foreach (TeamQuarter::query()->where('quarter_id', $source->id)->whereNotNull('effective_decisions')->get() as $tq) {
            $values[] = $measure($tq->effective_decisions ?? []);
        }

        return $values === [] ? null : array_sum($values) / count($values);
    }
}
