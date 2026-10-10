<?php

namespace App\Halden\Game;

use App\Halden\Ai\AdvisorRoom;
use App\Halden\Ai\Carrying;
use App\Halden\Ai\FacultyDrafts;
use App\Halden\Ai\HelpDesk;
use App\Halden\Content\ContentPack;
use App\Halden\OperatingModel\ModelData;
use App\Halden\OperatingModel\OperatingModel;
use App\Models\Quarter;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamQuarter;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Builds everything one quarter screen shows for one team. Students and the faculty
 * "view as team" both use this, so they always see the same thing.
 */
final class QuarterView
{
    public const PAGE_TITLES = [
        'oil_fields' => 'Oil fields',
        'refineries' => 'Refineries',
        'gas_stations' => 'Gas stations',
        'trading_finance' => 'Trading & finance',
        'capital' => 'Big projects',
    ];

    public const PAGE_SEAT = [
        'oil_fields' => 'oil_fields',
        'refineries' => 'refineries',
        'gas_stations' => 'gas_stations',
        'trading_finance' => 'trading_finance',
        'capital' => 'evp',
    ];

    public function __construct(
        private readonly ContentPack $content,
        private readonly DecisionBook $book,
        private readonly OperatingModel $model,
        private readonly QuarterRunner $runner,
        private readonly AdvisorRoom $room,
        private readonly FacultyDrafts $drafts,
        private readonly Carrying $carrying,
        private readonly HelpDesk $help,
    ) {}

    /**
     * The Big projects page: this quarter's envelope and cost of capital, and what's already under way.
     *
     * @param  array<string, mixed>  $previous
     * @return array{envelope: float, rate: float, projects: list<array{key: string, label: string, outlay: float}>, committedBefore: list<string>, capacityMatchedBefore: bool}|null
     */
    private function capitalDesk(Quarter $quarter, array $previous): ?array
    {
        if (! in_array('capital', $this->book->openPages($quarter->number), true) || ! $this->runner->hasMarket($quarter)) {
            return null;
        }
        $terms = $this->runner->capitalTerms($quarter);
        $projects = [];
        $committed = [];
        foreach ($this->model->data->projects as $key => $p) {
            $projects[] = ['key' => $key, 'label' => $p['label'], 'outlay' => $p['outlay']];
            if (($previous["proj_$key"] ?? 'hold') === 'commit') {
                $committed[] = $key;
            }
        }

        return ['envelope' => $terms['envelope'], 'rate' => $terms['rate'], 'projects' => $projects, 'committedBefore' => $committed,
            'capacityMatchedBefore' => ($previous['capacity_response'] ?? 'hold') === 'match'];
    }

    /**
     * The Gas stations page once a rival has cut prices: the cut, and how much fuel each Cordell market sells.
     *
     * @return array{cut: float, clusters: list<array{key: string, label: string, gallons: float}>}|null
     */
    private function rivalDesk(Quarter $quarter): ?array
    {
        if (! $this->book->isUnlocked('resp_urban', $quarter->number) || ! $this->runner->hasMarket($quarter)) {
            return null;
        }
        $cut = (float) ($this->runner->marketFor($quarter)['rival_cut'] ?? 0);
        if ($cut <= 0) {
            return null;
        }
        $data = $this->model->data;
        $total = $data->c('cordell_sites') * $data->c('cordell_gal_per_site_qtr');

        return ['cut' => $cut, 'clusters' => array_map(fn (array $c) => ['key' => $c['key'], 'label' => $c['label'], 'gallons' => $total * $c['share']], $data->cordell)];
    }

    /**
     * The Trading & finance page in the OPEC+ quarter: how much crude each planning case buys ahead, at what price.
     *
     * @return array{days: array<string, float>, wti: float, carryRate: float}|null
     */
    private function opecDesk(Quarter $quarter): ?array
    {
        if (! $this->book->isUnlocked('opec_case', $quarter->number) || ! $this->runner->hasMarket($quarter)) {
            return null;
        }
        $m = $this->model->data->quarter($quarter->company_quarter);
        if (! $m['opec']) {
            return null;
        }
        $days = [];
        foreach (array_keys($this->model->data->opec) as $case) {
            $days[$case] = $this->model->inventoryDays($case);
        }

        return ['days' => $days, 'wti' => (float) $m['wti'], 'carryRate' => $this->model->data->c('opec_inventory_carry_annual')];
    }

