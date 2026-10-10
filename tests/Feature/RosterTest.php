<?php

namespace Tests\Feature;

use App\Halden\Admin\ClassFactory;
use App\Halden\Admin\Roster;
use App\Halden\Game\QuarterRunner;
use App\Models\Enrolment;
use App\Models\Quarter;
use App\Models\Section;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamQuarter;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RosterTest extends TestCase
{
    use RefreshDatabase;

    private User $faculty;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();
        $this->faculty = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $this->section = app(ClassFactory::class)->create('MBA 7250', 'Managerial Economics', 14, $this->faculty, CarbonImmutable::parse('2027-01-14 22:00:00'), 0, 12);
    }

    public function test_the_pasted_list_is_read_in_every_common_shape(): void
    {
        $parsed = Roster::parse("email,name\nada@u.edu\nGrace Hopper <grace@u.edu>\n\"Lin, Mei\", mei@u.edu\nbob@u.edu, Bob Ng\nADA@u.edu\nnot an address\n");
        $this->assertSame([
            ['email' => 'ada@u.edu', 'name' => 'ada'],
            ['email' => 'grace@u.edu', 'name' => 'Grace Hopper'],
            ['email' => 'mei@u.edu', 'name' => 'Lin Mei'],
            ['email' => 'bob@u.edu', 'name' => 'Bob Ng'],
        ], $parsed['people']);
        $this->assertSame(['not an address'], $parsed['rejected']);
    }

    public function test_an_instructor_adds_students_forms_teams_and_moves_them(): void
    {
        $existing = User::factory()->create(['role' => User::ROLE_STUDENT, 'email' => 'old@u.edu', 'name' => 'Old Hand']);
        $this->actingAs($this->faculty)->get('/faculty/roster')->assertInertia(fn (Assert $p) => $p->component('halden/FacultyRoster')->has('students', 0)->has('teams', 0));

        $list = implode("\n", array_map(fn (int $i) => "Student $i <s$i@u.edu>", range(1, 11)))."\nold@u.edu\n".$this->faculty->email;
        $this->actingAs($this->faculty)->post('/faculty/roster/students', ['list' => $list])->assertSessionHasNoErrors()->assertRedirect("/faculty/roster?section={$this->section->id}");
        $added = session('added');
        $this->assertCount(13, $added);
        $this->assertSame(12, $this->section->enrolments()->count(), 'the instructor email is skipped');
        $this->assertSame(11, count(array_filter($added, fn ($a) => $a['password'] !== null)), 'new logins get a password shown once');
        $old = collect($added)->firstWhere('email', 'old@u.edu');
        $this->assertNull($old['password'], 'an existing student keeps their password');
        $this->assertSame('Old Hand', $old['name']);
        $this->assertStringContainsString('instructor', collect($added)->firstWhere('email', $this->faculty->email)['note']);
        $new = User::query()->where('email', 's1@u.edu')->firstOrFail();
        $this->assertTrue($new->isStudent());
        $this->assertTrue(Hash::check(collect($added)->firstWhere('email', 's1@u.edu')['password'], $new->password));

        // Twelve seats: a thirteenth is refused, adding someone already in is harmless.
        $this->actingAs($this->faculty)->post('/faculty/roster/students', ['list' => "s1@u.edu\nextra@u.edu"])->assertSessionHasNoErrors();
        $this->assertSame(12, $this->section->enrolments()->count());
        $this->assertStringContainsString('full', collect(session('added'))->firstWhere('email', 'extra@u.edu')['note']);
        $this->assertNull(User::query()->where('email', 'extra@u.edu')->first(), 'no login is made for a student who could not be added');

        // Form teams: 12 students become two teams of five plus two sixths on the shared seat.
        $this->actingAs($this->faculty)->post('/faculty/roster/form-teams')->assertSessionHasNoErrors()->assertSessionHas('done', 'placed:12');
        $teams = $this->section->teams()->withCount('members')->orderBy('name')->get();
        $this->assertSame(['Team A', 'Team B'], $teams->pluck('name')->all());
        $this->assertSame([6, 6], $teams->pluck('members_count')->all());
        foreach ($teams as $team) {
            $seats = $team->members()->pluck('seat')->sort()->values()->all();
            $this->assertSame(['evp', 'gas_stations', 'oil_fields', 'refineries', 'trading_finance', 'trading_finance'], $seats);
        }
        $this->actingAs($this->faculty)->post('/faculty/roster/form-teams')->assertSessionHas('done', 'nobody-to-place');

        // Move a sixth to a new empty team: they take its first free seat (EVP). Re-seat by hand. Off the team entirely.
        $this->actingAs($this->faculty)->post('/faculty/roster/teams')->assertSessionHasNoErrors();
        $teamC = Team::query()->where('section_id', $this->section->id)->where('name', 'Team C')->firstOrFail();
        $sixth = $teams[0]->members()->where('seat', 'trading_finance')->orderByDesc('id')->firstOrFail()->user;
        $this->actingAs($this->faculty)->post("/faculty/roster/students/{$sixth->id}/move", ['team' => $teamC->id])->assertSessionHasNoErrors();
        $this->assertSame('evp', TeamMember::query()->where('user_id', $sixth->id)->firstOrFail()->seat);
        $this->assertSame(5, $teams[0]->members()->count());
        $this->actingAs($this->faculty)->post("/faculty/roster/students/{$sixth->id}/move", ['team' => $teamC->id, 'seat' => 'refineries'])->assertSessionHasNoErrors();
        $this->assertSame('refineries', TeamMember::query()->where('user_id', $sixth->id)->firstOrFail()->seat);
        $this->actingAs($this->faculty)->post("/faculty/roster/students/{$sixth->id}/move", ['team' => null])->assertSessionHasNoErrors();
        $this->assertNull(TeamMember::query()->where('user_id', $sixth->id)->first());
        $this->assertTrue(Enrolment::query()->where('user_id', $sixth->id)->exists(), 'off the team, still in the class');

        // Rename; an empty team can be deleted, a team with members can't.
        $this->actingAs($this->faculty)->post("/faculty/roster/teams/{$teamC->id}", ['name' => 'Team A'])->assertSessionHasErrors(['name']);
        $this->actingAs($this->faculty)->post("/faculty/roster/teams/{$teamC->id}", ['name' => 'Nordlys'])->assertSessionHasNoErrors();
        $this->assertSame('Nordlys', $teamC->refresh()->name);
        $this->actingAs($this->faculty)->delete("/faculty/roster/teams/{$teams[0]->id}")->assertSessionHasErrors(['roster']);
        $this->actingAs($this->faculty)->delete("/faculty/roster/teams/{$teamC->id}")->assertSessionHasNoErrors();
        $this->assertNull(Team::query()->find($teamC->id));

        // Another instructor can't touch this class's students.
        $other = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $this->actingAs($other)->get('/faculty/roster')->assertNotFound();
        $this->actingAs($existing)->get('/faculty/roster')->assertForbidden();
    }

    public function test_pausing_removing_and_resetting_a_student(): void
    {
        $roster = app(Roster::class);
        $roster->add($this->section, [['email' => 'ada@u.edu', 'name' => 'Ada']]);
        $ada = User::query()->where('email', 'ada@u.edu')->firstOrFail();
        $ada->forceFill(['opening_seen_at' => now()])->save();

        // Not on a team: the waiting page, not an error.
        $this->actingAs($ada)->get('/play')->assertInertia(fn (Assert $p) => $p->component('halden/Waiting')->where('reason', 'no-team')->where('className', 'MBA 7250'));

        $roster->formTeams($this->section);
        $team = $this->section->teams()->firstOrFail();
        $this->section->quarters()->where('number', 1)->update(['status' => Quarter::OPEN]);
        $this->actingAs($ada)->get('/play')->assertRedirect();

        $this->actingAs($this->faculty)->post("/faculty/roster/students/{$ada->id}/block")->assertSessionHasNoErrors();
        $this->actingAs($ada)->get('/play')->assertInertia(fn (Assert $p) => $p->component('halden/Waiting')->where('reason', 'blocked'));
        $q1 = $this->section->quarters()->where('number', 1)->firstOrFail();
        $this->actingAs($ada)->get("/play/{$q1->id}")->assertForbidden();
        $this->actingAs($this->faculty)->post("/faculty/roster/students/{$ada->id}/unblock")->assertSessionHasNoErrors();
        $this->actingAs($ada)->get("/play/{$q1->id}")->assertOk();

        $this->actingAs($this->faculty)->post("/faculty/roster/students/{$ada->id}/reset-password")->assertSessionHasNoErrors();
        $password = session('new_password');
        $this->assertSame('ada@u.edu', $password['email']);
        $this->assertTrue(Hash::check($password['password'], $ada->refresh()->password));

        $this->actingAs($this->faculty)->post("/faculty/roster/students/{$ada->id}/remove")->assertSessionHasNoErrors();
        $this->assertNull(TeamMember::query()->where('user_id', $ada->id)->first());
        $this->assertNull(Enrolment::query()->where('user_id', $ada->id)->first());
        $this->assertNotNull(User::query()->find($ada->id), 'the login stays');
        $this->assertNotNull(Team::query()->find($team->id), 'the team stays');
        $this->actingAs($ada)->get('/play')->assertInertia(fn (Assert $p) => $p->component('halden/Waiting')->where('reason', 'no-class'));
        $this->actingAs($this->faculty)->post("/faculty/roster/students/{$ada->id}/block")->assertSessionHasErrors(['roster']);
    }

    public function test_students_join_by_link_and_a_csv_upload_adds_them_straight_to_a_team(): void
    {
        $this->get('/join/nosuchcode')->assertNotFound();
        $this->actingAs($this->faculty)->post('/faculty/roster/join-link')->assertSessionHasNoErrors();
        $code = $this->section->refresh()->join_code;
        $this->assertNotNull($code);
        $this->actingAs($this->faculty)->get('/faculty/roster')->assertInertia(fn (Assert $p) => $p->where('section.joinUrl', url("/join/$code")));

        // Signed out: the join page makes a student login and lands them in the opening.
        $this->post('/logout');
        $this->get("/join/$code")->assertInertia(fn (Assert $p) => $p->component('auth/Join')->where('className', 'MBA 7250')->where('full', false));
        $this->post("/join/$code", ['name' => 'Mei Lin', 'email' => 'Mei@u.edu', 'password' => 'correct horse battery', 'password_confirmation' => 'correct horse battery'])
            ->assertSessionHasNoErrors()->assertRedirect('/opening');
        $mei = User::query()->where('email', 'mei@u.edu')->firstOrFail();
        $this->assertTrue($mei->isStudent());
        $this->assertAuthenticatedAs($mei);
        $this->assertSame(Enrolment::ACTIVE, Enrolment::query()->where('user_id', $mei->id)->where('section_id', $this->section->id)->firstOrFail()->status);
        $this->post('/logout');

        // The same email again is told to log in; a signed-in student who opens the link is enrolled.
        $this->post("/join/$code", ['name' => 'Mei', 'email' => 'mei@u.edu', 'password' => 'correct horse battery', 'password_confirmation' => 'correct horse battery'])->assertSessionHasErrors(['email']);
        $other = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $this->actingAs($other)->get("/join/$code")->assertRedirect('/play');
        $this->assertTrue(Enrolment::query()->where('user_id', $other->id)->exists());
        $this->actingAs($this->faculty)->get("/join/$code")->assertInertia(fn (Assert $p) => $p->component('auth/Join')->where('problem', 'This link is for students. You are signed in as an instructor.'));

        // Switching the link off kills it; a fresh one is different.
        $this->actingAs($this->faculty)->post('/faculty/roster/join-link', ['off' => true])->assertSessionHasNoErrors();
        $this->assertNull($this->section->refresh()->join_code);
        $this->get("/join/$code")->assertNotFound();
        $this->actingAs($this->faculty)->post('/faculty/roster/join-link');
        $this->assertNotSame($code, $this->section->refresh()->join_code);

        // A CSV upload straight onto a team gives seats in order.
        $this->actingAs($this->faculty)->post('/faculty/roster/teams');
        $team = $this->section->teams()->firstOrFail();
        $csv = UploadedFile::fake()->createWithContent('students.csv', "name,email\nAnn,ann@u.edu\nBen,ben@u.edu\n");
        $this->actingAs($this->faculty)->post('/faculty/roster/students', ['file' => $csv, 'team' => $team->id])->assertSessionHasNoErrors();
        $this->assertSame(['evp', 'oil_fields'], $team->members()->orderBy('id')->pluck('seat')->all());
        $this->assertSame(4, $this->section->enrolments()->count());

        // The seat cap also closes the join link.
        $this->section->update(['seats' => 4]);
        $this->get('/join/'.$this->section->join_code)->assertInertia(fn (Assert $p) => $p->where('full', true));
        $this->post('/join/'.$this->section->join_code, ['name' => 'Late', 'email' => 'late@u.edu', 'password' => 'correct horse battery', 'password_confirmation' => 'correct horse battery'])
            ->assertSessionHasErrors(['email']);
    }

    public function test_forming_teams_uses_the_empty_teams_an_admin_made_and_tops_up_part_teams(): void
    {
        $section = app(ClassFactory::class)->create('Premade', 'Managerial Economics', 14, $this->faculty, CarbonImmutable::now(), 4);
        $roster = app(Roster::class);
        $roster->add($section, array_map(fn (int $i) => ['email' => "p$i@u.edu", 'name' => "P $i"], range(1, 13)));
        $roster->formTeams($section);
        $counts = $section->teams()->withCount('members')->orderBy('name')->get()->pluck('members_count', 'name')->all();
        $this->assertSame(['Team A' => 5, 'Team B' => 5, 'Team C' => 3, 'Team D' => 0], $counts, 'empty teams are used before new ones; three left over make a small team');

        // A student leaves Team A; two more arrive: the empty seat on Team A fills first, the other goes to the small team.
        $gone = $section->teams()->where('name', 'Team A')->firstOrFail()->members()->where('seat', 'refineries')->firstOrFail()->user;
        $roster->remove($section, $gone);
        $roster->add($section, [['email' => 'late1@u.edu', 'name' => 'Late'], ['email' => 'late2@u.edu', 'name' => 'Later']]);
        $roster->formTeams($section);
        $teamA = $section->teams()->where('name', 'Team A')->firstOrFail();
        $this->assertSame(5, $teamA->members()->count());
        $this->assertSame(1, $teamA->members()->where('seat', 'refineries')->count());
        $this->assertSame(4, $section->teams()->where('name', 'Team C')->firstOrFail()->members()->count());
    }

    public function test_an_instructor_with_two_classes_switches_between_them_and_the_choice_sticks(): void
    {
        $second = app(ClassFactory::class)->create('MBA 7250 Evening', 'Managerial Economics', 7, $this->faculty, CarbonImmutable::now());
        $this->actingAs($this->faculty)->get('/faculty')->assertInertia(fn (Assert $p) => $p->where('section.id', $this->section->id)->has('classes', 2));
        $this->actingAs($this->faculty)->get("/faculty?section={$second->id}")->assertInertia(fn (Assert $p) => $p->where('section.name', 'MBA 7250 Evening'));
        // Without ?section= the last class opened comes back, on the board and the roster alike.
        $this->actingAs($this->faculty)->get('/faculty')->assertInertia(fn (Assert $p) => $p->where('section.id', $second->id));
        $this->actingAs($this->faculty)->get('/faculty/roster')->assertInertia(fn (Assert $p) => $p->where('section.id', $second->id)->has('classes', 2));
        // A class that isn't theirs falls back to the first.
        $stranger = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $other = app(ClassFactory::class)->create('Someone else', 'x', 14, $stranger, CarbonImmutable::now());
        $this->actingAs($this->faculty)->get("/faculty?section={$other->id}")->assertInertia(fn (Assert $p) => $p->where('section.id', $this->section->id));
        $this->actingAs($stranger)->get('/faculty')->assertInertia(fn (Assert $p) => $p->where('section.id', $other->id)->has('classes', 1));
    }

    public function test_a_student_in_two_classes_switches_between_them_and_opens_either_quarter(): void
    {
        $roster = app(Roster::class);
        $roster->add($this->section, [['email' => 'ada@u.edu', 'name' => 'Ada']]);
        $roster->formTeams($this->section);
        $second = app(ClassFactory::class)->create('MBA 7250 Evening', 'Managerial Economics', 14, $this->faculty, CarbonImmutable::now());
        $roster->add($second, [['email' => 'ada@u.edu', 'name' => 'Ada']]);
        $roster->formTeams($second);
        $ada = User::query()->where('email', 'ada@u.edu')->firstOrFail();
        $ada->forceFill(['opening_seen_at' => now()])->save();
        $runner = app(QuarterRunner::class);
        $q1a = $this->section->quarters()->where('number', 1)->firstOrFail();
        $q1b = $second->quarters()->where('number', 1)->firstOrFail();
        $runner->open($q1a);
        $runner->open($q1b);

        // The first class by default, with both listed; ?class= switches and the choice sticks.
        $this->actingAs($ada)->get('/play')->assertRedirect("/play/{$q1a->id}");
        $this->actingAs($ada)->get("/play/{$q1a->id}")->assertInertia(fn (Assert $p) => $p->where('section.id', $this->section->id)->has('section.classes', 2));
        $this->actingAs($ada)->get("/play?class={$second->id}")->assertRedirect("/play/{$q1b->id}");
        $this->actingAs($ada)->get('/play')->assertRedirect("/play/{$q1b->id}");
        // A quarter of the other class opens as that class's team.
        $this->actingAs($ada)->get("/play/{$q1a->id}")->assertOk()->assertInertia(fn (Assert $p) => $p->where('section.id', $this->section->id));
        $this->actingAs($ada)->post("/play/{$q1a->id}/page/oil_fields", ['rigs' => 12])->assertSessionHasNoErrors();
        $this->assertSame(12, TeamQuarter::query()->where('quarter_id', $q1a->id)->firstOrFail()->decisions['rigs']);
        $this->assertNull(TeamQuarter::query()->where('quarter_id', $q1b->id)->first());
    }

    public function test_demo_students_are_enrolled_too(): void
    {
        $section = app(ClassFactory::class)->create('Rehearsal', 'Managerial Economics', 14, $this->faculty, CarbonImmutable::now(), 2);
        app(ClassFactory::class)->fillWithDemoStudents($section, 'password');
        $this->assertSame(10, $section->enrolments()->count());
        $this->assertSame(10, TeamMember::query()->whereIn('team_id', $section->teams()->select('id'))->count());
    }
}
