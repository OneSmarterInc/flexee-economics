<?php

namespace Tests\Feature;

use App\Halden\Game\QuarterRunner;
use App\Models\Quarter;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminClassesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $faculty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->faculty = User::factory()->create(['role' => User::ROLE_FACULTY, 'name' => 'Pat Instructor']);
    }

    public function test_an_admin_creates_a_class_with_its_quarters_and_changes_its_settings(): void
    {
        $this->actingAs($this->faculty)->get('/admin')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin')->assertInertia(fn (Assert $p) => $p
            ->component('halden/AdminClasses')->has('classes', 0)->has('instructors', 2));

        $this->actingAs($this->admin)->post('/admin/classes', [
            'name' => 'MBA 7250 Spring 2027', 'course' => 'Managerial Economics', 'weeks' => 7, 'instructor' => $this->faculty->id,
            'first_deadline' => '2027-01-14T17:00', 'teams' => 4, 'seats' => 20,
        ])->assertSessionHasNoErrors();
        $section = Section::query()->where('name', 'MBA 7250 Spring 2027')->firstOrFail();
        $this->assertSame(7, (int) $section->weeks);
        $this->assertSame(20, (int) $section->seats);
        $this->assertSame(4, $section->teams()->count());
        $this->assertSame(['Team A', 'Team B', 'Team C', 'Team D'], $section->teams()->orderBy('name')->pluck('name')->all());
        $this->assertSame(14, $section->quarters()->count());
        $q1 = $section->quarters()->where('number', 1)->firstOrFail();
        $this->assertSame('2027-01-14 22:00:00', $q1->deadline_at?->toDateTimeString(), 'Eastern 5 pm in UTC');
        $this->assertNull($section->quarters()->where('number', 2)->firstOrFail()->deadline_at, 'a 7-week class puts the deadline on the first of each pair');
        $this->assertSame('2027-01-21 22:00:00', $section->quarters()->where('number', 3)->firstOrFail()->deadline_at?->toDateTimeString());
        $this->assertSame('2027-02-25 22:00:00', $section->quarters()->where('number', 13)->firstOrFail()->deadline_at?->toDateTimeString());

        // A second class with the same name is refused; the instructor can see their class on the board, the admin sees both screens.
        $this->actingAs($this->admin)->from('/admin')->post('/admin/classes', ['name' => 'MBA 7250 Spring 2027', 'course' => 'x', 'weeks' => 14, 'instructor' => $this->faculty->id, 'first_deadline' => '2027-01-14T17:00', 'teams' => 0])
            ->assertSessionHasErrors(['name']);
        $this->actingAs($this->faculty)->get('/faculty')->assertInertia(fn (Assert $p) => $p->where('section.name', 'MBA 7250 Spring 2027')->where('isAdmin', false));
        $this->actingAs($this->admin)->get("/admin/classes/{$section->id}")->assertInertia(fn (Assert $p) => $p
            ->component('halden/AdminClass')->where('section.weeks', 7)->where('section.started', false)->has('section.quarters', 14)->where('usage.advisorAnswers', 0));

        // Settings: rename, move the first deadline (the rest follow), switch the advisors off.
        $this->actingAs($this->admin)->post("/admin/classes/{$section->id}", [
            'name' => 'MBA 7250 Spring 2027 (A)', 'course' => 'Managerial Economics', 'weeks' => 7, 'instructor' => $this->faculty->id,
            'first_deadline' => '2027-01-21T17:00', 'seats' => null, 'advisors_enabled' => false,
        ])->assertSessionHasNoErrors();
        $section->refresh();
        $this->assertSame('MBA 7250 Spring 2027 (A)', $section->name);
        $this->assertFalse($section->advisors_enabled);
        $this->assertNull($section->seats);
        $this->assertSame('2027-01-21 22:00:00', $q1->refresh()->deadline_at?->toDateTimeString());
        $this->assertSame('2027-01-28 22:00:00', $section->quarters()->where('number', 3)->firstOrFail()->deadline_at?->toDateTimeString());

        // Once a quarter has opened the length is fixed and the class cannot be deleted.
        $q1->update(['status' => Quarter::OPEN]);
        $this->actingAs($this->admin)->from("/admin/classes/{$section->id}")->post("/admin/classes/{$section->id}", [
            'name' => $section->name, 'course' => 'x', 'weeks' => 14, 'instructor' => $this->faculty->id, 'first_deadline' => null, 'seats' => null, 'advisors_enabled' => true,
        ])->assertSessionHasErrors(['weeks']);
        $this->actingAs($this->admin)->from("/admin/classes/{$section->id}")->delete("/admin/classes/{$section->id}")->assertSessionHasErrors(['delete']);
        $this->assertNotNull(Section::query()->find($section->id));

        $q1->update(['status' => Quarter::UPCOMING]);
        $this->actingAs($this->admin)->delete("/admin/classes/{$section->id}")->assertSessionHasNoErrors();
        $this->assertNull(Section::query()->find($section->id));
    }

    public function test_deadlines_stay_at_five_pm_eastern_across_the_change_to_daylight_saving(): void
    {
        $this->actingAs($this->admin)->post('/admin/classes', [
            'name' => 'Spring', 'course' => 'Econ', 'weeks' => 14, 'instructor' => $this->faculty->id, 'first_deadline' => '2027-03-11T17:00', 'teams' => 0, 'seats' => null,
        ])->assertSessionHasNoErrors();
        $section = Section::query()->where('name', 'Spring')->firstOrFail();
        $this->assertSame('2027-03-11 22:00:00', $section->quarters()->where('number', 1)->firstOrFail()->deadline_at?->toDateTimeString());
        $this->assertSame('2027-03-18 21:00:00', $section->quarters()->where('number', 2)->firstOrFail()->deadline_at?->toDateTimeString(), 'the clocks went forward on 14 March');
    }

    public function test_an_admin_makes_an_instructor_login_and_the_password_is_shown_once(): void
    {
        $this->actingAs($this->admin)->post('/admin/instructors', ['name' => 'Sam New', 'email' => 'sam@example.edu'])->assertSessionHasNoErrors();
        $sam = User::query()->where('email', 'sam@example.edu')->firstOrFail();
        $this->assertSame(User::ROLE_FACULTY, $sam->role);
        $this->actingAs($this->admin)->get('/admin')->assertInertia(fn (Assert $p) => $p
            ->where('newPassword.email', 'sam@example.edu')
            ->where('newPassword.password', fn ($pw) => is_string($pw) && strlen($pw) === 20));
        $this->actingAs($this->admin)->get('/admin')->assertInertia(fn (Assert $p) => $p->where('newPassword', null), 'shown once');

        // A student's email cannot be turned into an instructor; an existing instructor gets a new password, not a new role.
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $this->actingAs($this->admin)->from('/admin')->post('/admin/instructors', ['name' => 'x', 'email' => $student->email])->assertSessionHasErrors(['email']);
        $this->actingAs($this->admin)->post('/admin/instructors', ['name' => 'Admin Renamed', 'email' => $this->admin->email])->assertSessionHasNoErrors();
        $this->assertSame(User::ROLE_ADMIN, $this->admin->refresh()->role);
    }

    public function test_the_advisor_switch_turns_the_advisors_off_for_one_class(): void
    {
        $section = Section::query()->create(['name' => 'Off', 'course_name' => 'Econ', 'weeks' => 14, 'faculty_user_id' => $this->faculty->id, 'advisors_enabled' => false]);
        $team = $section->teams()->create(['name' => 'Alpha']);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'opening_seen_at' => now()]);
        $team->members()->create(['user_id' => $student->id, 'seat' => 'evp']);
        for ($n = 1; $n <= 14; $n++) {
            Quarter::query()->create(['section_id' => $section->id, 'number' => $n, 'company_quarter' => Quarter::companyQuarterFor($n)]);
        }
        $q1 = $section->quarters()->where('number', 1)->firstOrFail();
        app(QuarterRunner::class)->open($q1);
        $this->actingAs($student)->get("/play/{$q1->id}")->assertInertia(fn (Assert $p) => $p->where('advisors.enabled', false));
        $this->actingAs($student)->withHeader('X-Inertia', 'true')->from("/play/{$q1->id}")->post("/play/{$q1->id}/advisors/aasen", ['question' => 'Hello?'])
            ->assertSessionHasErrors(['question']);
    }
}
