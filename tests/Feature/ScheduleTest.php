<?php

namespace Tests\Feature;

use App\Halden\Admin\ClassFactory;
use App\Models\Quarter;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_moving_a_deadline_carries_the_later_ones_and_run_quarters_stay_put(): void
    {
        $faculty = User::factory()->create(['role' => User::ROLE_FACULTY]);
        // Thursday 14 Jan 2027, 5 pm Eastern (22:00 UTC); daylight saving starts 14 March 2027.
        $section = app(ClassFactory::class)->create('MBA 7250', 'Managerial Economics', 14, $faculty, CarbonImmutable::parse('2027-01-14 22:00:00'));
        $q = fn (int $n) => $section->quarters()->where('number', $n)->firstOrFail();
        $q(1)->update(['status' => Quarter::PUBLISHED]);
        $q(2)->update(['status' => Quarter::OPEN]);

        $this->actingAs($faculty)->get('/faculty/schedule')->assertInertia(fn (Assert $p) => $p
            ->component('halden/FacultySchedule')->has('rows', 14)
            ->where('rows.0.editable', false)->where('rows.1.editable', true)->where('rows.1.deadline', '2027-01-21T17:00'));

        // Week 3 moves from Thursday to the following Monday (+4 days); everything after follows; Q1 and Q2 stay.
        $ids = $section->quarters()->pluck('id', 'number');
        $deadlines = [];
        foreach ($section->quarters()->get() as $quarter) {
            $deadlines[$quarter->id] = $quarter->deadline_at->setTimezone('America/New_York')->format('Y-m-d\TH:i');
        }
        $deadlines[$ids[3]] = '2027-02-01T17:00';
        $this->actingAs($faculty)->post("/faculty/schedule?section={$section->id}", ['deadlines' => $deadlines, 'shift_following' => true])
            ->assertSessionHasNoErrors()->assertSessionHas('done', 'changed:12');
        $this->assertSame('2027-01-21 22:00:00', $q(2)->deadline_at?->toDateTimeString(), 'the open quarter was not touched');
        $this->assertSame('2027-02-01 22:00:00', $q(3)->deadline_at?->toDateTimeString());
        $this->assertSame('2027-02-08 22:00:00', $q(4)->deadline_at?->toDateTimeString());
        $this->assertSame('2027-03-15 21:00:00', $q(9)->deadline_at?->toDateTimeString(), 'still 5 pm Eastern after the clocks change');
        $this->assertSame('2027-04-19 21:00:00', $q(14)->deadline_at?->toDateTimeString());

        // Without the carry, only the one row moves; a row set explicitly in the same save keeps its own value.
        $deadlines = [];
        foreach ($section->quarters()->get() as $quarter) {
            $deadlines[$quarter->id] = $quarter->deadline_at->setTimezone('America/New_York')->format('Y-m-d\TH:i');
        }
        $deadlines[$ids[5]] = '2027-02-16T12:00';
        $this->actingAs($faculty)->post("/faculty/schedule?section={$section->id}", ['deadlines' => $deadlines, 'shift_following' => false])
            ->assertSessionHasNoErrors()->assertSessionHas('done', 'changed:1');
        $this->assertSame('2027-02-16 17:00:00', $q(5)->deadline_at?->toDateTimeString());
        $this->assertSame('2027-02-22 22:00:00', $q(6)->deadline_at?->toDateTimeString());

        // Out of order, or a quarter already run: refused.
        $deadlines[$ids[5]] = '2027-02-16T12:00';
        $deadlines[$ids[6]] = '2027-02-10T17:00';
        $this->actingAs($faculty)->post("/faculty/schedule?section={$section->id}", ['deadlines' => $deadlines, 'shift_following' => false])
            ->assertSessionHasErrors(['schedule']);
        $deadlines[$ids[6]] = '2027-02-22T17:00';
        $deadlines[$ids[1]] = '2027-01-15T17:00';
        $this->actingAs($faculty)->post("/faculty/schedule?section={$section->id}", ['deadlines' => $deadlines, 'shift_following' => false])
            ->assertSessionHasErrors(['schedule']);
        $this->assertSame('2027-01-14 22:00:00', $q(1)->deadline_at?->toDateTimeString());

        // Someone else's class is not theirs to see.
        $other = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $this->actingAs($other)->get("/faculty/schedule?section={$section->id}")->assertNotFound();
    }

    public function test_a_seven_week_class_shows_one_deadline_per_week(): void
    {
        $faculty = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $section = app(ClassFactory::class)->create('Short', 'Managerial Economics', 7, $faculty, CarbonImmutable::parse('2027-01-14 22:00:00'));
        $this->actingAs($faculty)->get('/faculty/schedule')->assertInertia(fn (Assert $p) => $p
            ->has('rows', 14)->where('rows.0.editable', true)->where('rows.1.carries', true)->where('rows.1.deadline', null)->where('rows.2.week', 2));
        $ids = $section->quarters()->pluck('id', 'number');
        $deadlines = [$ids[1] => '2027-01-14T17:00', $ids[3] => '2027-01-22T17:00'];
        $this->actingAs($faculty)->post("/faculty/schedule?section={$section->id}", ['deadlines' => $deadlines, 'shift_following' => true])
            ->assertSessionHasNoErrors()->assertSessionHas('done', 'changed:6');
        $this->assertSame('2027-01-29 22:00:00', $section->quarters()->where('number', 5)->firstOrFail()->deadline_at?->toDateTimeString());
    }
}
