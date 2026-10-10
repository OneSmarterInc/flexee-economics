<?php

namespace Tests\Feature;

use App\Halden\Game\QuarterRunner;
use App\Models\Quarter;
use App\Models\Section;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamQuarter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The 7-week course (decision D1): the same fourteen company quarters, two a week with the same settings. The team
 * works on the first quarter of each pair; the second runs on its own at the close and goes out with the first.
 */
class SevenWeekFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $faculty;

    private Section $section;

    private Team $team;

    /** @var array<string, User> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->faculty = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $this->section = Section::query()->create(['name' => 'Short class', 'course_name' => 'Econ', 'weeks' => 7, 'faculty_user_id' => $this->faculty->id]);
        $this->team = Team::query()->create(['section_id' => $this->section->id, 'name' => 'Alpha', 'strategy_become' => 'earns its keep', 'strategy_by' => 'running every plant to its margin']);
        foreach (array_keys(TeamMember::SEATS) as $seat) {
            $user = User::factory()->create(['role' => User::ROLE_STUDENT, 'opening_seen_at' => now()]);
            TeamMember::query()->create(['team_id' => $this->team->id, 'user_id' => $user->id, 'seat' => $seat]);
            $this->students[$seat] = $user;
        }
        for ($n = 1; $n <= Quarter::COMPANY_QUARTERS; $n++) {
            Quarter::query()->create(['section_id' => $this->section->id, 'number' => $n, 'company_quarter' => Quarter::companyQuarterFor($n)]);
        }
    }

    private function q(int $n): Quarter
    {
        return $this->section->quarters()->where('number', $n)->firstOrFail();
    }

    private function tq(int $n): TeamQuarter
    {
        return TeamQuarter::query()->where('team_id', $this->team->id)->where('quarter_id', $this->q($n)->id)->firstOrFail();
    }

    private function runWeek(int $first): void
    {
        $runner = app(QuarterRunner::class);
        $runner->open($this->q($first));
        $runner->close($this->q($first));
        $runner->publish($this->q($first));
    }

    public function test_a_week_covers_two_quarters_with_the_same_settings(): void
    {
        $q1 = $this->q(1);
        $this->assertTrue($q1->isPaired());
        $this->assertSame('Q1 and Q2 2027', $q1->weekLabel());
        $this->assertSame(2, $q1->playEnd());
        $this->assertSame(1, $this->q(2)->week());
        $this->assertTrue($this->q(2)->weekStart()->is($q1));
        $this->assertTrue($this->q(8)->isComparisonQuarter(), 'the midterm is the second half of week 4');
        $this->assertFalse($this->q(7)->isComparisonQuarter());
        $this->assertTrue($this->q(9)->isRotationQuarter(), 'seats rotate when week 5 opens');
        $this->assertFalse($this->q(8)->isRotationQuarter());
        $this->assertTrue($this->q(14)->isBoardQuarter());
        $this->assertTrue($this->q(13)->boardQuarter()->is($this->q(14)));

        // The faculty board shows the week, not the quarter.
        $this->actingAs($this->faculty)->get('/faculty')->assertInertia(fn (Assert $p) => $p
            ->where('quarter.week', 1)->where('quarter.weeks', 7)->where('quarter.paired', true)->where('quarter.label', 'Q1 and Q2 2027')
            ->has('pages', 3, fn (Assert $page) => $page->etc())
            ->where('pages.2.page', 'gas_stations'));

        app(QuarterRunner::class)->open($q1);
        $student = $this->students['gas_stations'];
        $this->actingAs($student)->get("/play/{$q1->id}")->assertInertia(fn (Assert $p) => $p
            ->where('quarter.heading', 'Q1 and Q2 2027 · Week 1 of 7')
            ->where('quarter.pairedNote', null)
            ->where('pages', ['oil_fields', 'refineries', 'gas_stations'])
            ->where('content.also.title', 'Also this week: Q2 2027')
            ->where('content.also.question', 'What should Cordell charge its station owners in each kind of market, and what should Halden charge drivers in Europe?')
            ->where('content.newPagesNote', 'This week you can change the oil fields, the refineries and the gas station prices. Gas station prices are new in Q2 2027, which runs with the settings you make now.')
            ->where('decisions.levers', fn ($levers) => collect($levers)->firstWhere('key', 'off_urban')['isOpen'] === true
                && collect($levers)->firstWhere('key', 'off_urban')['isNew'] === false
                && collect($levers)->firstWhere('key', 'tp')['isOpen'] === false));

        // A second-quarter page saves on the first quarter, and the first quarter's own pages too.
        $this->actingAs($student)->post("/play/{$q1->id}/page/gas_stations", ['off_urban' => 4.0, 'off_suburban' => 3.5, 'off_rural' => 5.0, 'off_interstate' => 3.0, 'off_nl' => 0, 'off_be' => 1.0, 'off_de' => -1.0])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->students['oil_fields'])->post("/play/{$q1->id}/page/oil_fields", ['rigs' => 11, 'norway' => 'run'])->assertSessionHasNoErrors();
        $this->actingAs($this->students['evp'])->post("/play/{$q1->id}/memo", ['memo' => 'Eleven rigs, and four cents in the cities.'])->assertSessionHasNoErrors();

        // Closing the first quarter runs both; the price lands in Q2, where it opens.
        $this->actingAs($this->faculty)->post("/faculty/quarters/{$q1->id}/close")->assertSessionHasNoErrors();
        $this->assertSame(Quarter::CLOSED, $q1->refresh()->status);
        $this->assertSame(Quarter::CLOSED, $this->q(2)->status);
        $this->assertSame(11, $this->tq(1)->effective_decisions['rigs']);
        $this->assertEqualsWithDelta(2.0, $this->tq(1)->effective_decisions['off_urban'], 1e-9, 'Q1 keeps the old presidents\' price: the page is not open yet');
        $this->assertSame(11, $this->tq(2)->effective_decisions['rigs']);
        $this->assertEqualsWithDelta(4.0, $this->tq(2)->effective_decisions['off_urban'], 1e-9);
        $this->assertNotNull($this->tq(2)->results);
        $this->assertNotNull($this->tq(2)->score);

        // Publishing the first quarter publishes the second; the strip shows both, each with its own results and a note.
        $this->actingAs($this->faculty)->post("/faculty/quarters/{$q1->id}/publish")->assertSessionHasNoErrors();
        $this->assertSame(Quarter::PUBLISHED, $this->q(2)->status);
        $this->actingAs($student)->get("/play/{$q1->id}")->assertInertia(fn (Assert $p) => $p
            ->has('results.story.paragraphs')
            ->where('quarter.pairedNote', "Q2 2027's results are on the next quarter in the strip at the top. Your score for the week is Q2 2027's, where the settings had fully landed."));
        $this->actingAs($student)->get('/play/'.$this->q(2)->id)->assertInertia(fn (Assert $p) => $p
            ->has('results.story.paragraphs')
            ->where('quarter.heading', 'Q1 and Q2 2027 · Week 1 of 7')
            ->where('quarter.pairedNote', 'Q2 2027 ran on its own with the settings you made for Q1 2027. Your pages, your advisors and your memo for the week are on Q1 2027.'));
        $this->actingAs($this->faculty)->get('/faculty')->assertInertia(fn (Assert $p) => $p
            ->where('quarter.week', 1)->where('quarter.status', 'published')
            ->where('next.label', 'Q3 and Q4 2027')
            ->where('feedbackQuarter.label', 'Q1 2027'));
    }

    public function test_the_midterm_the_rotation_the_one_time_decisions_and_the_board_fall_in_their_weeks(): void
    {
        $runner = app(QuarterRunner::class);
        foreach ([1, 3, 5] as $first) {
            $this->runWeek($first);
        }

        // Week 4: the midterm comparison lands on its second quarter.
        $this->runWeek(7);
        $this->actingAs($this->students['evp'])->get('/play/'.$this->q(7)->id)->assertInertia(fn (Assert $p) => $p->where('results.compare', false));
        $this->actingAs($this->students['evp'])->get('/play/'.$this->q(8)->id)->assertInertia(fn (Assert $p) => $p->where('results.compare', true));

        // Week 5: seats rotate as it opens.
        $before = TeamMember::query()->where('team_id', $this->team->id)->pluck('seat', 'user_id')->all();
        $runner->open($this->q(9));
        $this->assertNotNull($this->q(9)->seats_rotated_at);
        $this->assertNotSame($before, TeamMember::query()->where('team_id', $this->team->id)->pluck('seat', 'user_id')->all());
        $runner->close($this->q(9));
        $runner->publish($this->q(9));

        // Week 6: Kessana (Q3 2029) and the five-year plan (Q4 2029) are both answered on the first quarter's pages.
        $q11 = $this->q(11);
        $runner->open($q11);
        $oil = TeamMember::query()->where('team_id', $this->team->id)->where('seat', 'oil_fields')->firstOrFail()->user;
        $this->actingAs($oil)->post("/play/{$q11->id}/page/oil_fields", ['rigs' => 11, 'norway' => 'run', 'kessana_position' => 'counter'])->assertSessionHasNoErrors();
        $this->actingAs($oil)->post("/play/{$q11->id}/page/capital", ['port_offshore_wind' => 'go', 'port_biofuel_conversion' => 'go'])->assertSessionHasNoErrors();
        $this->actingAs($oil)->get("/play/{$q11->id}")->assertInertia(fn (Assert $p) => $p
            ->where('desk.kessana.open', true)->where('desk.portfolio.open', true));
        $runner->close($q11);
        $this->assertSame('counter', $this->tq(11)->effective_decisions['kessana_position']);
        $this->assertEqualsWithDelta(0.68, $this->tq(11)->results['ops.kessana_take'], 1e-9);
        $this->assertSame('go', $this->tq(12)->effective_decisions['port_offshore_wind']);
        $this->assertSame(['biofuel_conversion', 'offshore_wind'], array_keys((array) $this->tq(12)->state_after['portfolio']));
        $this->assertSame([], (array) $this->tq(11)->state_after['portfolio'], 'the plan is placed in Q4 2029, not before');
        $runner->publish($q11);

        // Week 7: the labor quarter and the board meeting. The world is drawn as the week opens; the defense lives on the board quarter.
        $q13 = $this->q(13);
        $runner->open($q13);
        $this->assertNotNull($this->q(14)->world);
        $this->actingAs($oil)->get("/play/{$q13->id}")->assertInertia(fn (Assert $p) => $p
            ->where('pages', ['oil_fields', 'refineries', 'gas_stations', 'trading_finance', 'capital'])
            ->where('board.sentence', 'Halden should become a company that earns its keep by running every plant to its margin.')
            ->where('board.world.text', fn ($t) => str_contains((string) $t, 'Your five-year plan is worth'))
            ->where('content.rule', fn ($t) => str_contains((string) $t, 'This week also brings the board meeting.')));
        $this->actingAs($oil)->post("/play/{$q13->id}/defense", ['synthesis' => 'One company.', 'decisions' => 'Three.', 'counterfactual' => 'Fewer rigs.'])->assertSessionHasNoErrors();
        $this->assertSame('Three.', $this->tq(14)->defense['decisions']);
        $this->assertNull(TeamQuarter::query()->where('team_id', $this->team->id)->where('quarter_id', $q13->id)->first()?->defense);
        $this->actingAs($oil)->post("/play/{$q13->id}/page/refineries", ['br_run' => 95, 'rot_run' => 80, 'rot_posture' => 'run', 'sg_request' => 90, 'turnaround' => 'wait'])->assertSessionHasNoErrors();
        $runner->close($q13);
        $this->assertSame(Quarter::CLOSED, $this->q(14)->status);
        $this->assertContains($this->q(14)->event_outcome, ['outage', 'no_outage'], 'the breakdown is drawn at the close of the board quarter');
        $runner->publish($q13);

        // The faculty board offers the memo feedback on the first quarter and the defense and the verdict on the second.
        $this->actingAs($this->faculty)->get('/faculty')->assertInertia(fn (Assert $p) => $p
            ->where('quarter.week', 7)->where('quarter.isBoard', true)->where('quarter.boardQuarterId', $this->q(14)->id)
            ->where('feedbackQuarter.id', $q13->id)
            ->where('teams.0.defense', true)->where('teams.0.verdict', 'none'));
        $this->actingAs($this->faculty)->post("/faculty/teams/{$this->team->id}/quarters/{$this->q(14)->id}/verdict?section={$this->section->id}", ['reasoning' => 'strong', 'publish' => true])
            ->assertSessionHasNoErrors();
        $this->actingAs($oil)->get("/play/{$q13->id}")->assertInertia(fn (Assert $p) => $p
            ->where('board.verdict.title', 'They keep the job and give you more.')
            ->has('board.record', 13));
    }
}
