<?php

namespace Tests\Feature;

use App\Halden\Game\DecisionBook;
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

class HaldenScreensTest extends TestCase
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
        $this->section = Section::query()->create(['name' => 'Test class', 'course_name' => 'Econ', 'weeks' => 14, 'faculty_user_id' => $this->faculty->id]);
        $this->team = Team::query()->create(['section_id' => $this->section->id, 'name' => 'Alpha']);
        foreach (array_keys(TeamMember::SEATS) as $seat) {
            $user = User::factory()->create(['role' => User::ROLE_STUDENT, 'opening_seen_at' => now()]);
            TeamMember::query()->create(['team_id' => $this->team->id, 'user_id' => $user->id, 'seat' => $seat]);
            $this->students[$seat] = $user;
        }
        for ($n = 1; $n <= 14; $n++) {
            Quarter::query()->create(['section_id' => $this->section->id, 'number' => $n, 'company_quarter' => Quarter::companyQuarterFor($n)]);
        }
    }

    private function quarter(int $n): Quarter
    {
        return $this->section->quarters()->where('number', $n)->firstOrFail();
    }

    private function openFirst(): Quarter
    {
        app(QuarterRunner::class)->open($this->quarter(1));

        return $this->quarter(1);
    }

    public function test_a_new_student_sees_the_opening_first_and_only_the_evp_records_the_teams_choice(): void
    {
        $q1 = $this->openFirst();
        $student = $this->students['oil_fields'];
        $student->forceFill(['opening_seen_at' => null])->save();

        $this->actingAs($student)->get('/play')->assertRedirect(route('opening'));
        $this->actingAs($student)->get('/opening')->assertInertia(fn (Assert $page) => $page
            ->component('halden/Opening')
            ->where('team.name', 'Alpha')
            ->where('isEvp', false)
            ->where('canChange', false)
            ->where('opening.screens.quarter.intro', 'Every week of the course is one quarter (three months) at Halden. Each quarter, your team does the same four things.')
            ->where('replay', false));

        // A teammate who isn't the EVP finishes the opening but doesn't decide for the team.
        $this->actingAs($student)->post('/opening', ['first_meeting' => 'marcus', 'become' => 'x', 'by' => 'y'])
            ->assertRedirect(route('play.home'));
        $this->assertNull($this->team->refresh()->first_meeting);
        $this->assertNotNull($student->refresh()->opening_seen_at);

        // The EVP records it, and can change it while Quarter 1 is open.
        $evp = $this->students['evp'];
        $this->actingAs($evp)->get('/opening')->assertInertia(fn (Assert $page) => $page->where('canChange', true));
        $this->actingAs($evp)->post('/opening', ['first_meeting' => 'ingrid', 'become' => 'earns more per barrel', 'by' => 'running it as one']);
        $this->actingAs($evp)->post('/opening', ['first_meeting' => 'marcus', 'become' => '', 'by' => '']);
        $this->team->refresh();
        $this->assertSame('marcus', $this->team->first_meeting);
        $this->assertSame('earns more per barrel', $this->team->strategy_become, 'an empty sentence never wipes a recorded one');

        // Once Quarter 1 has been run, nothing changes.
        app(QuarterRunner::class)->close($q1);
        $this->actingAs($evp)->post('/opening', ['first_meeting' => 'ingrid', 'become' => 'a', 'by' => 'b']);
        $this->assertSame('marcus', $this->team->refresh()->first_meeting);
    }

    public function test_quarters_close_on_their_own_at_the_deadline(): void
    {
        $q1 = $this->openFirst();
        $q1->update(['deadline_at' => now()->addMinute()]);
        $this->artisan('halden:close-due')->assertSuccessful();
        $this->assertSame(Quarter::OPEN, $q1->refresh()->status);

        $q1->update(['deadline_at' => now()->subMinute()]);
        $this->artisan('halden:close-due')->assertSuccessful();
        $this->assertSame(Quarter::CLOSED, $q1->refresh()->status, 'closed and run, results still hidden');
    }

    public function test_the_quarter_screen_opens_for_the_team(): void
    {
        $q = $this->openFirst();
        $this->actingAs($this->students['evp'])->get("/play/{$q->id}")->assertInertia(fn (Assert $page) => $page
            ->component('halden/Play')
            ->where('quarter.number', 1)
            ->where('canEdit', true)
            ->where('me.seat', 'evp')
            ->has('content.briefing.headline')
            ->has('content.exhibits', 2));
    }

    public function test_saving_a_page_keeps_good_values_and_explains_bad_ones_plainly(): void
    {
        $q = $this->openFirst();
        $student = $this->students['oil_fields'];

        $this->actingAs($student)->from("/play/{$q->id}")
            ->post("/play/{$q->id}/page/oil_fields", ['rigs' => 55])
            ->assertSessionHasErrors(['rigs' => 'Enter a number from 0 to 40.']);

        $this->actingAs($student)->from("/play/{$q->id}")
            ->post("/play/{$q->id}/page/oil_fields", ['rigs' => 11, 'norway' => 'run'])
            ->assertSessionHasNoErrors();

        $tq = TeamQuarter::query()->where('team_id', $this->team->id)->where('quarter_id', $q->id)->firstOrFail();
        $this->assertSame(11, $tq->decisions['rigs'] ?? null);
        $this->assertSame($student->name, $tq->saved_pages['oil_fields']['by'] ?? null);

        $this->actingAs($student)->post("/play/{$q->id}/page/not_a_page", [])->assertNotFound();
    }

    public function test_the_portfolio_page_refuses_a_set_that_does_not_fit_and_keeps_one_that_does(): void
    {
        $runner = app(QuarterRunner::class);
        for ($n = 1; $n <= 11; $n++) {
            $q = $this->quarter($n);
            $runner->open($q);
            $runner->close($q->refresh());
            $runner->publish($q->refresh());
        }
        $q = $this->quarter(12);
        $runner->open($q);
        $evp = $this->students['evp'];
        $this->actingAs($evp)->from("/play/{$q->id}")
            ->post("/play/{$q->id}/page/capital", ['port_helix_rotterdam' => 'go', 'port_offshore_wind' => 'go'])
            ->assertSessionHasErrors(['capital']);
        $this->actingAs($evp)->from("/play/{$q->id}")
            ->post("/play/{$q->id}/page/capital", ['port_helix_rotterdam' => 'go', 'port_biofuel_conversion' => 'go', 'port_euro_retail_divest' => 'go'])
            ->assertSessionHasErrors(['capital']);
        $this->actingAs($evp)->from("/play/{$q->id}")
            ->post("/play/{$q->id}/page/capital", ['port_helix_rotterdam' => 'go', 'port_offshore_wind' => 'go', 'port_euro_retail_divest' => 'go', 'proj_helix' => 'commit'])
            ->assertSessionHasNoErrors();
        $tq = TeamQuarter::query()->where('team_id', $this->team->id)->where('quarter_id', $q->id)->firstOrFail();
        $this->assertSame('go', $tq->decisions['port_helix_rotterdam'] ?? null);
        $this->assertSame('go', $tq->decisions['port_euro_retail_divest'] ?? null);
        $this->assertArrayNotHasKey('proj_helix', $tq->decisions, 'the 2028 project list is closed');
        // The Kessana answer reaches the server the same way, in its own quarter.
        $this->assertSame('counter', app(DecisionBook::class)->validatePage('oil_fields', ['kessana_position' => 'counter'], 11)[0]['kessana_position'] ?? null);
        $this->assertArrayNotHasKey('kessana_position', app(DecisionBook::class)->validatePage('oil_fields', ['kessana_position' => 'counter'], 12)[0]);
    }

    public function test_the_board_quarter_takes_a_defense_and_refuses_page_saves_and_the_instructor_publishes_the_verdict(): void
    {
        $runner = app(QuarterRunner::class);
        for ($n = 1; $n <= 13; $n++) {
            $q = $this->quarter($n);
            $runner->open($q);
            $runner->close($q->refresh());
            $runner->publish($q->refresh());
        }
        $q = $this->quarter(14);
        $runner->open($q);
        $student = $this->students['refineries'];
        $this->actingAs($student)->from("/play/{$q->id}")
            ->post("/play/{$q->id}/page/oil_fields", ['rigs' => 9])
            ->assertSessionHasErrors(['oil_fields']);
        $this->actingAs($student)->post("/play/{$q->id}/defense", ['synthesis' => 'One company.', 'decisions' => 'Three.', 'counterfactual' => 'Fewer rigs.'])
            ->assertSessionHasNoErrors();
        $tq = TeamQuarter::query()->where('team_id', $this->team->id)->where('quarter_id', $q->id)->firstOrFail();
        $this->assertSame('Three.', $tq->defense['decisions'] ?? null);
        $this->assertStringContainsString('Three decisions, defended:', (string) $tq->memo, 'mirrored into the memo for the drafting tools');
        // The verdict waits for the quarter to run, then goes out when the instructor says so.
        $this->actingAs($this->faculty)
            ->post("/faculty/teams/{$this->team->id}/quarters/{$q->id}/verdict?section={$this->section->id}", ['reasoning' => 'strong', 'publish' => true])
            ->assertNotFound();
        $runner->close($q->refresh());
        $runner->publish($q->refresh());
        $this->actingAs($this->faculty)
            ->post("/faculty/teams/{$this->team->id}/quarters/{$q->id}/verdict?section={$this->section->id}", ['reasoning' => 'strong', 'publish' => true])
            ->assertSessionHasNoErrors();
        $tq->refresh();
        $this->assertSame('widen', $tq->verdict, 'strong reasoning and, alone in its class, strong outcomes');
        $this->assertNotNull($tq->verdict_published_at);
        $this->actingAs($this->students['evp'])->get("/play/{$q->id}")->assertInertia(fn (Assert $page) => $page
            ->where('board.verdict.title', 'They keep the job and give you more.')
            ->where('pages', []));
    }

    public function test_the_memo_saves_in_full(): void
    {
        $q = $this->openFirst();
        $memo = str_repeat('We cut rigs because the twelfth one costs more than it brings in. ', 60);
        $this->actingAs($this->students['trading_finance'])->post("/play/{$q->id}/memo", ['memo' => $memo])->assertSessionHasNoErrors();

        $tq = TeamQuarter::query()->where('team_id', $this->team->id)->where('quarter_id', $q->id)->firstOrFail();
        $this->assertSame(trim($memo), $tq->memo, 'only the outer spaces are trimmed');
    }

    public function test_only_the_evp_can_mark_the_team_ready(): void
    {
        $q = $this->openFirst();
        $this->actingAs($this->students['refineries'])->post("/play/{$q->id}/ready")
            ->assertSessionHasErrors(['ready' => 'Only the EVP on your team can do this.']);

        $this->actingAs($this->students['evp'])->post("/play/{$q->id}/ready")->assertSessionHasNoErrors();
        $this->assertNotNull(TeamQuarter::query()->where('team_id', $this->team->id)->firstOrFail()->ready_at);
    }

    public function test_a_closed_quarter_cannot_be_changed(): void
    {
        $q = $this->openFirst();
        app(QuarterRunner::class)->close($q);
        $this->actingAs($this->students['oil_fields'])->post("/play/{$q->id}/page/oil_fields", ['rigs' => 9])
            ->assertSessionHasErrors(['quarter']);
    }

    public function test_students_cannot_see_another_class(): void
    {
        $other = Section::query()->create(['name' => 'Other', 'course_name' => 'Econ', 'weeks' => 14, 'faculty_user_id' => $this->faculty->id]);
        $q = Quarter::query()->create(['section_id' => $other->id, 'number' => 1, 'company_quarter' => Quarter::companyQuarterFor(1)]);
        $this->actingAs($this->students['evp'])->get("/play/{$q->id}")->assertNotFound();
        $this->actingAs($this->students['evp'])->get('/faculty')->assertForbidden();
    }

    public function test_reading_files_unlock_with_their_quarter(): void
    {
        $student = $this->students['evp'];
        $this->actingAs($student)->get('/files/q1/halden-q1-asset-register.xlsx')->assertNotFound();

        $this->openFirst();
        $this->actingAs($student)->get('/files/q1/halden-q1-asset-register.xlsx')->assertOk();
        $this->actingAs($student)->get('/files/q4/halden-q4-crude-price.xlsx')->assertNotFound();
        $this->actingAs($student)->get('/files/../.env')->assertNotFound();
        $this->actingAs($student)->get('/files/q1/../../operating-model/MANIFEST.json')->assertNotFound();

        // Faculty can read any quarter's files ahead of time.
        $this->actingAs($this->faculty)->get('/files/q1/halden-q1-asset-register.xlsx')->assertOk();
    }

    public function test_faculty_run_the_quarter_from_the_board(): void
    {
        $this->actingAs($this->faculty)->get('/faculty')->assertInertia(fn (Assert $page) => $page
            ->component('halden/FacultyBoard')
            ->where('quarter.number', 1)
            ->where('quarter.status', 'upcoming')
            ->has('teams', 1));

        $q1 = $this->quarter(1);
        $this->actingAs($this->faculty)->post("/faculty/quarters/{$q1->id}/open")->assertSessionHasNoErrors();
        $this->assertSame(Quarter::OPEN, $q1->refresh()->status);

        $this->actingAs($this->faculty)->post("/faculty/quarters/{$q1->id}/close")->assertSessionHasNoErrors();
        $this->actingAs($this->faculty)->post("/faculty/quarters/{$q1->id}/publish")->assertSessionHasNoErrors();
        $this->assertSame(Quarter::PUBLISHED, $q1->refresh()->status);

        $q3 = $this->quarter(3);
        $this->actingAs($this->faculty)->from('/faculty')->post("/faculty/quarters/{$q3->id}/open")->assertSessionHasErrors(['action']);

        // With two quarters published, the board shows the latest one and offers the next (it once showed Quarter 1 again).
        $q2 = $this->quarter(2);
        foreach (['open', 'close', 'publish'] as $step) {
            $this->actingAs($this->faculty)->post("/faculty/quarters/{$q2->id}/$step")->assertSessionHasNoErrors();
        }
        $this->actingAs($this->faculty)->get('/faculty')->assertInertia(fn (Assert $page) => $page
            ->where('quarter.number', 2)
            ->where('quarter.status', 'published')
            ->where('next.label', 'Q3 2027'));

        $this->actingAs($this->faculty)->get("/faculty/teams/{$this->team->id}/quarters/{$q1->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('halden/Play')
                ->where('readOnly', true)
                ->where('canEdit', false)
                ->has('results.story.paragraphs'));

        $this->actingAs($this->students['evp'])->get("/play/{$q1->id}")
            ->assertInertia(fn (Assert $page) => $page->has('results.bridge.parts', 3));
    }

    public function test_faculty_can_set_the_class_draws_ahead_of_time_or_leave_them_to_chance(): void
    {
        // Quarter 7 published, Quarter 8 (OPEC+) next: the board offers that one draw.
        $this->section->quarters()->where('number', '<=', 7)->update(['status' => Quarter::PUBLISHED]);
        $q8 = $this->quarter(8);
        $this->actingAs($this->faculty)->get('/faculty')->assertInertia(fn (Assert $page) => $page
            ->has('draws', 1)
            ->where('draws.0.key', 'opec')
            ->where('draws.0.quarterLabel', 'Q4 2028')
            ->where('draws.0.settable', true)
            ->where('draws.0.value', null)
            ->where('draws.0.options.2.label', 'The cut falls apart')
            ->where('draws.0.options.2.chance', 25));

        $this->actingAs($this->faculty)->post("/faculty/quarters/{$q8->id}/draws", ['draw' => 'opec', 'value' => 'fails'])->assertSessionHasNoErrors();
        $this->assertSame('fails', $q8->refresh()->event_outcome);
        $this->actingAs($this->faculty)->from('/faculty')->post("/faculty/quarters/{$q8->id}/draws", ['draw' => 'opec', 'value' => 'maybe'])->assertSessionHasErrors(['draw']);
        $this->actingAs($this->faculty)->post("/faculty/quarters/{$q8->id}/draws", ['draw' => 'opec', 'value' => ''])->assertSessionHasNoErrors();
        $this->assertNull($q8->refresh()->event_outcome);

        // A quarter without that draw refuses it; a quarter that has run is settled.
        $this->actingAs($this->faculty)->from('/faculty')->post('/faculty/quarters/'.$this->quarter(3)->id.'/draws', ['draw' => 'opec', 'value' => 'full'])->assertSessionHasErrors(['draw']);
        $q8->update(['status' => Quarter::CLOSED, 'event_outcome' => 'partial']);
        $this->actingAs($this->faculty)->from('/faculty')->post("/faculty/quarters/{$q8->id}/draws", ['draw' => 'opec', 'value' => 'full'])->assertSessionHasErrors(['draw']);
        $this->assertSame('partial', $q8->refresh()->event_outcome);

        // Quarter 13 published, the board quarter next: the breakdown and the world, both open to be set; a preset world survives opening.
        $this->section->quarters()->where('number', '<=', 13)->update(['status' => Quarter::PUBLISHED]);
        $q14 = $this->quarter(14);
        $this->actingAs($this->faculty)->get('/faculty')->assertInertia(fn (Assert $page) => $page
            ->has('draws', 2)
            ->where('draws.0.key', 'outage')
            ->where('draws.0.options.1.chance', 12)
            ->where('draws.1.key', 'world')
            ->has('draws.1.options', 9)
            ->where('draws.1.options.0.value', 'low:slow'));
        $this->actingAs($this->faculty)->post("/faculty/quarters/{$q14->id}/draws", ['draw' => 'world', 'value' => 'high:collapse'])->assertSessionHasNoErrors();
        app(QuarterRunner::class)->open($q14->refresh());
        $this->assertSame('high:collapse', $q14->refresh()->world);
        $this->actingAs($this->faculty)->post("/faculty/quarters/{$q14->id}/draws", ['draw' => 'outage', 'value' => 'outage'])->assertSessionHasNoErrors();
        $this->assertSame('outage', $q14->refresh()->event_outcome);
    }

    public function test_faculty_cannot_run_another_instructors_class(): void
    {
        $stranger = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $theirs = Section::query()->create(['name' => 'Theirs', 'course_name' => 'Econ', 'weeks' => 14, 'faculty_user_id' => $stranger->id]);
        Quarter::query()->create(['section_id' => $theirs->id, 'number' => 1, 'company_quarter' => Quarter::companyQuarterFor(1)]);

        $this->actingAs($stranger)->post('/faculty/quarters/'.$this->quarter(1)->id.'/open')->assertNotFound();
        $this->actingAs($stranger)->get("/faculty/teams/{$this->team->id}/quarters/".$this->quarter(1)->id)->assertNotFound();
    }
}
