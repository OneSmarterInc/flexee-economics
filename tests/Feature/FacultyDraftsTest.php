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
            ->where('feedback.writingScoreAi', 4)
            ->where('feedback.writingAdjustment', 0)
            ->where('feedback.writingScore', 4));

        // Faculty adjust the AI's score; the adjustment is added once and the result stays within 1 to 5.
        $this->actingAs($this->faculty)->post($this->url(), ['feedback' => 'x', 'writing_adjustment' => -1])->assertSessionHasNoErrors();
        $this->assertSame(3, TeamQuarter::query()->firstOrFail()->writing_score);
        $this->actingAs($this->faculty)->post($this->url(), ['feedback' => 'x', 'writing_adjustment' => 0])->assertSessionHasNoErrors();
        $this->assertSame(4, TeamQuarter::query()->firstOrFail()->writing_score);
        $this->actingAs($this->faculty)->post($this->url(), ['feedback' => 'x', 'writing_adjustment' => 3])->assertSessionHasNoErrors();
        $this->assertSame(5, TeamQuarter::query()->firstOrFail()->writing_score);
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

    public function test_in_the_board_quarter_the_drafts_read_the_defense_against_the_whole_record(): void
    {
        $runner = app(QuarterRunner::class);
        $runner->publish($this->q());
        $this->team->update(['strategy_become' => 'earns its keep in oil', 'strategy_by' => 'running every plant to its margin']);
        for ($n = 2; $n <= 13; $n++) {
            $q = $this->section->quarters()->where('number', $n)->firstOrFail();
            $runner->open($q);
            if ($n === 5) {
                TeamQuarter::query()->create(['team_id' => $this->team->id, 'quarter_id' => $q->id, 'memo' => 'We paused Rotterdam because every barrel there loses money at this margin.']);
            }
            $runner->close($q->refresh());
            $runner->publish($q->refresh());
        }
        $q14 = $this->section->quarters()->where('number', 14)->firstOrFail();
        $runner->open($q14);
        $this->actingAs($this->student)->post("/play/{$q14->id}/defense", ['synthesis' => 'We ran Halden as one oil company.', 'decisions' => 'Rotterdam: we always meant to keep it running.', 'counterfactual' => 'Fewer rigs in 2027.'])
            ->assertSessionHasNoErrors();
        $runner->close($q14->refresh());
        $tq = TeamQuarter::query()->where('team_id', $this->team->id)->where('quarter_id', $q14->id)->firstOrFail();
        $url = "/faculty/teams/{$this->team->id}/quarters/{$q14->id}/feedback";

        $this->llm->queue(
            "A company, or fourteen answers: You say you ran one oil company, and the record mostly bears that out. Q1 2027 is the clearest case. Your eleven rigs followed from what each one adds, which is the test you kept applying.\n\nSound at the time: Of the three decisions you defend, Rotterdam is the weakest. Your memo from Q1 2028 argued for pausing it because every barrel there loses money at this margin, so the claim that you always meant to keep it running does not match what you wrote then. The rigs decision is the strongest.\n\nTheir own run: You see what went right. You see less of what went wrong. The record shows a quarter without a memo at all, and your defense does not mention it.",
            '{"score": 3, "reason": "The parts are there; the through-line is asserted rather than shown."}',
            '{"mismatch": true, "note": "Q1 2028: the memo argued for pausing Rotterdam; the defense says the team always meant to keep it running."}',
        );
        $this->actingAs($this->faculty)->post("$url/draft?section={$this->section->id}")->assertSessionHasNoErrors();

        $this->assertCount(3, $this->llm->calls);
        $system = $this->llm->calls[0]['system'];
        $sent = $this->llm->calls[0]['messages'][0]['content'];
        $this->assertStringContainsString('submits a board defense in three parts', $system);
        $this->assertStringContainsString("'A company, or fourteen answers:'", $system);
        $this->assertStringContainsString("THE TEAM'S BOARD DEFENSE:", $sent);
        $this->assertStringContainsString('Rotterdam: we always meant to keep it running.', $sent);
        $this->assertStringContainsString('Halden should become a company that earns its keep in oil by running every plant to its margin.', $sent);
        $this->assertStringContainsString('The world the plan is judged in:', $sent);
        $this->assertStringContainsString("THE TEAM'S RECORD, QUARTER BY QUARTER:", $sent);
        $this->assertStringContainsString((string) TeamQuarter::query()->where('quarter_id', $this->q()->id)->firstOrFail()->memo, $sent, 'every memo goes in whole');
        $this->assertStringContainsString('Q1 2028 · ', $sent);
        $this->assertStringContainsString('every barrel there loses money at this margin', $sent);
        $this->assertStringContainsString('(No memo that quarter.)', $sent);
        $this->assertStringContainsString('Only flag a real contradiction between the defense and the record.', $this->llm->calls[2]['system']);

        $this->actingAs($this->faculty)->get("$url?section={$this->section->id}")->assertInertia(fn (Assert $p) => $p
            ->where('labels.feedback', 'Feedback on the board defense')
            ->where('labels.mismatch', 'Does the defense match the record?')
            ->where('text.memo_title', "The team's board defense")
            ->where('drafts.feedback.status', 'ok')
            ->where('drafts.writing.data.score', 3)
            ->where('drafts.mismatch.data.mismatch', true)
            ->has('inputs.record', 13));
        $this->assertSame(3, $tq->refresh()->writing_score_ai);

        // The usual memo feedback shape is refused here: the board quarter wants its own three paragraphs.
        $this->llm->queue(self::FEEDBACK, '{"score": 3, "reason": "Fine."}', '{"mismatch": false, "note": "The defense matches the record."}');
        $this->actingAs($this->faculty)->post("$url/draft?section={$this->section->id}");
        $this->assertSame("Shape: the draft is missing the 'A company, or fourteen answers:' paragraph.", FacultyDraft::query()->where('kind', 'feedback')->latest('id')->firstOrFail()->dropped_reason);
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
