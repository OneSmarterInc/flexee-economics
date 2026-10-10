<?php

namespace Tests\Feature;

use App\Halden\Game\DecisionBook;
use App\Halden\Game\QuarterRunner;
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

        $keys = [1 => '2027Q1', 2 => '2027Q2', 3 => '2027Q3', 4 => '2027Q4', 5 => '2028Q1', 6 => '2028Q2', 7 => '2028Q3', 8 => '2028Q4', 9 => '2029Q1'];
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
                foreach (['money.ebitda', 'money.fcf', 'segment.oil_fields', 'kpi.plant_condition', 'ops.permian_prod', 'line.hedges', 'ops.nwe', 'ops.project_outlay', 'line.projects_refining', 'line.cordell_price_match', 'line.capacity_game', 'line.crude_bought_ahead', 'ops.wti_shock', 'ops.gc', 'line.rebrand_gain', 'ops.rebrand_outlay', 'ops.nonfuel_per_gal'] as $m) {
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

    public function test_quarter_ten_cannot_open_until_its_economics_exist(): void
    {
        [$section] = $this->section(['a']);
        $runner = app(QuarterRunner::class);
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $n) {
            $q = $section->quarters()->where('number', $n)->firstOrFail();
            $runner->open($q);
            $runner->close($q->refresh());
            $runner->publish($q->refresh());
        }
        $this->expectExceptionMessage("The economics for Q2 2029 aren't built yet.");
        $runner->open($section->quarters()->where('number', 10)->firstOrFail());
    }
}