    /**
     * The Gas stations page from Quarter 9: the three Cordell regions, what each name is worth there, the cost to repaint.
     *
     * @param  array<string, mixed>  $previous
     * @return array{regions: list<array{key: string, label: string, sites: float, keep: float, halden: float, cost: float, rebrandedBefore: bool}>}|null
     */
    private function rebrandDesk(Quarter $quarter, array $previous): ?array
    {
        if (! $this->book->isUnlocked('rebrand_core', $quarter->number)) {
            return null;
        }
        $regions = [];
        foreach ($this->model->data->rebrand as $key => $m) {
            $regions[] = ['key' => $key, 'label' => $m['label'], 'sites' => $m['sites'], 'keep' => $m['keep'], 'halden' => $m['halden'],
                'cost' => $this->model->rebrandCost($key), 'rebrandedBefore' => ($previous["rebrand_$key"] ?? 'keep') === 'rebrand'];
        }

        return ['regions' => $regions];
    }

    /** @return array{title: string, text: string|null, reason: string|null}|null */
    private function carryingFor(?TeamQuarter $tq, bool $forFaculty): ?array
    {
        if ($tq === null || $tq->carrying_status === null) {
            return null;
        }
        if ($tq->carrying_status !== 'ok' && ! $forFaculty) {
            return null;
        }

        return ['title' => $this->carrying->title(), 'text' => $tq->carrying, 'reason' => $forFaculty ? $tq->carrying_reason : null];
    }

