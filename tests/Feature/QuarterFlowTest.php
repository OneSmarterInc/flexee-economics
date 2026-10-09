<?php

namespace Tests\Feature;

use App\Halden\Game\DecisionBook;
use App\Halden\Game\QuarterRunner;
use App\Halden\OperatingModel\ModelData;
use App\Models\Quarter;
use App\Models\Section;
use App\Models\Team;
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

        $keys = [1 => '2027Q1', 2 => '2027Q2', 3 => '2027Q3', 4 => '2027Q4', 5 => '2028Q1', 6 => '2028Q2'];
        foreach ($keys as $n => $key) {
            $quarter = $section->quarters()->where('number', $n)->firstOrFail();
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
                foreach (['money.ebitda', 'money.fcf', 'segment.oil_fields', 'kpi.plant_condition', 'ops.permian_prod', 'line.hedges', 'ops.nwe', 'ops.project_outlay', 'line.projects_refining'] as $m) {
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

    public function test_quarter_seven_cannot_open_until_its_economics_exist(): void
    {
        [$section] = $this->section(['a']);
        $runner = app(QuarterRunner::class);
        foreach ([1, 2, 3, 4, 5, 6] as $n) {
            $q = $section->quarters()->where('number', $n)->firstOrFail();
            $runner->open($q);
            $runner->close($q->refresh());
            $runner->publish($q->refresh());
        }
        $this->expectExceptionMessage("The economics for Q3 2028 aren't built yet.");
        $runner->open($section->quarters()->where('number', 7)->firstOrFail());
    }
}
