<?php

namespace Tests\Feature;

use App\Halden\Ai\LlmClient;
use App\Halden\Ai\StubClient;
use App\Halden\Game\QuarterRunner;
use App\Models\FacultyDraft;
use App\Models\Quarter;
use App\Models\Section;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamQuarter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FacultyDraftsTest extends TestCase
{
    use RefreshDatabase;

    private const FEEDBACK = "What held: You judged each extra rig by what it adds, and stopping at 11 rigs follows from that. You also kept Rotterdam running because each barrel covers its running costs, which is the right test for this quarter. That shows you separating the costs that stop from the ones that don't.\n\nWhat was thin: A board member would ask how far oil could fall before your last rig stops paying for itself. Your memo doesn't say, and the answer decides whether 11 is a firm number or a guess.\n\nWhat to carry: Covering running costs is a reason to keep a plant open now, not for good. Keep the yearly loss and the cost of closing side by side whenever you defend it.";

    private User $faculty;

    private Section $section;

    private Team $team;

    private User $student;

    private StubClient $llm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->faculty = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $this->section = Section::query()->create(['name' => 'Test', 'course_name' => 'Econ', 'weeks' => 14, 'faculty_user_id' => $this->faculty->id]);
        $this->team = Team::query()->create(['section_id' => $this->section->id, 'name' => 'Alpha']);
        $this->student = User::factory()->create(['role' => User::ROLE_STUDENT, 'opening_seen_at' => now()]);
        TeamMember::query()->create(['team_id' => $this->team->id, 'user_id' => $this->student->id, 'seat' => 'evp']);
        for ($n = 1; $n <= 14; $n++) {
            Quarter::query()->create(['section_id' => $this->section->id, 'number' => $n, 'company_quarter' => Quarter::companyQuarterFor($n)]);
        }
        $runner = app(QuarterRunner::class);
        $runner->open($this->q());
        TeamQuarter::query()->create(['team_id' => $this->team->id, 'quarter_id' => $this->q()->id,
            'decisions' => ['rigs' => 11], 'memo' => str_repeat('We cut to eleven rigs because the twelfth costs more than it adds. ', 80)]);
        $runner->close($this->q());
        $llm = app(LlmClient::class);
        $this->assertInstanceOf(StubClient::class, $llm);
        $this->llm = $llm;
    }

    private function q(): Quarter
    {
        return $this->section->quarters()->where('number', 1)->firstOrFail();
    }

    private function url(string $tail = ''): string
    {
        return "/faculty/teams/{$this->team->id}/quarters/{$this->q()->id}/feedback$tail";
    }

    public function test_faculty_get_three_drafts_built_from_the_whole_memo_and_the_findings(): void
    {
        $this->llm->queue(self::FEEDBACK, '{"score": 4, "reason": "Clear claim and evidence, with one gap."}', '{"mismatch": false, "note": "The memo matches the decisions."}');
        $this->actingAs($this->faculty)->post($this->url('/draft'))->assertSessionHasNoErrors();

        $this->assertCount(3, $this->llm->calls);
        $sent = $this->llm->calls[0]['messages'][0]['content'];
        $memo = (string) TeamQuarter::query()->firstOrFail()->memo;
        $this->assertStringContainsString($memo, $sent, 'the memo goes in whole');
        $this->assertStringContainsString('Drilling rigs in Texas: 11', $sent);
        $this->assertStringContainsString('the last of those rigs adds oil worth about $42M', $sent);
        $this->assertStringContainsString('The team did not ask any advisor anything this quarter.', $sent);

        $this->actingAs($this->faculty)->get($this->url())->assertInertia(fn (Assert $p) => $p
            ->component('halden/FacultyFeedback')
            ->where('drafts.feedback.status', 'ok')
            ->where('drafts.writing.data.score', 4)
            ->where('drafts.mismatch.data.mismatch', false)
            ->where('feedback.text', '')
            ->where('feedback.writingScore', null));
    }

    public function test_a_draft_that_fails_a_check_is_kept_for_faculty_with_the_reason(): void
    {
        $this->llm->queue(
            str_replace('stopping at 11 rigs', 'earning $95 million from the cut by stopping at 11 rigs', self::FEEDBACK),
            '{"score": 9, "reason": "Great."}',
            'Not JSON at all',
        );
        $this->actingAs($this->faculty)->post($this->url('/draft'));
        $reasons = FacultyDraft::query()->orderBy('id')->pluck('dropped_reason', 'kind')->all();
        $this->assertSame([
            'feedback' => 'Dollar figure not in its inputs: $95 million.',
            'writing' => 'The proposed score was not a whole number from 1 to 5.',
            'mismatch' => 'The reply was not in the expected form.',
        ], $reasons);
        $this->assertSame(0, FacultyDraft::query()->where('status', 'ok')->count());
    }

    public function test_nothing_reaches_students_until_faculty_publish(): void
    {
        app(QuarterRunner::class)->publish($this->q());
        $this->actingAs($this->faculty)->post($this->url(), ['feedback' => 'Good work on the rigs.', 'writing_score' => 4, 'publish' => false])->assertSessionHasNoErrors();
        $this->actingAs($this->student)->get('/play/'.$this->q()->id)->assertInertia(fn (Assert $p) => $p->where('feedback', null));

        $this->actingAs($this->faculty)->post($this->url(), ['feedback' => 'Good work on the rigs.', 'writing_score' => 4, 'publish' => true])->assertSessionHasNoErrors();
        $this->actingAs($this->student)->get('/play/'.$this->q()->id)->assertInertia(fn (Assert $p) => $p
            ->where('feedback.title', 'Feedback from your instructor')
            ->where('feedback.text', 'Good work on the rigs.'));
        $this->assertSame(4, TeamQuarter::query()->firstOrFail()->writing_score);
    }

    public function test_publishing_empty_feedback_is_refused(): void
    {
        $this->actingAs($this->faculty)->post($this->url(), ['feedback' => '  ', 'publish' => true])
            ->assertSessionHasErrors(['feedback' => 'Write or paste the feedback before publishing it.']);
    }

    public function test_drafts_only_after_the_close_and_only_for_your_own_class(): void
    {
        $runner = app(QuarterRunner::class);
        $runner->publish($this->q());
        $runner->open($this->section->quarters()->where('number', 2)->firstOrFail());
        $q2 = $this->section->quarters()->where('number', 2)->firstOrFail();
        TeamQuarter::query()->create(['team_id' => $this->team->id, 'quarter_id' => $q2->id]);
        $this->actingAs($this->faculty)->get("/faculty/teams/{$this->team->id}/quarters/{$q2->id}/feedback")->assertNotFound();

        $stranger = User::factory()->create(['role' => User::ROLE_FACULTY]);
        Section::query()->create(['name' => 'Theirs', 'course_name' => 'Econ', 'weeks' => 14, 'faculty_user_id' => $stranger->id]);
        $this->actingAs($stranger)->post($this->url('/draft'))->assertNotFound();
        $this->actingAs($this->student)->get($this->url())->assertForbidden();
        $this->assertCount(0, $this->llm->calls);
    }
}