    /** @return array<string, mixed> */
    public function build(Team $team, Quarter $quarter, ?User $viewer, bool $readOnly = false): array
    {
        $section = $team->section;
        $tq = TeamQuarter::query()->where('team_id', $team->id)->where('quarter_id', $quarter->id)->first();
        $members = $team->members()->with('user')->get();
        $me = $viewer === null ? null : $members->firstWhere('user_id', $viewer->id);
        $open = $quarter->status === Quarter::OPEN;
        $canEdit = $open && ! $readOnly && $me !== null;
        $data = $this->model->data;

        $previous = $this->book->previousEffective($team, $quarter);
        $current = $this->book->effective($team, $quarter);
        $savedPages = $tq->saved_pages ?? [];

        $start = null;
        if ($quarter->status !== Quarter::UPCOMING) {
            try {
                $start = $this->runner->startState($team, $quarter);
            } catch (\RuntimeException) {
                $start = null;
            }
        }

        return [
            'mandate' => $this->content->opening()['mandate'],
            'readOnly' => $readOnly,
            'canEdit' => $canEdit,
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'firstMeeting' => $team->first_meeting,
                'strategy' => $team->strategy_become ? "Halden should become a company that {$team->strategy_become} by {$team->strategy_by}." : null,
                'members' => $members->map(fn (TeamMember $m) => [
                    'name' => $m->user->name,
                    'seat' => TeamMember::SEATS[$m->seat] ?? $m->seat,
                    'isMe' => $viewer !== null && $m->user_id === $viewer->id,
                ])->values(),
            ],
            'me' => $me === null ? null : ['seat' => $me->seat, 'seatLabel' => TeamMember::SEATS[$me->seat] ?? $me->seat],
            'section' => ['name' => $section->name, 'teamCount' => $section->teams()->count()],
            'quarter' => [
                'id' => $quarter->id,
                'number' => $quarter->number,
                'total' => $section->weeks,
                'label' => $quarter->label(),
                'status' => $quarter->status,
                'deadline' => $quarter->deadline_at?->toIso8601String(),
                'deadlineText' => $this->deadlineText($quarter),
            ],
            'quarters' => $section->quarters()->get()->map(fn (Quarter $q) => [
                'id' => $q->id, 'number' => $q->number, 'label' => $q->label(), 'status' => $q->status,
            ])->values(),
            'content' => $this->content->hasQuarter($quarter->number) ? $this->contentFor($quarter) : null,
            'market' => $this->marketRows($quarter),
            'wti' => $this->wtiHistory($quarter),
            'advisors' => $this->room->view($team, $quarter, forFaculty: $readOnly && $me === null),
            'leverText' => $this->content->leverText(),
            'pages' => array_values(array_intersect(array_keys(self::PAGE_TITLES), $this->book->openPages($quarter->number))),
            'decisions' => [
                'previous' => $previous,
                'current' => $current,
                'saved' => $tq->decisions ?? [],
                'savedPages' => $savedPages,
                'levers' => array_values(array_map(fn (array $l) => $l + ['isOpen' => $l['unlock'] <= $quarter->number,
                    'isNew' => $l['unlock'] === $quarter->number && $quarter->number > 1], $this->book->levers())),
            ],
            'desk' => [
                'permianNow' => $start?->permianProd,
                'decline' => $data->c('permian_decline_qtr'),
                'adds' => array_map(fn (int $r) => $this->model->permianAdds($r), range(0, 40)),
                'brCapacity' => $data->c('br_capacity'),
                'rotCapacity' => $data->c('rot_capacity'),
                'rotStatus' => $start?->rotStatus,
                'genevaMaxVolume' => $data->c('geneva_max_volume'),
                'marketTp' => $this->runner->hasMarket($quarter) ? $this->model->transferPrices((float) $this->runner->marketFor($quarter)['wti'])[0] : null,
                'costTp' => $data->c('delivered_marginal_cost') + $data->c('sr_capital_charge'),
                'capital' => $this->capitalDesk($quarter, $previous),
                'rival' => $this->rivalDesk($quarter),
                'opec' => $this->opecDesk($quarter),
                'rebrand' => $this->rebrandDesk($quarter, $previous),
            ],
            'memo' => [
                'text' => $tq->memo ?? '',
                'savedAt' => $tq?->memo_saved_at?->toIso8601String(),
            ],
            'ready' => $tq?->ready_at?->toIso8601String(),
            'results' => $quarter->status === Quarter::PUBLISHED && $tq !== null && $tq->results !== null
                ? $this->results($team, $quarter, $tq, $data) : null,
            'carrying' => $this->carryingFor($tq, $readOnly && $me === null),
            'help' => $this->help->view() + ['enabled' => $this->help->enabled()],
            'feedback' => $tq?->feedback_published_at !== null && trim((string) $tq->feedback) !== ''
                ? ['title' => $this->drafts->screenText()['student_title'], 'text' => (string) $tq->feedback, 'at' => $tq->feedback_published_at->toIso8601String()]
                : null,
        ];
    }

    /** @return array<string, mixed> */
    private function contentFor(Quarter $quarter): array
    {
        $c = $this->content->quarter($quarter->number);

        return [
            'briefing' => $c['briefing'],
            'rule' => $c['rule'],
            'newPagesNote' => $c['new_pages_note'],
            'marchetti' => $c['marchetti'],
            'rotationNote' => $quarter->seats_rotated_at !== null ? ($c['rotation_note'] ?? null) : null,
            'exhibits' => array_map(fn (array $e) => ['title' => $e['title'], 'url' => route('exhibits.show', ['path' => $e['file']])], $c['exhibits']),
        ];
    }

    private function deadlineText(Quarter $quarter): string
    {
        // The status badge next to this already says closed or not open yet.
        return match ($quarter->status) {
            Quarter::OPEN => $quarter->deadline_at === null ? '' : 'Due '.$quarter->deadline_at->setTimezone('America/New_York')->format('l g:i a').' · '.$this->left($quarter->deadline_at),
            default => '',
        };
    }

    private function left(CarbonImmutable $deadline): string
    {
        $hours = (int) floor(now()->diffInHours($deadline, false));
        if ($hours < 0) {
            return 'past the deadline';
        }

        return $hours >= 48 ? intdiv($hours, 24).' days left' : $hours.' hours left';
    }

    /** @return list<array{name: string, last: ?float, now: ?float, unit: string, what: string}> */
    private function marketRows(Quarter $quarter): array
    {
        if (! $this->runner->hasMarket($quarter)) {
            return [];
        }
        $data = $this->model->data;
        $now = $this->runner->marketFor($quarter);
        $prev = $quarter->previous();
        if ($prev !== null) {
            $last = $this->runner->marketFor($prev);
        } else {
            $keys = array_column($data->market, 'quarter');
            $i = array_search($quarter->company_quarter, $keys, true);
            $last = is_int($i) && $i > 0 ? $data->market[$i - 1] : null;
        }
        $row = fn (string $name, string $k, string $unit, string $what) => [
            'name' => $name, 'last' => $last[$k] ?? null, 'now' => $now[$k], 'unit' => $unit, 'what' => $what,
        ];

        $rows = [
            $row('US oil price (WTI)', 'wti', 'usd', 'Dollars per barrel'),
            ['name' => 'International oil price (Brent)', 'last' => $last === null ? null : $last['wti'] + $data->c('brent_spread'),
                'now' => $now['wti'] + $data->c('brent_spread'), 'unit' => 'usd', 'what' => 'Usually $4.50 above the US price'],
            $row('Gulf Coast refining margin', 'gc', 'usd', 'What a refinery makes per barrel near Baton Rouge (the crack spread)'),
            $row('European refining margin', 'nwe', 'usd', 'Same idea, near Rotterdam'),
            $row('Asian refining margin', 'sg', 'usd', 'Same idea, near Singapore'),
            $row('Euro', 'eurusd', 'rate', 'Dollars you get for one euro'),
            $row('Norwegian krone', 'usdnok', 'rate', 'Kroner you get for one dollar'),
            $row('Singapore dollar', 'usdsgd', 'rate', 'Singapore dollars you get for one US dollar'),
        ];
        if (isset($now['cordell_nonfuel'])) {
            $usual = $data->c('window3_base_nonfuel');
            $rows[] = ['name' => 'Cordell shop margin', 'last' => $last === null ? null : (float) ($last['cordell_nonfuel'] ?? $usual), 'now' => (float) $now['cordell_nonfuel'],
                'unit' => 'usd', 'what' => sprintf('What a Cordell shop makes per gallon of fuel sold, this year. The usual figure is $%.2f; last summer\'s price war across your class set this one.', $usual)];
        }

        return $rows;
    }

    /** @return list<array{label: string, value: float, current: bool}> */
    private function wtiHistory(Quarter $quarter): array
    {
        $out = [];
        foreach ($this->model->data->market as $m) {
            $out[] = ['label' => substr($m['quarter'], 4)."'".substr($m['quarter'], 2, 2), 'value' => $m['wti'], 'current' => $m['quarter'] === $quarter->company_quarter];
            if ($m['quarter'] === $quarter->company_quarter) {
                break;
            }
        }

        return array_slice($out, -8);
    }

    /** @return array<string, mixed> */
    private function results(Team $team, Quarter $quarter, TeamQuarter $tq, ModelData $data): array
    {
        $r = $tq->results ?? [];
        $d = $tq->effective_decisions ?? [];
        $base = $data->baseOffsets();
        $prevQuarter = $quarter->previous();
        $prevTq = $prevQuarter === null ? null : TeamQuarter::query()->where('team_id', $team->id)->where('quarter_id', $prevQuarter->id)->first();
        $prev = $prevTq?->results;
        if ($prev === null) {
            $history = $this->model->runHistory()[1];
            $last = end($history);
            $prev = $last === false ? [] : $last['result']->metrics();
        }

        $segmentNames = ['oil_fields' => 'Oil fields', 'refineries' => 'Refineries', 'geneva' => 'Geneva trading',
            'gas_stations' => 'Gas stations', 'head_office' => 'Head office'];
        $pnl = [];
        foreach ($segmentNames as $k => $name) {
            $pnl[] = ['name' => $name, 'last' => (float) ($prev["segment.$k"] ?? 0), 'now' => (float) $r["segment.$k"]];
        }
        $pnl[] = ['name' => 'Halden total', 'last' => (float) ($prev['money.ebitda'] ?? 0), 'now' => (float) $r['money.ebitda'], 'total' => true];

        $named = [];
        $tpNow = (float) $r['ops.tp'];
        if (abs((float) $r['line.internal_crude_shift']) > 0.05) {
            $named[] = ['name' => 'Profit moved from the oil fields to the refinery', 'amount' => (float) $r['line.internal_crude_shift'],
                'why' => sprintf('Baton Rouge paid $%.2f a barrel for Texas crude instead of the market $%.2f. This moves profit around inside Halden. It does not add any.', $tpNow, (float) $r['ops.market_tp'])];
        }
        if ((float) $r['line.geneva_gap_trading'] > 0.05) {
            $named[] = ['name' => 'Geneva trading on the price gap', 'amount' => (float) $r['line.geneva_gap_trading'],
                'why' => 'Comes out of the refinery and shows up as Geneva profit.'];
        }
        if (abs((float) $r['line.norway_cutback_effect']) > 0.05) {
            $named[] = ['name' => 'Norway: pumping less', 'amount' => (float) $r['line.norway_cutback_effect'], 'why' => 'Revenue lost on the barrels you left in the ground, minus the costs you saved.'];
        }
        $named[] = ['name' => 'Rotterdam', 'amount' => (float) $r['line.rotterdam'], 'why' => match ((string) $r['ops.rot_status']) {
            'idle' => 'Paused: no crude bought, but staff, upkeep and care costs still paid.',
            'closed' => 'Closed.',
            default => sprintf('Running at %s%% of capacity, after its fixed costs.', rtrim(rtrim(number_format((float) ($d['rot_run'] ?? 0), 1), '0'), '.')),
        }];
        if (abs((float) $r['line.rotterdam_one_time']) > 0.05) {
            $named[] = ['name' => 'Rotterdam one-time cost', 'amount' => (float) $r['line.rotterdam_one_time'],
                'why' => (float) $r['line.rotterdam_one_time'] <= -100 ? 'The cost of closing the refinery.' : 'The cost of restarting the refinery.'];
        }
        if (abs((float) ($r['line.advisor_time'] ?? 0)) > 0.0001) {
            $n = (int) ($r['advisor.answers'] ?? 0);
            $named[] = ['name' => 'Advisor time', 'amount' => (float) $r['line.advisor_time'],
                'why' => sprintf('%d %s from your advisors, at $%sK each. Counted under Head office.', $n, $n === 1 ? 'answer' : 'answers', number_format($data->c('advisor_cost_per_answer') * 1000))];
        }
        if (abs((float) ($r['line.hedges'] ?? 0)) > 0.05) {
            $named[] = ['name' => 'Hedges settled', 'amount' => (float) $r['line.hedges'],
                'why' => 'What hedges paid or cost when they settled this quarter. Counted under Head office.'];
        }
        if (abs((float) ($r['ops.fx_effect'] ?? 0)) > 0.05) {
            $named[] = ['name' => 'Currency moves (already in the lines above)', 'amount' => (float) $r['ops.fx_effect'],
                'why' => 'How much the euro, the krone and the Singapore dollar changed earnings compared with last year\'s rates.'];
        }
        $projectsPaying = (float) ($r['line.projects_refining'] ?? 0) + (float) ($r['line.projects_upstream'] ?? 0);
        if (abs($projectsPaying) > 0.05) {
            $named[] = ['name' => 'Big projects paying back', 'amount' => $projectsPaying, 'why' => 'This quarter\'s share of what your projects deliver.'];
        }
        if (abs((float) ($r['line.cordell_price_match'] ?? 0)) > 0.05) {
            $named[] = ['name' => 'Matching Pelican\'s price cut (already in Gas stations)', 'amount' => (float) $r['line.cordell_price_match'],
                'why' => 'Six cents given up on every gallon Cordell sold in the markets where you matched.'];
        }
        if ((float) ($r['ops.rival_ignore_cost'] ?? 0) > 0.05) {
            $named[] = ['name' => 'Drivers who drifted to Pelican (already in Gas stations)', 'amount' => -(float) $r['ops.rival_ignore_cost'],
                'why' => 'Fuel and shop profit on the gallons that went to Pelican in the markets where you held your price.'];
        }
        if (abs((float) ($r['line.capacity_game'] ?? 0)) > 0.05) {
            $named[] = ['name' => 'Pelican\'s new Gulf Coast unit', 'amount' => (float) $r['line.capacity_game'],
                'why' => ($d['capacity_response'] ?? 'hold') === 'match' ? 'Two new units chasing the same barrels. Counted under Refineries.' : 'Margin Baton Rouge lost to the new unit. Counted under Refineries.'];
        }
        if (abs((float) ($r['line.crude_bought_ahead'] ?? 0)) > 0.05 || abs((float) ($r['line.inventory_carry'] ?? 0)) > 0.05) {
            $named[] = ['name' => 'Crude bought ahead of OPEC+', 'amount' => (float) ($r['line.crude_bought_ahead'] ?? 0),
                'why' => sprintf('Geneva bought %d days of Baton Rouge\'s crude at $%s before the meeting. Oil then moved $%s. Counted under Geneva trading.',
                    (int) $this->model->inventoryDays((string) ($d['opec_case'] ?? 'fails')), number_format((float) $r['ops.wti'] - (float) $r['ops.wti_shock'], 2), number_format((float) $r['ops.wti_shock'], 2))];
            $named[] = ['name' => 'Interest on the crude bought ahead', 'amount' => (float) ($r['line.inventory_carry'] ?? 0),
                'why' => 'The cost of the money tied up until Vienna decided.'];
        }
        if (abs((float) ($r['line.rebrand_gain'] ?? 0)) > 0.05) {
            $named[] = ['name' => 'The Halden name in the shops (already in Gas stations)', 'amount' => (float) $r['line.rebrand_gain'],
                'why' => 'What the Halden name earns, or costs, per fill in the regions you repainted, at this year\'s shop margin.'];
        }
        if ((float) ($r['ops.rebrand_outlay'] ?? 0) > 0) {
            $named[] = ['name' => 'Repainting the stations (capital spending, not in EBITDA)', 'amount' => -(float) $r['ops.rebrand_outlay'],
                'why' => 'Paid up front this quarter, like a big project. It adds to debt but not to your free cash flow score.'];
        }
        if ((float) ($r['ops.project_outlay'] ?? 0) > 0) {
            $named[] = ['name' => 'Big projects started (capital spending, not in EBITDA)', 'amount' => -(float) $r['ops.project_outlay'],
                'why' => 'Paid up front this quarter. It adds to debt but not to your free cash flow score.'];
        }
        $named[] = ['name' => 'Drilling in Texas (capital spending, not in EBITDA)', 'amount' => -(float) $d['rigs'] * $data->c('rig_capex_per_qtr'),
            'why' => sprintf('%d rigs at $%dM each this quarter.', (int) $d['rigs'], (int) $data->c('rig_capex_per_qtr'))];

        $kpiNames = [
            'profit_per_barrel' => ['Profit per barrel, whole company', '30%', 'usd2', 'What Halden makes on each barrel it produces, before head office costs'],
            'roace_pct' => ['Return on capital', '15%', 'pct', 'Profit after tax, compared with all the money invested in the business (yearly rate)'],
            'free_cash_flow' => ['Free cash flow', '15%', 'musd', 'Cash left over after tax and the spending needed to keep things running, before any new big projects'],
            'refining_vs_industry' => ['Refining profit vs competitors', '10%', 'usd2', 'How much more (or less) your refineries make per barrel than a typical refinery'],
            'shop_profit_per_station_k' => ['Shop profit per gas station', '10%', 'kusd', 'What each Cordell station earns Halden from snacks, coffee and the car wash'],
            'debt_to_earnings' => ['Debt compared with earnings', '10%', 'x', 'Roughly how many years of earnings it would take to pay off debt. Lower is better.'],
            'plant_condition' => ['Plant condition', '10%', 'pts', 'How well your refineries and oil fields are holding up, out of 100'],
        ];
        // The midterm and the end: every measure against the whole class (decision D4).
        $total = (int) $team->section->weeks;
        $compare = $quarter->number === (int) ceil($total / 2) || $quarter->number === $total;
        $classValues = [];
        if ($compare) {
            foreach (TeamQuarter::query()->where('quarter_id', $quarter->id)->whereNotNull('results')->get() as $other) {
                foreach (array_keys($kpiNames) as $k) {
                    $classValues[$k][] = (float) ($other->results["kpi.$k"] ?? 0);
                }
            }
        }
        $kpis = [];
        foreach ($kpiNames as $k => [$name, $weight, $unit, $def]) {
            $vals = $classValues[$k] ?? [];
            $kpis[] = ['name' => $name, 'weight' => $weight, 'unit' => $unit, 'def' => $def,
                'last' => isset($prev["kpi.$k"]) ? (float) $prev["kpi.$k"] : null, 'now' => (float) $r["kpi.$k"],
                'class' => $vals === [] ? null : ['low' => min($vals), 'avg' => array_sum($vals) / count($vals), 'high' => max($vals)]];
        }

        $earlier = [];
        $prevHealth = (float) ($prev['kpi.plant_condition'] ?? 70);
        if ((float) $r['kpi.plant_condition'] < $prevHealth - 0.01) {
            $why = (float) ($d['br_run'] ?? 0) > $data->c('br_wear_threshold')
                ? 'Baton Rouge ran above 97%, which wears the plant faster.'
                : 'Rotterdam ran below 80%, and equipment run slowly starts to act up.';
            $earlier[] = ['when' => 'This quarter', 'text' => sprintf('Plant condition slipped from %s to %s. %s', self::n($prevHealth), self::n((float) $r['kpi.plant_condition']), $why)];
        }
        $prevRigs = (int) (($this->book->previousEffective($team, $quarter))['rigs'] ?? 14);
        $earlier[] = ['when' => 'From last quarter', 'text' => sprintf('The %d rigs that drilled last quarter set this quarter\'s Texas output: %s barrels a day (last quarter: %s).',
            $prevRigs, number_format(round((float) $r['ops.permian_prod'], -2)), number_format(round((float) ($prev['ops.permian_prod'] ?? $r['ops.permian_prod']), -2)))];
        if ($quarter->company_quarter === '2028Q4') {
            $share = $this->runner->classBrUpgradeShare($quarter);
            if ($share !== null) {
                $shift = $this->model->window2Shift($share);
                $earlier[] = ['when' => 'From Q2 2028', 'text' => sprintf('%s of the teams in your class went ahead with the Baton Rouge upgrade. Those units are running now, and the Gulf Coast margin is %s a barrel %s than it would otherwise be.',
                    $share <= 0 ? 'None' : ($share >= 1 ? 'All' : sprintf('About %d%%', (int) round($share * 100))), '$'.number_format(abs($shift), 2), $shift < 0 ? 'lower' : 'higher')];
            }
        }
        if (str_starts_with($quarter->company_quarter, '2029')) {
            $aggression = $this->runner->classAggression($quarter);
            if ($aggression !== null) {
                $margin = $this->model->window3Nonfuel($aggression);
                $usual = $data->c('window3_base_nonfuel');
                $earlier[] = ['when' => 'From Q3 2028', 'text' => sprintf('%s of the Cordell markets across your class matched Pelican\'s cut. Drivers learned to shop on price, and the shop margin this year is %s cents a gallon instead of the usual %s.',
                    $aggression <= 0 ? 'None' : ($aggression >= 1 ? 'All' : sprintf('About %d%%', (int) round($aggression * 100))), self::n($margin * 100), self::n($usual * 100))];
            }
        }
        if ($quarter->number >= 2 && $team->first_meeting !== null) {
            $earlier[] = ['when' => 'From your first day', 'text' => $team->first_meeting === 'ingrid'
                ? 'You met Ingrid Vestergaard first. She has been quicker to approve your plans for the oil fields.'
                : 'You met Marcus Delacroix first. Baton Rouge has been quicker to back your plans for the refineries.'];
        }

        $content = $this->content->quarter($quarter->number);

        return [
            'story' => $this->content->story($quarter->number, $r, $d, $base, ['{rebranded_regions}' => $this->rebrandedRegions($d)]),
            'pnl' => $pnl,
            'named' => $named,
            'bridge' => [
                'previous' => (float) $r['bridge.previous'],
                'now' => (float) $r['money.ebitda'],
                'parts' => [
                    ['name' => 'Prices', 'note' => 'Oil prices, refining margins and currencies moved', 'value' => (float) $r['bridge.prices']],
                    ['name' => 'Your decisions this quarter', 'note' => 'What you changed compared with last quarter. Rigs you add or cut show up from next quarter.', 'value' => (float) $r['bridge.decisions']],
                    ['name' => 'Carried over from earlier quarters', 'note' => 'Output from earlier drilling, natural decline in older wells, shrinking European sales', 'value' => (float) $r['bridge.carried_over']],
                ],
            ],
            'kpis' => $kpis,
            'compare' => $compare,
            'score' => (float) $tq->score,
            'scoreLast' => $prevTq?->score,
            'rank' => (int) $tq->rank,
            'rankLast' => $prevTq?->rank,
            'earlier' => $earlier,
            'news' => $content['news'],
            'relations' => $this->content->relations($quarter->number, $r, $d, $base, $team->first_meeting),
            'money' => ['fcf' => (float) $r['money.fcf'], 'netDebt' => (float) $r['money.net_debt_end'], 'capex' => (float) $r['money.capex'], 'tax' => (float) $r['money.tax']],
        ];
    }

    /** @param  array<string, string|float|int|null>  $d */
    private function rebrandedRegions(array $d): string
    {
        $names = [];
        foreach ($this->model->data->rebrand as $key => $m) {
            if (($d["rebrand_$key"] ?? 'keep') === 'rebrand') {
                $names[] = $m['label'];
            }
        }
        if ($names === []) {
            return 'no region';
        }
        $last = array_pop($names);

        return $names === [] ? $last : implode(', ', $names).' and '.$last;
    }

    private static function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1), '0'), '.');
    }
}
