<?php

namespace Tests\Feature;

use App\Halden\Ai\AdvisorRoom;
use App\Halden\Ai\LlmClient;
use App\Halden\Ai\StubClient;
use App\Halden\Content\ContentPack;
use App\Halden\Game\QuarterRunner;
use App\Halden\OperatingModel\ModelData;
use App\Models\AdvisorMessage;
use App\Models\Quarter;
use App\Models\Section;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamQuarter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdvisorRoomTest extends TestCase
{
    use RefreshDatabase;

    private const GOOD = 'Look at what each rig adds against what it costs. The first eight drill the best ground, and after that each one adds less than the one before.';

    private User $faculty;

    private Section $section;

    private Team $team;

    private User $student;

    private User $teammate;

    private StubClient $llm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->faculty = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $this->section = Section::query()->create(['name' => 'Test', 'course_name' => 'Econ', 'weeks' => 14, 'faculty_user_id' => $this->faculty->id]);
        $this->team = Team::query()->create(['section_id' => $this->section->id, 'name' => 'Alpha', 'strategy_become' => 'earns more per barrel', 'strategy_by' => 'running it as one']);
        $this->student = User::factory()->create(['role' => User::ROLE_STUDENT, 'opening_seen_at' => now(), 'name' => 'Raj']);
        $this->teammate = User::factory()->create(['role' => User::ROLE_STUDENT, 'opening_seen_at' => now(), 'name' => 'Mei']);
        TeamMember::query()->create(['team_id' => $this->team->id, 'user_id' => $this->student->id, 'seat' => 'oil_fields']);
        TeamMember::query()->create(['team_id' => $this->team->id, 'user_id' => $this->teammate->id, 'seat' => 'evp']);
        for ($n = 1; $n <= 14; $n++) {
            Quarter::query()->create(['section_id' => $this->section->id, 'number' => $n, 'company_quarter' => Quarter::companyQuarterFor($n)]);
        }
        app(QuarterRunner::class)->open($this->q(1));
        $llm = app(LlmClient::class);
        $this->assertInstanceOf(StubClient::class, $llm);
        $this->llm = $llm;
    }

    private function q(int $n): Quarter
    {
        return $this->section->quarters()->where('number', $n)->firstOrFail();
    }

    private function ask(string $advisor, string $question, ?User $as = null): TestResponse
    {
        $response = $this->actingAs($as ?? $this->student)->withHeader('X-Inertia', 'true')->from('/play/'.$this->q(1)->id)
            ->post('/play/'.$this->q(1)->id.'/advisors/'.$advisor, ['question' => $question]);
        $this->flushHeaders();

        return $response;
    }

    public function test_the_team_shares_one_conversation_and_the_advisor_sees_all_of_it(): void
    {
        $this->llm->queue(self::GOOD, self::GOOD);
        $this->ask('aasen', 'How many rigs can Texas take?')->assertSessionHasNoErrors();
        $this->ask('aasen', 'And what does a rig cost?', $this->teammate)->assertSessionHasNoErrors();

        $second = $this->llm->calls[1]['messages'];
        $this->assertSame(['user', 'assistant', 'user'], array_column($second, 'role'));
        $this->assertSame('Raj: How many rigs can Texas take?', $second[0]['content']);
        $this->assertSame('Mei: And what does a rig cost?', $second[2]['content']);

        $this->actingAs($this->teammate)->get('/play/'.$this->q(1)->id)->assertInertia(fn (Assert $p) => $p
            ->where('advisors.enabled', true)
            ->where('advisors.cards.2.key', 'aasen')
            ->where('advisors.cards.2.used', 2)
            ->where('advisors.cards.2.left', 4)
            ->has('advisors.cards.2.messages', 4));
    }

    public function test_the_prompt_is_built_from_the_quarter_file_and_the_teams_own_words(): void
    {
        $this->ask('aasen', 'How many rigs?');
        $system = $this->llm->calls[0]['system'];
        $this->assertStringContainsString('You are Bjørn Aasen', $system);
        $this->assertStringContainsString('Fourteen rigs are drilling in Texas.', $system);
        $this->assertStringContainsString('Halden should become a company that earns more per barrel by running it as one.', $system);
        $this->assertStringContainsString('Never mention a course, class, simulation', $system);
        $this->assertStringNotContainsString('This is your last answer', $system);
        $this->assertStringContainsString('No results yet', $system);
        // The advisor is told which words get an answer dropped, and that Cordell is Halden's own brand.
        $this->assertStringContainsString('Never use these words, even in passing, unless the team used them first: room, war room, lever,', $system);
        $this->assertStringContainsString("Cordell is Halden's own brand, not a rival.", $system);
    }

    public function test_a_long_question_reaches_the_advisor_in_full(): void
    {
        $long = str_repeat('We want to understand the rigs properly before deciding anything at all. ', 50);
        $this->ask('marchetti', $long)->assertSessionHasNoErrors();
        $this->assertSame('Raj: '.trim($long), $this->llm->calls[0]['messages'][0]['content']);
    }

    public function test_six_answers_then_the_advisor_is_done_and_no_more_calls_are_made(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->llm->queue(self::GOOD);
            $this->ask('ruiz', "Question $i")->assertSessionHasNoErrors();
        }
        $this->assertStringContainsString('This is your last answer', $this->llm->calls[5]['system']);

        $this->ask('ruiz', 'One more?')->assertSessionHasErrors(['question' => 'Ana is done for this quarter.']);
        $this->assertCount(6, $this->llm->calls, 'a blocked team never reaches the model');

        // Outside the app (no Inertia header) the refusal is a 403.
        $this->actingAs($this->student)->post('/play/'.$this->q(1)->id.'/advisors/ruiz', ['question' => 'Again?'])->assertForbidden();
        $this->assertCount(6, $this->llm->calls);
    }

    public function test_priya_gives_three_predictions_a_quarter(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->ask('priya', "Will Marcus mind? ($i)")->assertSessionHasNoErrors();
        }
        $this->ask('priya', 'And Ingrid?')->assertSessionHasErrors(['question' => 'Priya is done for this quarter.']);
    }

    public function test_a_reply_with_a_made_up_figure_is_dropped_not_billed_and_explained_to_faculty_only(): void
    {
        $this->llm->queue(self::GOOD.' I reckon the twelfth rig earns about $37 million over its life.');
        $this->ask('aasen', 'Does the twelfth rig pay?')->assertSessionHasNoErrors();

        $reply = AdvisorMessage::query()->where('role', 'advisor')->sole();
        $this->assertSame(AdvisorMessage::DROPPED, $reply->status);
        $this->assertSame('Dollar figure not in its inputs: $37 million.', $reply->dropped_reason);
        $this->assertSame(0, AdvisorMessage::billable($this->team->id, $this->q(1)->id));

        $this->actingAs($this->student)->get('/play/'.$this->q(1)->id)->assertInertia(fn (Assert $p) => $p
            ->where('advisors.cards.2.left', 6)
            ->where('advisors.cards.2.messages.1.from', 'notice')
            ->where('advisors.cards.2.messages.1.body', "Bjørn didn't have a clear answer to that one. Try asking another way. That question didn't use up an answer."));

        $this->actingAs($this->faculty)->get("/faculty/teams/{$this->team->id}/quarters/".$this->q(1)->id)->assertInertia(fn (Assert $p) => $p
            ->where('advisors.cards.2.messages.1.from', 'dropped')
            ->where('advisors.cards.2.messages.1.reason', 'Dollar figure not in its inputs: $37 million.'));

        // The next question still carries the one that got no answer.
        $this->llm->queue(self::GOOD);
        $this->ask('aasen', 'Put another way: is rig twelve worth it?');
        $this->assertSame("Raj: Does the twelfth rig pay?\n\nRaj: Put another way: is rig twelve worth it?", $this->llm->calls[1]['messages'][0]['content']);
    }

    public function test_answers_are_billed_to_head_office_when_the_quarter_closes(): void
    {
        foreach (['marchetti', 'aasen', 'aasen'] as $a) {
            $this->llm->queue(self::GOOD);
            $this->ask($a, 'What should we look at?');
        }
        $runner = app(QuarterRunner::class);
        $runner->close($this->q(1));
        $r = TeamQuarter::query()->where('team_id', $this->team->id)->firstOrFail()->results;
        $this->assertSame(3, $r['advisor.answers']);
        $this->assertEqualsWithDelta(-0.36, $r['line.advisor_time'], 1e-9);
        $this->assertEqualsWithDelta($r['money.ebitda'] - $r['bridge.previous'], $r['bridge.prices'] + $r['bridge.decisions'] + $r['bridge.carried_over'], 1e-6);

        $runner->publish($this->q(1)->refresh());
        $this->actingAs($this->student)->get('/play/'.$this->q(1)->id)->assertInertia(fn (Assert $p) => $p
            ->where('results.named', fn ($named) => collect($named)->contains(fn ($l) => $l['name'] === 'Advisor time'
                && $l['why'] === '3 answers from your advisors, at $120K each. Counted under Head office.')));

        // Next quarter, the finance advisor sees the published results; conversations are read-only in a closed quarter.
        $this->ask('marchetti', 'Anything else?')->assertSessionHasErrors(['question']);
        $runner->open($this->q(2));
        $this->actingAs($this->student)->post('/play/'.$this->q(2)->id.'/advisors/ruiz', ['question' => 'How did we do?']);
        $this->assertStringContainsString('Q1 2027: earnings before interest, tax and depreciation $', end($this->llm->calls)['system']);
    }

    public function test_switched_off_without_a_key_on_the_live_site(): void
    {
        $room = new AdvisorRoom($this->llm, app(ContentPack::class), app(ModelData::class), enabled: false);
        $this->app->instance(AdvisorRoom::class, $room);
        $this->ask('aasen', 'Hello?')->assertSessionHasErrors(['question']);
        $this->assertCount(0, $this->llm->calls);
    }

    public function test_no_conversations_with_another_class_or_unknown_advisors(): void
    {
        $other = Section::query()->create(['name' => 'Other', 'course_name' => 'Econ', 'weeks' => 14, 'faculty_user_id' => $this->faculty->id]);
        $q = Quarter::query()->create(['section_id' => $other->id, 'number' => 1, 'company_quarter' => Quarter::companyQuarterFor(1), 'status' => Quarter::OPEN]);
        $this->actingAs($this->student)->post("/play/{$q->id}/advisors/aasen", ['question' => 'Hi'])->assertNotFound();
        $this->ask('sandvik', 'Hi')->assertSessionHasErrors(['question' => 'There is no advisor by that name.']);
        $this->assertCount(0, $this->llm->calls);
    }
}
