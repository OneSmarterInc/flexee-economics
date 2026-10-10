<?php

namespace Tests\Feature;

use App\Halden\Admin\ClassFactory;
use App\Halden\Admin\ResultsExport;
use App\Halden\Game\QuarterRunner;
use App\Models\TeamMember;
use App\Models\TeamQuarter;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_class_results_download_as_one_csv_row_per_team_per_quarter(): void
    {
        $faculty = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $section = app(ClassFactory::class)->create('MBA 7250 Spring 2027', 'Managerial Economics', 14, $faculty, CarbonImmutable::now(), 2);
        [$a, $b] = $section->teams()->orderBy('name')->get()->all();
        $ada = User::factory()->create(['role' => User::ROLE_STUDENT, 'name' => 'Ada Lovelace']);
        TeamMember::query()->create(['team_id' => $a->id, 'user_id' => $ada->id, 'seat' => 'evp']);
        $runner = app(QuarterRunner::class);

        // Nothing run yet: headings only.
        $this->actingAs($faculty)->get('/faculty/results.csv')->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="MBA-7250-Spring-2027-results.csv"')
            ->assertSee('Quarter,"Company quarter",Week,Team,Members,Score,Rank,"Profit per barrel, whole company (30%, $/bbl)"', false);

        $q1 = $section->quarters()->where('number', 1)->firstOrFail();
        $runner->open($q1);
        foreach ([$a, $b] as $team) {
            TeamQuarter::query()->create(['team_id' => $team->id, 'quarter_id' => $q1->id, 'decisions' => ['rigs' => $team->is($a) ? 12 : 8],
                'memo' => $team->is($a) ? "<p>We drilled, and here's why: the \"price\" held.</p>" : null, 'memo_saved_at' => now()]);
        }
        $runner->close($q1->refresh());
        $runner->publish($q1->refresh());
        TeamQuarter::query()->where('team_id', $a->id)->where('quarter_id', $q1->id)
            ->update(['feedback' => 'Clear memo.', 'feedback_published_at' => now(), 'writing_score' => 8]);
        TeamQuarter::query()->where('team_id', $b->id)->where('quarter_id', $q1->id)
            ->update(['feedback' => 'Not yet shown to the team', 'writing_score' => 5]);
        $q2 = $section->quarters()->where('number', 2)->firstOrFail();
        $runner->open($q2);

        $rows = ResultsExport::rows($section);
        $this->assertCount(2, $rows, 'Q1 for both teams; Q2 is open with nothing saved yet');
        $head = array_flip(ResultsExport::headings());
        $rowA = $rows[0];
        $this->assertSame([1, 'Q1 2027', 1, 'Team A', 'Ada Lovelace'], array_slice($rowA, 0, 5));
        $this->assertIsFloat($rowA[$head['Score']]);
        $this->assertContains($rowA[$head['Rank']], [1, 2]);
        $this->assertIsFloat($rowA[$head['Plant condition (10%, of 100)']]);
        $this->assertSame('We drilled, and here\'s why: the "price" held.', $rowA[$head['Memo']]);
        $this->assertSame(8, $rowA[$head['Memo words']]);
        $this->assertSame('Clear memo.', $rowA[$head['Feedback']]);
        $this->assertSame(8, $rowA[$head['Writing score']]);
        $rowB = $rows[1];
        $this->assertSame('Team B', $rowB[$head['Team']]);
        $this->assertSame('', $rowB[$head['Feedback']], 'feedback not yet published stays out');
        $this->assertSame(5, $rowB[$head['Writing score']]);
        $this->assertSame(0, $rowB[$head['Memo words']]);

        $csv = $this->actingAs($faculty)->get("/faculty/results.csv?section={$section->id}")->assertOk()->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"We drilled, and here\'s why: the ""price"" held."', $csv);
        $this->assertSame(3, substr_count($csv, "\n"), 'a heading row and two team rows');

        // Only the instructor (or an admin) can download it.
        $other = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $this->actingAs($other)->get("/faculty/results.csv?section={$section->id}")->assertNotFound();
        $this->actingAs($ada)->get("/faculty/results.csv?section={$section->id}")->assertForbidden();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get("/faculty/results.csv?section={$section->id}")->assertOk();
    }
}
