<?php

namespace Tests\Feature;

use App\Halden\Admin\Benchmarks;
use App\Halden\Admin\ClassFactory;
use App\Halden\Game\QuarterRunner;
use App\Models\Section;
use App\Models\TeamQuarter;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BenchmarksTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_class_is_compared_with_every_class_that_ran_the_same_quarter(): void
    {
        $faculty = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $other = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $mine = app(ClassFactory::class)->create('Mine', 'Managerial Economics', 14, $faculty, CarbonImmutable::now(), 2);
        $theirs = app(ClassFactory::class)->create('Theirs', 'Managerial Economics', 7, $other, CarbonImmutable::now(), 3);
        $runner = app(QuarterRunner::class);
        $rigs = 8;
        foreach ([$mine, $theirs] as $section) {
            $q1 = $section->quarters()->where('number', 1)->firstOrFail();
            $runner->open($q1);
            foreach ($section->teams as $team) {
                TeamQuarter::query()->create(['team_id' => $team->id, 'quarter_id' => $q1->id, 'decisions' => ['rigs' => $rigs++]]);
            }
            $runner->close($q1->refresh());
        }
        $this->assertSame([], Benchmarks::rows($mine), 'nothing until results are shown');

        $runner->publish($mine->quarters()->where('number', 1)->firstOrFail()->refresh());
        $rows = Benchmarks::rows($mine);
        $this->assertCount(1, $rows);
        $this->assertSame(['teams' => 2, 'otherTeams' => 0, 'otherClasses' => 0], ['teams' => $rows[0]['teams'], 'otherTeams' => $rows[0]['otherTeams'], 'otherClasses' => $rows[0]['otherClasses']]);
        $this->assertSame($rows[0]['measures'][0]['here'], $rows[0]['measures'][0]['all'], 'alone, the class is the whole field');

        $runner->publish($theirs->quarters()->where('number', 1)->firstOrFail()->refresh());
        $rows = Benchmarks::rows($mine);
        $this->assertSame(['teams' => 2, 'otherTeams' => 3, 'otherClasses' => 1], ['teams' => $rows[0]['teams'], 'otherTeams' => $rows[0]['otherTeams'], 'otherClasses' => $rows[0]['otherClasses']]);
        $names = array_column($rows[0]['measures'], 'name');
        $this->assertSame('Profit per barrel, whole company', $names[0]);
        $this->assertSame('EBITDA', $names[7]);
        $ebitda = $rows[0]['measures'][7];
        $this->assertGreaterThanOrEqual($ebitda['hereBest'], $ebitda['best'], 'the field\'s best is at least this class\'s best');
        $debt = collect($rows[0]['measures'])->firstWhere('key', 'debt_to_earnings');
        $this->assertLessThanOrEqual($debt['hereBest'], $debt['best'], 'lower is better for debt');

        $this->actingAs($faculty)->get("/faculty/benchmarks?section={$mine->id}")->assertInertia(fn (Assert $p) => $p->component('halden/FacultyBenchmarks')->has('rows', 1)->where('rows.0.otherClasses', 1));
        $this->actingAs($other)->get("/faculty/benchmarks?section={$mine->id}")->assertInertia(fn (Assert $p) => $p->where('section.name', 'Theirs'), 'someone else\'s class falls back to their own');
        $this->assertSame(1, Section::query()->where('name', 'Theirs')->count());
    }
}
