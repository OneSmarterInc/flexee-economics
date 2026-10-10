<?php

namespace Tests\Feature;

use App\Halden\Admin\ClassFactory;
use App\Halden\Admin\Roster;
use App\Halden\Game\QuarterRunner;
use App\Mail\HaldenMail;
use App\Models\Quarter;
use App\Models\TeamQuarter;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OutgoingMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_nothing_is_sent_while_mail_is_off(): void
    {
        Mail::fake();
        config(['halden.mail_enabled' => false]);
        $faculty = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $section = app(ClassFactory::class)->create('MBA 7250', 'Managerial Economics', 14, $faculty, CarbonImmutable::now()->addDay());
        $added = app(Roster::class)->add($section, [['email' => 'ada@u.edu', 'name' => 'Ada']], null, true);
        $this->assertSame('New login.', $added[0]['note']);
        Mail::assertNothingSent();
        $this->actingAs($faculty)->get('/faculty/roster')->assertInertia(fn ($p) => $p->where('mailEnabled', false));
        $q1 = $section->quarters()->where('number', 1)->firstOrFail();
        app(QuarterRunner::class)->open($q1);
        $this->actingAs($faculty)->post("/faculty/quarters/{$q1->id}/remind")->assertSessionHasErrors(['action']);
    }

    public function test_new_students_get_their_login_by_email_when_the_instructor_asks(): void
    {
        Mail::fake();
        config(['halden.mail_enabled' => true]);
        $faculty = User::factory()->create(['role' => User::ROLE_FACULTY, 'name' => 'Pat Instructor']);
        $section = app(ClassFactory::class)->create('MBA 7250', 'Managerial Economics', 14, $faculty, CarbonImmutable::now()->addDay());
        User::factory()->create(['role' => User::ROLE_STUDENT, 'email' => 'old@u.edu', 'name' => 'Old Hand']);

        $this->actingAs($faculty)->post('/faculty/roster/students', ['list' => "Ada Lovelace <ada@u.edu>\nold@u.edu", 'email' => true])->assertSessionHasNoErrors();
        $added = collect(session('added'));
        $this->assertSame('New login. Emailed.', $added->firstWhere('email', 'ada@u.edu')['note']);
        $this->assertSame('Had a login already; added to the class. Emailed.', $added->firstWhere('email', 'old@u.edu')['note']);
        $password = $added->firstWhere('email', 'ada@u.edu')['password'];
        Mail::assertSent(HaldenMail::class, fn (HaldenMail $m) => $m->hasTo('ada@u.edu') && $m->subjectLine === 'Your Halden Energy login for MBA 7250'
            && str_contains(implode("\n", $m->lines), "Password: $password") && str_contains(implode("\n", $m->lines), 'Pat Instructor'));
        Mail::assertSent(HaldenMail::class, fn (HaldenMail $m) => $m->hasTo('old@u.edu') && str_starts_with($m->subjectLine, "You've been added"));
        Mail::assertSentCount(2);

        // Without the box ticked, nothing goes out; a student already in the class is never re-emailed.
        $this->actingAs($faculty)->post('/faculty/roster/students', ['list' => "ada@u.edu\nnew2@u.edu", 'email' => false])->assertSessionHasNoErrors();
        Mail::assertSentCount(2);
        $rendered = (new HaldenMail('S', ['One.', 'Two.']))->render();
        $this->assertStringContainsString("One.\n\nTwo.", $rendered);
    }

    public function test_deadline_reminders_go_once_to_teams_that_are_not_ready_and_the_board_can_nudge(): void
    {
        Mail::fake();
        config(['halden.mail_enabled' => true]);
        $faculty = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $deadline = CarbonImmutable::parse('2027-01-14 22:00:00');
        $section = app(ClassFactory::class)->create('MBA 7250', 'Managerial Economics', 14, $faculty, $deadline);
        $roster = app(Roster::class);
        $roster->add($section, array_map(fn (int $i) => ['email' => "s$i@u.edu", 'name' => "S $i"], range(1, 10)));
        $roster->formTeams($section);
        [$a, $b] = $section->teams()->orderBy('name')->get()->all();
        $q1 = $section->quarters()->where('number', 1)->firstOrFail();
        app(QuarterRunner::class)->open($q1);
        TeamQuarter::query()->firstOrCreate(['team_id' => $a->id, 'quarter_id' => $q1->id])->update(['ready_at' => now()]);

        // Two days out: nothing. 23 hours out: the day reminder to Team B's five.
        $this->travelTo($deadline->subDays(2));
        $this->artisan('halden:remind')->assertSuccessful();
        Mail::assertNothingSent();
        $this->travelTo($deadline->subHours(23));
        $this->artisan('halden:remind')->assertSuccessful();
        Mail::assertSentCount(5);
        Mail::assertSent(HaldenMail::class, fn (HaldenMail $m) => $m->subjectLine === 'MBA 7250: Q1 2027 closes in a day' && str_contains(implode(' ', $m->lines), 'Thursday 14 January, 5:00 pm'));
        $this->artisan('halden:remind')->assertSuccessful();
        Mail::assertSentCount(5);
        $this->assertSame(['24h'], DB::table('quarter_reminders')->where('quarter_id', $q1->id)->pluck('kind')->all());

        // A member of Team B is paused: left out. 3 hours out: the four-hour reminder.
        $roster->block($section, $b->members()->first()->user, true);
        $this->travelTo($deadline->subHours(3));
        $this->artisan('halden:remind')->assertSuccessful();
        Mail::assertSentCount(9);
        $this->assertSame(['24h', '4h'], DB::table('quarter_reminders')->where('quarter_id', $q1->id)->orderBy('id')->pluck('kind')->all());

        // The board's own nudge, any time while open.
        $this->actingAs($faculty)->post("/faculty/quarters/{$q1->id}/remind")->assertSessionHasNoErrors()->assertSessionHas('done', 'reminded:4');
        Mail::assertSent(HaldenMail::class, fn (HaldenMail $m) => str_contains($m->subjectLine, 'a note from your instructor'));

        // Past the deadline the quarter is closed by the cron; nothing more.
        $this->travelTo($deadline->addMinutes(2));
        $this->artisan('halden:close-due')->assertSuccessful();
        $this->assertSame(Quarter::CLOSED, $q1->refresh()->status);
        $this->artisan('halden:remind')->assertSuccessful();
        Mail::assertSentCount(13);
    }

    public function test_a_quarter_opened_inside_a_window_gets_only_the_nearest_reminder(): void
    {
        Mail::fake();
        config(['halden.mail_enabled' => true]);
        $faculty = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $deadline = CarbonImmutable::parse('2027-01-14 22:00:00');
        $section = app(ClassFactory::class)->create('MBA 7250', 'Managerial Economics', 14, $faculty, $deadline);
        $roster = app(Roster::class);
        $roster->add($section, [['email' => 'one@u.edu', 'name' => 'One']]);
        $roster->formTeams($section);
        $q1 = $section->quarters()->where('number', 1)->firstOrFail();
        $this->travelTo($deadline->subHours(2));
        app(QuarterRunner::class)->open($q1);
        $this->artisan('halden:remind')->assertSuccessful();
        Mail::assertSentCount(1);
        Mail::assertSent(HaldenMail::class, fn (HaldenMail $m) => str_ends_with($m->subjectLine, 'closes in four hours'));
        $this->assertSame(['24h', '4h'], DB::table('quarter_reminders')->where('quarter_id', $q1->id)->orderBy('id')->pluck('kind')->all());
        $this->travelTo($deadline->subMinutes(50));
        $this->artisan('halden:remind')->assertSuccessful();
        Mail::assertSentCount(2);
    }
}
