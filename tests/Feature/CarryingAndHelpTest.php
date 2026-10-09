<?php

namespace Tests\Feature;

use App\Halden\Ai\Carrying;
use App\Halden\Ai\LlmClient;
use App\Halden\Ai\StubClient;
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

class CarryingAndHelpTest extends TestCase
{
    use RefreshDatabase;

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
        $this->team = Team::query()->create(['section_id' => $this->section->id, 'name' => 'Alpha', 'strategy_become' => 'earns more per barrel', 'strategy_by' => 'running it as one']);
        $this->student = User::factory()->create(['role' => User::ROLE_STUDENT, 'opening_seen_at' => now()]);
        TeamMember::query()->create(['team_id' => $this->team->id, 'user_id' => $this->student->id, 'seat' => 'evp']);
        for ($n = 1; $n <= 14; $n++) {
            Quarter::query()->create(['section_id' => $this->section->id, 'number' => $n, 'company_quarter' => Quarter::companyQuarterFor($n)]);
        }
        $llm = app(LlmClient::class);
        $this->assertInstanceOf(StubClient::class, $llm);
        $this->llm = $llm;
    }

    private function q(int $n): Quarter
    {
        return $this->section->quarters()->where('number', $n)->firstOrFail();
    }

    private function playFirstQuarter(): void
    {
        $runner = app(QuarterRunner::class);
        $runner->open($this->q(1));
        TeamQuarter::query()->create(['team_id' => $this->team->id, 'quarter_id' => $this->q(1)->id, 'decisions' => ['rigs' => 11]]);
        $runner->close($this->q(1));
        $runner->publish($this->q(1)->refresh());
    }

    public function test_the_note_is_written_once_from_the_teams_own_record_and_everyone_reads_the_same_words(): void
    {
        $this->playFirstQuarter();
        $this->llm->queue('You set out to earn more on every barrel by running Halden as one business. Last quarter you drilled with 11 rigs in Texas and kept Rotterdam running.');
        $this->actingAs($this->faculty)->post('/faculty/quarters/'.$this->q(2)->id.'/open')->assertSessionHasNoErrors();

        $this->assertCount(1, $this->llm->calls, 'the note is written after the page is sent');
        $sent = $this->llm->calls[0]['messages'][0]['content'];
        $this->assertStringContainsString('Halden should become a company that earns more per barrel by running it as one.', $sent);
        $this->assertStringContainsString('Q1 2027: Drilling rigs in Texas: 11', $sent);

        app(Carrying::class)->writeFor($this->q(2));
        $this->assertCount(1, $this->llm->calls, 'never rewritten');

        $this->actingAs($this->student)->get('/play/'.$this->q(2)->id)->assertInertia(fn (Assert $p) => $p
            ->where('carrying.title', "What you're carrying")
            ->where('carrying.text', 'You set out to earn more on every barrel by running Halden as one business. Last quarter you drilled with 11 rigs in Texas and kept Rotterdam running.')
            ->where('carrying.reason', null));
    }

    public function test_a_note_that_gives_advice_is_dropped_silently_and_faculty_see_why(): void
    {
        $this->playFirstQuarter();
        $this->llm->queue('You drilled with 11 rigs last quarter. You should consider cutting Rotterdam this time, since it keeps losing money for Halden.');
        app(QuarterRunner::class)->open($this->q(2));
        app(Carrying::class)->writeFor($this->q(2));

        $this->actingAs($this->student)->get('/play/'.$this->q(2)->id)->assertInertia(fn (Assert $p) => $p->where('carrying', null));
        $this->actingAs($this->faculty)->get("/faculty/teams/{$this->team->id}/quarters/".$this->q(2)->id)->assertInertia(fn (Assert $p) => $p
            ->where('carrying.text', null)
            ->where('carrying.reason', fn ($r) => str_starts_with((string) $r, 'Gave advice ("you should").')));
    }

    public function test_no_note_in_the_first_quarter(): void
    {
        app(QuarterRunner::class)->open($this->q(1));
        app(Carrying::class)->writeFor($this->q(1));
        $this->assertCount(0, $this->llm->calls);
    }

    public function test_the_help_desk_knows_only_the_website(): void
    {
        $this->llm->queue('Your results appear under Your results once your instructor shows them, usually before the next class.');
        $this->actingAs($this->student)->postJson('/help', ['question' => 'Where are my results?'])
            ->assertOk()->assertJson(['answer' => 'Your results appear under Your results once your instructor shows them, usually before the next class.']);

        $system = $this->llm->calls[0]['system'];
        foreach (['Rotterdam', 'Permian', 'rigs', 'Marcus', 'WTI', '$'] as $scenario) {
            $this->assertStringNotContainsString($scenario, $system, "the help desk is told nothing about $scenario");
        }
        $this->assertDatabaseCount('advisor_messages', 0);
    }

    public function test_a_help_answer_with_a_figure_is_dropped(): void
    {
        $this->llm->queue('Each rig costs about $40 million, so keep that in mind on the oil fields page.');
        $this->actingAs($this->student)->postJson('/help', ['question' => 'How much is a rig?'])->assertOk()->assertJson(['answer' => null]);
    }

    public function test_the_quarter_screen_carries_the_help_questions(): void
    {
        app(QuarterRunner::class)->open($this->q(1));
        $this->actingAs($this->student)->get('/play/'.$this->q(1)->id)->assertInertia(fn (Assert $p) => $p
            ->where('help.enabled', true)
            ->where('help.faq.0.q', 'When is the deadline?'));
    }
}
