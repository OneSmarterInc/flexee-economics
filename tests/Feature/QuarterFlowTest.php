<?php

namespace Tests\Feature;

use App\Halden\Game\BoardVerdict;
use App\Halden\Game\DecisionBook;
use App\Halden\Game\QuarterRunner;
use App\Halden\Game\QuarterView;
use App\Halden\OperatingModel\ModelData;
use App\Models\Quarter;
use App\Models\Section;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamQuarter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class QuarterFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Section, 1: array<string, Team>} */
    private function section(array $teamNames): array
    {
        $faculty = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $section = Section::query()->create(['name' => 'Test', 'course_name' => 'Econ', 'weeks' => 14, 'faculty_user_id' => $faculty->id]);
        $teams = [];
        foreach ($teamNames as $name) {
            $teams[$name] = Team::query()->create(['section_id' => $section->id, 'name' => $name]);
        }
        for ($n = 1; $n <= 14; $n++) {
            Quarter::query()->create(['section_id' => $section->id, 'number' => $n, 'company_quarter' => Quarter::companyQuarterFor($n)]);
        }

        return [$section, $teams];
    }

    public function test_the_reference_teams_reproduce_the_golden_results_through_the_database(): void
    {
        [$section, $teams] = $this->section(['careful', 'average', 'careless']);
        $runner = app(QuarterRunner::class);

        $plans = [];
        foreach (ModelData::csv(base_path('packages/operating-model/fixtures/reference_decisions.csv')) as $row) {
            $flat = [];
            foreach ($row as $k => $v) {
                if (in_array($k, ['team', 'round', 'quarter', 'advisor_answers'], true)) {
                    continue;
                }
                $k = $k === 'crude_hedge_pct' ? 'crude_hedge' : $k;
                $flat[$k] = $v === '' ? null : (is_numeric($v) ? ($k === 'rigs' ? (int) $v : (float) $v) : $v);
            }
            $plans[$row['team']][(int) $row['round']] = $flat;
        }
        $golden = [];
        foreach (ModelData::csv(base_path('packages/operating-model/fixtures/golden_quarters.csv')) as $r) {
            $golden[$r['team']][$r['quarter']][$r['metric']] = (float) $r['value'];
        }

        $keys = [1 => '2027Q1', 2 => '2027Q2', 3 => '2027Q3', 4 => '2027Q4', 5 => '2028Q1', 6 => '2028Q2', 7 => '2028Q3', 8 => '2028Q4', 9 => '2029Q1', 10 => '2029Q2'];
        foreach ($keys as $n => $key) {
            $quarter = $section->quarters()->where('number', $n)->firstOrFail();
            if ($n === 8) {
                $quarter->update(['event_outcome' => 'partial']);   // the fixtures use the likeliest OPEC+ outcome
            }
            $runner->open($quarter);
            foreach ($teams as $name => $team) {
                TeamQuarter::query()->create(['team_id' => $team->id, 'quarter_id' => $quarter->id, 'decisions' => $plans[$name][$n]]);
            }
            $runner->close($quarter->refresh());
            $runner->publish($quarter->refresh());

            foreach ($teams as $name => $team) {
                $tq = TeamQuarter::query()->where('team_id', $team->id)->where('quarter_id', $quarter->id)->firstOrFail();
                $expected = $golden[$name][$key];
                $this->assertEqualsWithDelta($expected['score.composite'], $tq->score, 1e-4, "$name Q$n score");
                $this->assertSame((int) $expected['score.rank'], (int) $tq->rank, "$name Q$n rank");
                $r = $tq->results;
                $this->assertEqualsWithDelta($r['money.ebitda'] - $r['bridge.previous'],
                    $r['bridge.prices'] + $r['bridge.decisions'] + $r['bridge.carried_over'], 1e-6, "$name Q$n bridge adds up");
                foreach (['money.ebitda', 'money.fcf', 'segment.oil_fields', 'kpi.plant_condition', 'ops.permian_prod', 'line.hedges', 'ops.nwe', 'ops.project_outlay', 'line.projects_refining', 'line.cordell_price_match', 'line.capacity_game', 'line.crude_bought_ahead', 'ops.wti_shock', 'ops.gc', 'line.rebrand_gain', 'ops.rebrand_outlay', 'ops.nonfuel_per_gal', 'ops.br_run', 'ops.sg_accepted', 'ops.br_throughput'] as $m) {
                    $this->assertEqualsWithDelta($expected[$m], $tq->results[$m], max(1e-4, abs($expected[$m]) * 1e-6), "$name Q$n $m");
                }
            }
        }
    }

    public function test_unchanged_decisions_carry_forward_and_locked_ones_are_ignored(): void
    {
        [$section, $teams] = $this->section(['a']);
        $book = app(DecisionBook::class);
        $runner = app(QuarterRunner::class);
        $team = $teams['a'];

        $q1 = $section->quarters()->where('number', 1)->firstOrFail();
        $runner->open($q1);
        TeamQuarter::query()->create(['team_id' => $team->id, 'quarter_id' => $q1->id,
            'decisions' => ['rigs' => 11, 'tp_method' => 'cost', 'off_rural' => 9.0]]);
        $effective = $book->effective($team, $q1);
        $this->assertSame(11, $effective['rigs']);
        $this->assertSame('market', $effective['tp_method'], 'the crude price is not open until Quarter 4');
        $this->assertSame(5.0, $effective['off_rural'], 'station prices are not open until Quarter 2');

        $runner->close($q1->refresh());
        $runner->publish($q1->refresh());
        $q2 = $section->quarters()->where('number', 2)->firstOrFail();
        $runner->open($q2);
        $this->assertSame(11, $book->effective($team, $q2)['rigs'], 'last quarter\'s rigs carry forward');
    }

    public function test_quarters_open_in_order_and_unbuilt_economics_are_refused(): void
    {
        [$section] = $this->section(['a']);
        $runner = app(QuarterRunner::class);
        $q2 = $section->quarters()->where('number', 2)->firstOrFail();
        $this->expectException(RuntimeException::class);
        $runner->open($q2);
    }

    public function test_page_checks_use_plain_words(): void
    {
        $book = app(DecisionBook::class);
        [$clean, $errors] = $book->validatePage('oil_fields', ['rigs' => 55, 'norway' => 'maybe'], 1);
        $this->assertSame('Enter a number from 0 to 40.', $errors['rigs']);
        $this->assertSame('Pick one of the options.', $errors['norway']);

        [$clean, $errors] = $book->validatePage('trading_finance', ['tp_method' => 'other', 'tp_value' => '46.2'], 4);
        $this->assertSame([], $errors);
        $this->assertSame(['tp_method' => 'other', 'tp_value' => 46.2], $clean);

        [$clean] = $book->validatePage('trading_finance', ['tp_method' => 'cost'], 3);
        $this->assertSame([], $clean, 'the crude price cannot be set before Quarter 4');
    }

    public function test_answers_to_the_rival_open_in_quarter_seven_and_building_sticks(): void
    {
        $book = app(DecisionBook::class);
        [$clean] = $book->validatePage('gas_stations', ['resp_urban' => 'match', 'off_urban' => 2.0], 6);
        $this->assertSame(['off_urban' => 2.0], $clean, 'the rival has not moved before Quarter 7');
        [$clean, $errors] = $book->validatePage('gas_stations', ['resp_urban' => 'match', 'resp_rural' => 'maybe'], 7);
        $this->assertSame(['resp_urban' => 'match'], $clean);
        $this->assertSame('Pick one of the options.', $errors['resp_rural']);

        [$section, $teams] = $this->section(['a']);
        $runner = app(QuarterRunner::class);
        $team = $teams['a'];
        foreach ([1, 2, 3, 4, 5, 6] as $n) {
            $q = $section->quarters()->where('number', $n)->firstOrFail();
            $runner->open($q);
            $runner->close($q->refresh());
            $runner->publish($q->refresh());
        }
        $q7 = $section->quarters()->where('number', 7)->firstOrFail();
        $runner->open($q7);
        TeamQuarter::query()->create(['team_id' => $team->id, 'quarter_id' => $q7->id,
            'decisions' => ['capacity_response' => 'match', 'resp_suburban' => 'match']]);
        $runner->close($q7->refresh());
        $tq = TeamQuarter::query()->where('team_id', $team->id)->where('quarter_id', $q7->id)->firstOrFail();
        $this->assertSame('match', $tq->effective_decisions['capacity_response']);
        $this->assertEqualsWithDelta(0.0, $tq->results['line.capacity_game'], 1e-9, 'nothing is paid until Pelican shows its hand');
        $this->assertEqualsWithDelta(-24.8325, $tq->results['line.cordell_price_match'], 1e-3, 'six cents on every suburban gallon');
        $this->assertGreaterThan(0, $tq->results['ops.rival_ignore_cost']);
        $runner->publish($q7->refresh());
        $q8 = $section->quarters()->where('number', 8)->firstOrFail();
        $this->assertSame('match', $book->effective($team, $q8)['capacity_response'], 'a unit under way stays under way');
        $this->assertSame('match', $book->effective($team, $q8)['resp_suburban'], 'last quarter\'s answer carries forward');
    }

    public function test_seats_rotate_once_when_quarter_eight_opens_and_opec_is_drawn_at_the_close(): void
    {
        [$section, $teams] = $this->section(['a']);
        $runner = app(QuarterRunner::class);
        $team = $teams['a'];
        $users = User::factory()->count(5)->create(['role' => User::ROLE_STUDENT]);
        $seats = array_keys(TeamMember::SEATS);
        foreach ($users as $i => $u) {
            TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $u->id, 'seat' => $seats[$i]]);
        }
        foreach ([1, 2, 3, 4, 5, 6, 7] as $n) {
            $q = $section->quarters()->where('number', $n)->firstOrFail();
            $runner->open($q);
            $runner->close($q->refresh());
            $runner->publish($q->refresh());
            $this->assertSame('evp', TeamMember::query()->where('user_id', $users[0]->id)->firstOrFail()->seat, "no rotation before Quarter 8 (Q$n)");
        }
        $q8 = $section->quarters()->where('number', 8)->firstOrFail();
        $this->assertTrue($q8->isRotationQuarter());
        $runner->open($q8);
        $this->assertSame('oil_fields', TeamMember::query()->where('user_id', $users[0]->id)->firstOrFail()->seat, 'the EVP moves to Oil fields');
        $this->assertSame('evp', TeamMember::query()->where('user_id', $users[4]->id)->firstOrFail()->seat, 'Trading & finance becomes the EVP');
        $this->assertNotNull($q8->refresh()->seats_rotated_at);
        $runner->rotateSeats($q8->refresh());
        $this->assertSame('refineries', TeamMember::query()->where('user_id', $users[0]->id)->firstOrFail()->seat, 'a second call rotates again; open() guards against that');

        $this->assertNull($q8->refresh()->event_outcome);
        $this->assertEqualsWithDelta(74.0, $runner->marketFor($q8)['wti'], 1e-9, 'before the decision, students see pre-decision prices');
        TeamQuarter::query()->create(['team_id' => $team->id, 'quarter_id' => $q8->id, 'decisions' => ['opec_case' => 'full']]);
        $runner->close($q8->refresh());
        $q8->refresh();
        $this->assertContains($q8->event_outcome, ['full', 'partial', 'fails']);
        $tq = TeamQuarter::query()->where('team_id', $team->id)->where('quarter_id', $q8->id)->firstOrFail();
        $expectedWti = 74.0 + ['full' => 14.0, 'partial' => 7.0, 'fails' => -4.0][$q8->event_outcome];
        $this->assertEqualsWithDelta($expectedWti - 74.0, $tq->results['ops.wti_shock'], 1e-9);
        $this->assertEqualsWithDelta($expectedWti, $runner->marketFor($q8)['wti'], 1e-9, 'after the close, the prices page shows the outcome');
        $this->assertEqualsWithDelta(30 * 520000 * 0.96 * ($expectedWti - 74.0) / 1e6, $tq->results['line.crude_bought_ahead'], 1e-3, '30 days bought ahead when planning for the cut to hold');
        $this->assertEqualsWithDelta(-10.0, $tq->results['line.capacity_game'], 1e-9, 'Pelican built; the team held, so $10M a quarter');
    }

    public function test_quarters_eleven_to_fourteen_settle_their_answers_and_the_board_decides(): void
    {
        [$section, $teams] = $this->section(['a', 'b']);
        $runner = app(QuarterRunner::class);
        $book = app(DecisionBook::class);
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10] as $n) {
            $q = $section->quarters()->where('number', $n)->firstOrFail();
            $runner->open($q);
            $runner->close($q->refresh());
            $runner->publish($q->refresh());
        }
        $q11 = $section->quarters()->where('number', 11)->firstOrFail();
        $runner->open($q11);
        $this->assertTrue($book->isOpen('kessana_position', 11));
        $this->assertFalse($book->isOpen('kessana_position', 12), 'the position is answered once');
        $this->assertSame('accept', $book->effective($teams['a'], $q11->refresh())['kessana_position'], 'an unanswered team signs the new terms');
        TeamQuarter::query()->create(['team_id' => $teams['a']->id, 'quarter_id' => $q11->id, 'decisions' => ['kessana_position' => 'counter']]);
        TeamQuarter::query()->create(['team_id' => $teams['b']->id, 'quarter_id' => $q11->id, 'decisions' => ['kessana_position' => 'exit']]);
        $runner->close($q11->refresh());
        $runner->publish($q11->refresh());
        $a = TeamQuarter::query()->where('team_id', $teams['a']->id)->where('quarter_id', $q11->id)->firstOrFail();
        $b = TeamQuarter::query()->where('team_id', $teams['b']->id)->where('quarter_id', $q11->id)->firstOrFail();
        $this->assertEqualsWithDelta(0.68, $a->results['ops.kessana_take'], 1e-9, 'a reasoned counter settles in the middle');
        $this->assertLessThan(0, $a->results['line.kessana_take_change']);
        $this->assertSame(0.0, (float) $b->results['line.kessana'], 'leaving ends the line');
        $this->assertEqualsWithDelta(180.0, $b->results['ops.kessana_exit_proceeds'], 1e-9);
        $this->assertEqualsWithDelta(2300.0, $a->results['money.capital_employed_end'] - $b->results['money.capital_employed_end'], 1e-6, 'the book value leaves capital employed');
        $view = app(QuarterView::class)->build($teams['a'], $q11->refresh(), null, readOnly: true);
        $this->assertSame('counter', $view['results']['story']['band']);
        $this->assertStringContainsString('Tetteh', $view['results']['story']['paragraphs'][0]);
        $this->assertContains('Minister Tetteh', array_column($view['results']['relations'], 'who'));
        $this->assertTrue(collect($view['results']['named'])->contains(fn (array $l) => str_starts_with($l['name'], 'Kessana')));
        $this->assertTrue($view['desk']['kessana']['open'], 'the Q3 2029 page keeps showing the answer the team gave');
        $this->assertEqualsWithDelta(0.62, $view['desk']['kessana']['take'], 1e-9, 'the page shows the take the quarter started with');

        // Quarter 12: the five-year portfolio. The old project list is closed; the portfolio is placed once.
        $q12 = $section->quarters()->where('number', 12)->firstOrFail();
        $runner->open($q12);
        $this->assertFalse($book->isOpen('proj_helix', 12), 'the 2028 project list is closed');
        $this->assertTrue($book->isOpen('port_helix_rotterdam', 12));
        $this->assertFalse($book->isOpen('port_helix_rotterdam', 13));
        TeamQuarter::query()->create(['team_id' => $teams['a']->id, 'quarter_id' => $q12->id, 'decisions' => ['port_biofuel_conversion' => 'go', 'port_offshore_wind' => 'go']]);
        TeamQuarter::query()->create(['team_id' => $teams['b']->id, 'quarter_id' => $q12->id, 'decisions' => ['port_helix_rotterdam' => 'go', 'port_offshore_wind' => 'go', 'port_euro_retail_divest' => 'go']]);
        $runner->close($q12->refresh());
        $runner->publish($q12->refresh());
        $a12 = TeamQuarter::query()->where('team_id', $teams['a']->id)->where('quarter_id', $q12->id)->firstOrFail();
        $b12 = TeamQuarter::query()->where('team_id', $teams['b']->id)->where('quarter_id', $q12->id)->firstOrFail();
        $this->assertSame(0.0, (float) $a12->results['ops.portfolio_capex'], 'nothing goes out in the go-ahead quarter');
        $this->assertEqualsWithDelta(550.0, $b12->results['ops.divest_proceeds'], 1e-9);
        $this->assertSame(0.0, (float) $a12->results['ops.divest_proceeds']);
        $view12 = app(QuarterView::class)->build($teams['b'], $q12->refresh(), null, readOnly: true);
        $this->assertSame('transition', $view12['results']['story']['band']);
        $this->assertStringContainsString('sold the European stations', implode(' ', $view12['results']['story']['paragraphs']));
        $this->assertStringContainsString('Helix at Rotterdam and offshore wind off Norway, $1,750M over five years', implode(' ', $view12['results']['story']['paragraphs']));
        $this->assertTrue(collect($view12['market'])->contains(fn (array $row) => $row['name'] === 'Carbon price'));
        $this->assertFalse($view12['desk']['portfolio']['projects'][0]['available'] === false);
        $this->assertSame('go', $view12['decisions']['current']['port_helix_rotterdam']);
        // Quarter 13: the factor markets. One-time answers to the union and on the turnaround; the breakdown draw comes next quarter.
        $q13 = $section->quarters()->where('number', 13)->firstOrFail();
        $runner->open($q13);
        $this->assertTrue($book->isOpen('norway_wage', 13) && $book->isOpen('turnaround', 13));
        $this->assertFalse($book->isOpen('norway_wage', 14));
        TeamQuarter::query()->create(['team_id' => $teams['a']->id, 'quarter_id' => $q13->id, 'decisions' => ['norway_wage' => 'refuse', 'turnaround' => 'wait']]);
        TeamQuarter::query()->create(['team_id' => $teams['b']->id, 'quarter_id' => $q13->id, 'decisions' => ['norway_wage' => 'accept', 'turnaround' => 'now']]);
        $runner->close($q13->refresh());
        $runner->publish($q13->refresh());
        $this->assertNull($q13->refresh()->event_outcome, 'nothing is drawn in the turnaround quarter itself');
        $a13 = TeamQuarter::query()->where('team_id', $teams['a']->id)->where('quarter_id', $q13->id)->firstOrFail();
        $b13 = TeamQuarter::query()->where('team_id', $teams['b']->id)->where('quarter_id', $q13->id)->firstOrFail();
        $this->assertSame(2.0, (float) $a13->results['ops.norway_stoppage_weeks']);
        $this->assertEqualsWithDelta(0.08, $a13->results['ops.norway_wage_uplift'], 1e-12, 'arbitration');
        $this->assertSame(1.0, (float) $a13->results['ops.turnaround_pending']);
        $this->assertEqualsWithDelta(-81.0, $b13->results['line.turnaround'], 1e-9);
        $this->assertEqualsWithDelta(3.0, $b13->results['kpi.plant_condition'] - $a13->results['kpi.plant_condition'], 1e-9, 'three points for running past due');
        $view13 = app(QuarterView::class)->build($teams['a'], $q13->refresh(), null, readOnly: true);
        $this->assertSame('refuse', $view13['results']['story']['band']);
        $this->assertTrue(collect($view13['results']['named'])->contains(fn (array $l) => str_contains($l['name'], 'stoppage')));
        $q14 = $section->quarters()->where('number', 14)->firstOrFail();
        $this->assertTrue($runner->followsLaborQuarter($q14));

        // Quarter 14: the board meeting. The world is drawn at open, the breakdown at close, nothing is set, and the verdict is the instructor's.
        $runner->open($q14);
        $q14->refresh();
        $this->assertTrue($q14->isBoardQuarter());
        $this->assertMatchesRegularExpression('/^(low|mid|high):(slow|fast|collapse)$/', (string) $q14->world);
        $view14 = app(QuarterView::class)->build($teams['a'], $q14, null, readOnly: true);
        $this->assertSame([], $view14['pages'], 'no operating pages in the board quarter');
        $this->assertNotNull($view14['board']);
        $this->assertCount(13, $view14['board']['record']);
        $this->assertStringContainsString('Your five-year plan is worth', $view14['board']['world']['text']);
        TeamQuarter::query()->updateOrCreate(['team_id' => $teams['a']->id, 'quarter_id' => $q14->id], ['defense' => ['synthesis' => 'We ran it as one company.', 'decisions' => 'Three.', 'counterfactual' => 'Kessana.'], 'defense_saved_at' => now()]);
        $runner->close($q14->refresh());
        $q14->refresh();
        $this->assertContains($q14->event_outcome, ['outage', 'no_outage'], 'the breakdown behind the delayed turnaround is drawn at the close');
        $runner->publish($q14->refresh());
        $a14 = TeamQuarter::query()->where('team_id', $teams['a']->id)->where('quarter_id', $q14->id)->firstOrFail();
        $this->assertEqualsWithDelta(-60.0, $a14->results['line.turnaround'], 1e-9, 'the off-peak crews came');
        $this->assertSame($q14->event_outcome === 'outage' ? -150.0 : 0.0, (float) $a14->results['line.turnaround_outage']);
        $this->assertEqualsWithDelta(-8.4, $a14->results['line.norway_wages'], 1e-9, 'the raise carries');
        $view14 = app(QuarterView::class)->build($teams['a'], $q14->refresh(), null, readOnly: true);
        $this->assertSame('Eighteen months later', $view14['results']['story']['title']);
        $text = implode(' ', $view14['results']['story']['paragraphs']);
        $this->assertStringContainsString('A sell-side analyst', $text);
        $this->assertStringContainsString('The board, through Margrethe', $text);
        $this->assertStringContainsString('Minister Tetteh', $text, 'team a countered in Kessana, so the government is its partner judge');
        $this->assertStringNotContainsString('{', $text);
        $this->assertNull($view14['board']['verdict'], 'no verdict until the instructor publishes one');
        $verdict = app(BoardVerdict::class);
        $outcomes = $verdict->outcomes($teams['a'], $q14);
        $this->assertNotNull($outcomes['strong']);
        $this->assertSame('widen', $verdict->suggested('strong', true));
        $this->assertSame('conditions', $verdict->suggested('weak', true));
        $this->assertSame('split', $verdict->suggested('strong', false));
        $this->assertSame('sold', $verdict->suggested('weak', false));
        $a14->update(['reasoning' => 'strong', 'verdict' => 'widen', 'verdict_published_at' => now()]);
        $view14 = app(QuarterView::class)->build($teams['a'], $q14->refresh(), null, readOnly: true);
        $this->assertSame('They keep the job and give you more.', $view14['board']['verdict']['title']);
    }
}
