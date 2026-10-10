<?php

namespace App\Halden\Ai;

use App\Halden\Content\ContentPack;
use App\Halden\Game\QuarterRunner;
use App\Halden\OperatingModel\ModelData;
use App\Models\AdvisorMessage;
use App\Models\AdvisorThread;
use App\Models\Quarter;
use App\Models\Team;
use App\Models\TeamQuarter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Team conversations with the advisors. The prompt is rebuilt on every turn from layers:
 * persona, rules, this quarter's advisor file, and the team's own record cut to the advisor's area.
 */
final class AdvisorRoom
{
    /** The thread a team's meetings live in: one per team and quarter, with several advisors speaking. */
    public const MEETING = 'meeting';

    /** @var array<string, mixed> */
    private array $book;

    /** @var array<int, array<string, array<string, string>>> */
    private array $quarterFiles = [];

    public function __construct(
        private readonly LlmClient $llm,
        private readonly ContentPack $content,
        private readonly ModelData $data,
        private readonly bool $enabled,
        private readonly int $maxTokens = 2000,
        ?string $root = null,
        private readonly ?QuarterRunner $runner = null,
    ) {
        $root ??= base_path('packages/content');
        $this->book = json_decode((string) file_get_contents("$root/advisors.json"), true, flags: JSON_THROW_ON_ERROR);
        foreach (glob("$root/advisors/q*.json") ?: [] as $file) {
            if (preg_match('/q(\d+)\.json$/', $file, $m) === 1) {
                $this->quarterFiles[(int) $m[1]] = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            }
        }
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /** @return array<string, string> words on the advisors screen, with the cost filled in */
    public function screenText(): array
    {
        $screen = (array) $this->book['screen'];
        $screen['cost_note'] = str_replace('{cost}', $this->costText(), (string) $screen['cost_note']);

        return array_map('strval', $screen);
    }

    public function costPerAnswer(): float
    {
        return $this->data->c('advisor_cost_per_answer');
    }

    public function limitFor(string $advisor): int
    {
        $limits = (array) $this->book['limits'];

        return (int) ($advisor === 'priya' ? $limits['answers_priya'] : $limits['answers_per_advisor']);
    }

    /** @return list<string> advisor keys in the order of the cards */
    public function keys(): array
    {
        return array_values(array_filter(array_column($this->cards(), 'key'), 'is_string'));
    }

    /** @return list<array<string, string>> the cards from the opening, which are the one source for names and roles */
    public function cards(): array
    {
        /** @var list<array<string, string>> $cards */
        $cards = (array) ($this->content->opening()['advisors'] ?? []);

        return array_values(array_filter($cards, fn (array $c) => isset($c['key']) && isset($this->book['advisors'][$c['key']])));
    }

    public function firstName(string $advisor): string
    {
        foreach ($this->cards() as $c) {
            if ($c['key'] === $advisor) {
                $name = (string) preg_replace('/^Dr\.\s+/', '', $c['name']);

                return explode(' ', $name)[0];
            }
        }

        return $advisor;
    }

    /**
     * Everything the advisors screen needs. Students see a dropped reply only as a short notice.
     * Faculty also see what was dropped and why.
     *
     * @return array<string, mixed>
     */
    public function view(Team $team, Quarter $quarter, bool $forFaculty): array
    {
        $threads = AdvisorThread::query()->where('team_id', $team->id)->where('quarter_id', $quarter->id)
            ->with(['messages.user'])->get()->keyBy('advisor');
        $screen = $this->screenText();
        $meeting = $threads->get(self::MEETING);
        $spokeInMeeting = [];
        foreach ($meeting->messages ?? [] as $m) {
            if ($m->role === AdvisorMessage::ADVISOR && $m->status === AdvisorMessage::SHOWN && $m->advisor !== null) {
                $spokeInMeeting[$m->advisor] = ($spokeInMeeting[$m->advisor] ?? 0) + 1;
            }
        }
        $cards = [];
        foreach ($this->cards() as $card) {
            $key = $card['key'];
            $first = $this->firstName($key);
            $messages = [];
            $used = $spokeInMeeting[$key] ?? 0;
            foreach ($threads->get($key)->messages ?? [] as $m) {
                $shown = $m->status === AdvisorMessage::SHOWN;
                if ($m->role === AdvisorMessage::ADVISOR && $shown) {
                    $used++;
                }
                if ($m->role === AdvisorMessage::ADVISOR && ! $shown && ! $forFaculty) {
                    $messages[] = ['id' => $m->id, 'from' => 'notice', 'who' => '', 'body' => str_replace('{first}', $first, $screen['dropped']), 'at' => $m->created_at?->toIso8601String()];

                    continue;
                }
                $messages[] = [
                    'id' => $m->id,
                    'from' => $m->role === AdvisorMessage::STUDENT ? 'team' : ($shown ? 'advisor' : 'dropped'),
                    'who' => $m->role === AdvisorMessage::STUDENT ? ($m->user->name ?? 'A teammate') : $first,
                    'body' => $m->body,
                    'reason' => $forFaculty ? $m->dropped_reason : null,
                    'at' => $m->created_at?->toIso8601String(),
                ];
            }
            $limit = $this->limitFor($key);
            $cards[] = $card + ['first' => $first, 'used' => $used, 'limit' => $limit, 'left' => max(0, $limit - $used), 'messages' => $messages];
        }

        return [
            'enabled' => $this->enabled,
            'open' => $quarter->status === Quarter::OPEN,
            'text' => $screen,
            'cards' => $cards,
            'meeting' => $this->meetingView($meeting, $forFaculty, $screen),
        ];
    }

    /**
     * The meeting transcript for the screen. A question that got no shown answer at all is followed by one notice.
     *
     * @param  array<string, string>  $screen
     * @return array{messages: list<array<string, mixed>>, invited: list<string>, min: int, max: int}
     */
    private function meetingView(?AdvisorThread $thread, bool $forFaculty, array $screen): array
    {
        $messages = [];
        $invited = [];
        $turn = null;
        $answered = true;
        foreach ($thread->messages ?? [] as $m) {
            if ($m->role === AdvisorMessage::STUDENT) {
                if (! $answered && ! $forFaculty && $turn !== null) {
                    $messages[] = ['id' => $turn.'-notice', 'from' => 'notice', 'who' => '', 'body' => $screen['meeting_dropped'], 'at' => null];
                }
                $turn = $m->id;
                $answered = false;
                $invited = array_values(array_filter((array) ($m->invited ?? []), 'is_string'));
                $messages[] = ['id' => $m->id, 'from' => 'team', 'who' => $m->user->name ?? 'A teammate', 'body' => $m->body, 'invited' => $invited, 'at' => $m->created_at?->toIso8601String()];

                continue;
            }
            $shown = $m->status === AdvisorMessage::SHOWN;
            $answered = $answered || $shown;
            if (! $shown && ! $forFaculty) {
                continue;
            }
            $messages[] = ['id' => $m->id, 'from' => $shown ? 'advisor' : 'dropped', 'who' => $this->firstName((string) $m->advisor), 'body' => $m->body,
                'reason' => $forFaculty ? $m->dropped_reason : null, 'at' => $m->created_at?->toIso8601String()];
        }
        if (! $answered && ! $forFaculty && $turn !== null) {
            $messages[] = ['id' => $turn.'-notice', 'from' => 'notice', 'who' => '', 'body' => $screen['meeting_dropped'], 'at' => null];
        }
        $limits = (array) $this->book['limits'];

        return ['messages' => $messages, 'invited' => $invited, 'min' => (int) $limits['meeting_min'], 'max' => (int) $limits['meeting_max']];
    }

    /** Shown answers this team has had from this advisor this quarter. */
    public function answersUsed(Team $team, Quarter $quarter, string $advisor): int
    {
        $oneToOne = AdvisorMessage::query()
            ->whereHas('thread', fn ($q) => $q->where('team_id', $team->id)->where('quarter_id', $quarter->id)->where('advisor', $advisor))
            ->where('role', AdvisorMessage::ADVISOR)->where('status', AdvisorMessage::SHOWN)->count();
        $inMeetings = AdvisorMessage::query()
            ->whereHas('thread', fn ($q) => $q->where('team_id', $team->id)->where('quarter_id', $quarter->id)->where('advisor', self::MEETING))
            ->where('role', AdvisorMessage::ADVISOR)->where('status', AdvisorMessage::SHOWN)->where('advisor', $advisor)->count();

        return $oneToOne + $inMeetings;
    }

    /**
     * The advisors this team has heard from this quarter, one to one or in a meeting.
     *
     * @return list<string>
     */
    public function consulted(Team $team, Quarter $quarter): array
    {
        $shown = AdvisorMessage::query()->with('thread')
            ->whereHas('thread', fn ($q) => $q->where('team_id', $team->id)->where('quarter_id', $quarter->id))
            ->where('role', AdvisorMessage::ADVISOR)->where('status', AdvisorMessage::SHOWN)->get();
        $keys = [];
        foreach ($shown as $m) {
            $keys[] = (string) ($m->advisor ?? $m->thread->advisor);
        }

        return array_values(array_unique(array_filter($keys, fn (string $k) => $k !== self::MEETING)));
    }

    /** All shown answers this team has had this quarter, which Halden pays for. */
    public function billableAnswers(Team $team, Quarter $quarter): int
    {
        return AdvisorMessage::query()
            ->whereHas('thread', fn ($q) => $q->where('team_id', $team->id)->where('quarter_id', $quarter->id))
            ->where('role', AdvisorMessage::ADVISOR)->where('status', AdvisorMessage::SHOWN)->count();
    }

    /**
     * A student asks an advisor something. The question is always stored in full. The answer is stored
     * either way, but shown (and billed) only if it passes the checks.
     *
     * @throws RuntimeException with a plain-English message when the question can't be asked
     */
    public function ask(Team $team, Quarter $quarter, string $advisor, User $user, string $question): AdvisorMessage
    {
        if (! $this->enabled) {
            throw new RuntimeException((string) $this->book['screen']['unavailable']);
        }
        if (! in_array($advisor, $this->keys(), true)) {
            throw new RuntimeException('There is no advisor by that name.');
        }
        if ($quarter->status !== Quarter::OPEN) {
            throw new RuntimeException((string) $this->book['screen']['closed']);
        }

        // Lock the thread so two teammates asking at once can't go past the limit.
        return DB::transaction(function () use ($team, $quarter, $advisor, $user, $question): AdvisorMessage {
            $thread = AdvisorThread::query()->firstOrCreate(['team_id' => $team->id, 'quarter_id' => $quarter->id, 'advisor' => $advisor]);
            AdvisorThread::query()->whereKey($thread->id)->lockForUpdate()->first();
            $used = $this->answersUsed($team, $quarter, $advisor);
            $limit = $this->limitFor($advisor);
            if ($used >= $limit) {
                throw new RuntimeException(str_replace('{name}', $this->firstName($advisor), (string) $this->book['screen']['none_left']));
            }

            AdvisorMessage::query()->create([
                'advisor_thread_id' => $thread->id, 'user_id' => $user->id, 'role' => AdvisorMessage::STUDENT, 'body' => $question,
            ]);

            [$system, $sources] = $this->systemPrompt($team, $quarter, $advisor, $used + 1 === $limit);
            $history = $this->history($thread);
            $teamText = array_column(array_filter($history, fn (array $m) => $m['role'] === 'user'), 'content');

            try {
                $reply = $this->llm->complete($system, $history, $this->maxTokens);
                $limits = (array) $this->book['limits'];
                $reason = $reply->cutOff ? 'The reply was cut off before it finished.' : ReplyCheck::failure($reply->text, [...$sources, ...$teamText], $teamText, (int) $limits['words_min'], (int) $limits['words_max']);
            } catch (Throwable $e) {
                Log::warning('Advisor call failed', ['advisor' => $advisor, 'team' => $team->id, 'error' => $e->getMessage()]);
                $reply = new LlmReply('');
                $reason = 'The advisor service did not answer: '.$e->getMessage();
            }

            return AdvisorMessage::query()->create([
                'advisor_thread_id' => $thread->id,
                'role' => AdvisorMessage::ADVISOR,
                'body' => $reply->text,
                'status' => $reason === null ? AdvisorMessage::SHOWN : AdvisorMessage::DROPPED,
                'dropped_reason' => $reason,
                'input_tokens' => $reply->inputTokens,
                'output_tokens' => $reply->outputTokens,
                'model' => $reply->model,
            ]);
        });
    }

    /**
     * The team calls a meeting: a question to two to four advisors at once. A hidden director picks who answers
     * (one to three, in order), and each speaks in turn with the whole transcript in front of them, so they can
     * take issue with each other. Every shown answer counts against that advisor and is billed like any other.
     *
     * @param  list<string>  $invited
     * @return list<AdvisorMessage> the replies, shown or dropped
     *
     * @throws RuntimeException with a plain-English message when the meeting can't be held
     */
    public function meet(Team $team, Quarter $quarter, array $invited, User $user, string $question): array
    {
        $screen = (array) $this->book['screen'];
        $limits = (array) $this->book['limits'];
        if (! $this->enabled) {
            throw new RuntimeException((string) $screen['unavailable']);
        }
        if ($quarter->status !== Quarter::OPEN) {
            throw new RuntimeException((string) $screen['closed']);
        }
        $invited = array_values(array_unique(array_filter($invited, fn ($k) => in_array($k, $this->keys(), true))));
        if (count($invited) < (int) $limits['meeting_min']) {
            throw new RuntimeException((string) $screen['meeting_too_few']);
        }
        if (count($invited) > (int) $limits['meeting_max']) {
            throw new RuntimeException((string) $screen['meeting_too_many']);
        }

        return DB::transaction(function () use ($team, $quarter, $invited, $user, $question, $screen, $limits): array {
            $thread = AdvisorThread::query()->firstOrCreate(['team_id' => $team->id, 'quarter_id' => $quarter->id, 'advisor' => self::MEETING]);
            AdvisorThread::query()->whereKey($thread->id)->lockForUpdate()->first();
            $left = [];
            foreach ($invited as $key) {
                $left[$key] = $this->limitFor($key) - $this->answersUsed($team, $quarter, $key);
                if ($left[$key] <= 0) {
                    throw new RuntimeException(str_replace('{name}', $this->firstName($key), (string) $screen['meeting_done']));
                }
            }

            AdvisorMessage::query()->create([
                'advisor_thread_id' => $thread->id, 'user_id' => $user->id, 'role' => AdvisorMessage::STUDENT, 'invited' => $invited, 'body' => $question,
            ]);

            $transcript = $this->transcript($thread);
            $teamText = array_column(array_filter($transcript, fn (array $l) => $l['team']), 'text');
            $names = implode(', ', array_map(fn (string $k) => $this->firstName($k).' ('.$this->roleOf($k).')', $invited));
            $speakers = $this->director($invited, $transcript, (int) $limits['meeting_speakers_max']);

            $replies = [];
            $saidThisTurn = [];
            foreach ($speakers as $key) {
                [$system, $sources] = $this->systemPrompt($team, $quarter, $key, $left[$key] === 1);
                $system .= "

You're in a meeting:
".implode('
', array_map(fn ($r) => "- $r", (array) $this->book['meeting']['rules']))
                    .'
'.str_replace('{names}', $names, (string) $this->book['meeting']['with_line']);
                $lines = array_map(fn (array $l) => $l['who'].': '.$l['text'], [...$transcript, ...$saidThisTurn]);
                $userMsg = 'The meeting so far:

'.implode('

', $lines).'

'.str_replace('{first}', $this->firstName($key), (string) $this->book['meeting']['your_turn']);
                try {
                    $reply = $this->llm->complete($system, [['role' => 'user', 'content' => $userMsg]], $this->maxTokens);
                    $heard = array_column($saidThisTurn, 'text');
                    $reason = $reply->cutOff ? 'The reply was cut off before it finished.'
                        : ReplyCheck::failure($reply->text, [...$sources, ...$teamText, ...$heard], $teamText, (int) $limits['words_min'], (int) $limits['words_max']);
                } catch (Throwable $e) {
                    Log::warning('Advisor call failed', ['advisor' => $key, 'team' => $team->id, 'error' => $e->getMessage()]);
                    $reply = new LlmReply('');
                    $reason = 'The advisor service did not answer: '.$e->getMessage();
                }
                $replies[] = AdvisorMessage::query()->create([
                    'advisor_thread_id' => $thread->id,
                    'role' => AdvisorMessage::ADVISOR,
                    'advisor' => $key,
                    'body' => $reply->text,
                    'status' => $reason === null ? AdvisorMessage::SHOWN : AdvisorMessage::DROPPED,
                    'dropped_reason' => $reason,
                    'input_tokens' => $reply->inputTokens,
                    'output_tokens' => $reply->outputTokens,
                    'model' => $reply->model,
                ]);
                if ($reason === null) {
                    $saidThisTurn[] = ['who' => $this->firstName($key), 'text' => $reply->text, 'team' => false];
                }
            }

            return $replies;
        });
    }

    /**
     * The hidden director: which of the advisors in the meeting answer, and in what order. Falls back to the first
     * one invited when the model's pick can't be read.
     *
     * @param  list<string>  $invited
     * @param  list<array{who: string, text: string, team: bool}>  $transcript
     * @return list<string>
     */
    private function director(array $invited, array $transcript, int $maxSpeakers): array
    {
        $system = implode('
', array_map(fn ($r) => "- $r", (array) $this->book['meeting']['director']['rules']));
        $roster = implode('
', array_map(fn (string $k) => "$k: ".$this->firstName($k).', '.$this->roleOf($k), $invited));
        $lines = array_map(fn (array $l) => $l['who'].': '.$l['text'], $transcript);
        $user = "In the meeting, with answers left:
$roster

The conversation so far (the last message is the one to answer):

".implode('

', $lines);
        try {
            $reply = $this->llm->complete($system, [['role' => 'user', 'content' => $user]], 300);
            $data = json_decode(trim((string) preg_replace('/^```(?:json)?|```$/m', '', $reply->text)), true);
            $picked = [];
            foreach ((array) ($data['speakers'] ?? []) as $k) {
                if (is_string($k) && in_array($k, $invited, true) && ! in_array($k, $picked, true)) {
                    $picked[] = $k;
                }
            }
            if ($picked !== []) {
                return array_slice($picked, 0, $maxSpeakers);
            }
        } catch (Throwable $e) {
            Log::warning('Meeting director failed', ['error' => $e->getMessage()]);
        }

        return [$invited[0]];
    }

    /**
     * The meeting as a list of lines: who spoke and what they said. Dropped replies are left out; nothing is shortened.
     *
     * @return list<array{who: string, text: string, team: bool}>
     */
    private function transcript(AdvisorThread $thread): array
    {
        $out = [];
        foreach ($thread->messages()->with('user')->get() as $m) {
            if ($m->role === AdvisorMessage::STUDENT) {
                $out[] = ['who' => ($m->user->name ?? 'The team').' (the team)', 'text' => $m->body, 'team' => true];
            } elseif ($m->status === AdvisorMessage::SHOWN) {
                $out[] = ['who' => $this->firstName((string) $m->advisor), 'text' => $m->body, 'team' => false];
            }
        }

        return $out;
    }

    private function roleOf(string $advisor): string
    {
        foreach ($this->cards() as $c) {
            if ($c['key'] === $advisor) {
                return $c['role'];
            }
        }

        return '';
    }

    /**
     * The conversation so far, as the model sees it. Dropped replies are left out, and the question
     * that led to one is folded into the next question so turns still alternate. Nothing is shortened.
     *
     * @return list<array{role: 'user'|'assistant', content: string}>
     */
    private function history(AdvisorThread $thread): array
    {
        $out = [];
        $pending = [];
        foreach ($thread->messages()->with('user')->get() as $m) {
            if ($m->role === AdvisorMessage::STUDENT) {
                $who = $m->user?->name;
                $pending[] = ($who ? "$who: " : '').$m->body;

                continue;
            }
            if ($m->status !== AdvisorMessage::SHOWN || $pending === []) {
                continue;
            }
            $out[] = ['role' => 'user', 'content' => implode("\n\n", $pending)];
            $out[] = ['role' => 'assistant', 'content' => $m->body];
            $pending = [];
        }
        if ($pending !== []) {
            $out[] = ['role' => 'user', 'content' => implode("\n\n", $pending)];
        }

        return $out;
    }

    /**
     * @return array{0: string, 1: list<string>} the system prompt, and every text it was built from (for the number check)
     */
    public function systemPrompt(Team $team, Quarter $quarter, string $advisor, bool $lastAnswer): array
    {
        /** @var array<string, mixed> $who */
        $who = $this->book['advisors'][$advisor];
        $card = collect($this->cards())->firstWhere('key', $advisor) ?? [];
        $file = $this->quarterFiles[$quarter->number][$advisor] ?? [];
        // A 7-week course: the week's second quarter is on the advisor's desk too.
        $partner = $quarter->isFirstOfWeek() ? $quarter->partner() : null;
        if ($partner !== null) {
            foreach ($this->quarterFiles[$partner->number][$advisor] ?? [] as $k => $v) {
                $file[$k] = trim(($file[$k] ?? '').' '.$v);
            }
        }
        $team->loadMissing('section');

        $parts = [];
        $parts[] = (string) $who['persona'];
        $parts[] = 'About you: '.$who['details'];
        $parts[] = 'Your own point of view, which you hold sincerely: '.$who['point_of_view'];
        $parts[] = "How people here describe you: good at {$card['good_at']} They also say: {$card['watch_out']}";
        $parts[] = "It is {$quarter->label()}. You're speaking with {$team->name}, the EVP's team, who are deciding what Halden does this quarter.";

        $rules = (array) $this->book['rules'];
        if (isset($who['rules_extra'])) {
            $rules[] = (string) $who['rules_extra'];
        }
        // The words the check drops a reply for, so the advisor avoids them instead of losing the answer.
        $rules[] = 'Never use these words, even in passing, unless the team used them first: '.implode(', ', ReplyCheck::BANNED).'.';
        if ($lastAnswer) {
            $rules[] = (string) $this->book['last_answer'];
        }
        $parts[] = "How you talk with them:\n".implode("\n", array_map(fn ($r) => "- $r", $rules));

        $know = [];
        if (($file['facts'] ?? '') !== '') {
            $know[] = 'What you know this quarter (the only figures you may quote, apart from the team\'s own results below): '.$file['facts'];
        }
        if (($file['stance'] ?? '') !== '') {
            $know[] = 'What you believe this quarter: '.$file['stance'];
        }
        if (($file['signal'] ?? '') !== '') {
            $know[] = 'What you would like them to notice, if their questions lead there. Let them get there; never hand it over as an instruction: '.$file['signal'];
        }
        if (($file['misdirection'] ?? '') !== '') {
            $know[] = 'Where your own view pulls you this quarter. Act on it naturally when it comes up, and never describe it as a bias: '.$file['misdirection'];
        }
        $prices = $this->pricesLine($quarter);
        if ($prices !== '') {
            $know[] = $prices;
        }
        if ($know === []) {
            $know[] = 'You have nothing particular on your desk about this quarter. If asked, say this one is mostly for someone else, and say who.';
        }
        $parts[] = implode("\n\n", $know);

        $record = $this->teamRecord($team, $quarter, array_values(array_filter((array) $who['sees'], 'is_string')));
        $parts[] = "What you know about this team:\n".$record;

        return [implode("\n\n", $parts), [(string) ($file['facts'] ?? ''), $record, $prices]];
    }

    /**
     * The team's strategy sentence and its published results, cut to what this advisor would see.
     *
     * @param  list<string>  $sees
     */
    private function teamRecord(Team $team, Quarter $current, array $sees): string
    {
        $lines = [];
        if ($team->strategy_become && $team->strategy_by) {
            $lines[] = "The team's own sentence: \"Halden should become a company that {$team->strategy_become} by {$team->strategy_by}.\"";
        }
        if ($team->first_meeting !== null && in_array('relations', $sees, true)) {
            $lines[] = $team->first_meeting === 'marcus' ? 'On their first day they met Marcus Delacroix before Ingrid Vestergaard.' : 'On their first day they met Ingrid Vestergaard before Marcus Delacroix.';
        }

        $published = TeamQuarter::query()->where('team_id', $team->id)
            ->whereHas('quarter', fn ($q) => $q->where('number', '<', $current->number)->where('status', Quarter::PUBLISHED))
            ->with('quarter')->get()->sortByDesc(fn (TeamQuarter $tq) => $tq->quarter->number)->take(3);

        foreach ($published as $tq) {
            $r = $tq->results ?? [];
            $d = $tq->effective_decisions ?? [];
            $bits = [];
            $m = fn (string $k) => isset($r[$k]) ? $this->money((float) $r[$k]) : 'n/a';
            if (array_intersect(['money', 'kpis', 'strategy'], $sees) !== []) {
                $bits[] = "earnings before interest, tax and depreciation {$m('money.ebitda')}, free cash flow {$m('money.fcf')}, net debt {$m('money.net_debt_end')}";
            }
            if (in_array('segments', $sees, true)) {
                $bits[] = "oil fields {$m('segment.oil_fields')}, refineries {$m('segment.refineries')}, Geneva {$m('segment.geneva')}, gas stations {$m('segment.gas_stations')}";
            }
            if (in_array('operations', $sees, true)) {
                $rot = ($d['rot_posture'] ?? 'run') === 'run' ? 'Rotterdam at '.($d['rot_run'] ?? '?').'%' : 'Rotterdam '.(($d['rot_posture'] ?? '') === 'idle' ? 'paused' : 'closed');
                $bits[] = ($d['rigs'] ?? '?').' rigs in Texas, Texas output '.number_format(round((float) ($r['ops.permian_prod'] ?? 0), -2)).' barrels a day, Baton Rouge at '.($d['br_run'] ?? '?').'%, '.$rot;
            }
            if (in_array('plant', $sees, true)) {
                $bits[] = 'plant condition '.round((float) ($r['kpi.plant_condition'] ?? 0), 1).' out of 100';
            }
            if (in_array('retail', $sees, true)) {
                $bits[] = "gas stations earned {$m('segment.gas_stations')}, Cordell fuel {$m('line.cordell_fuel')}, Cordell shops {$m('line.cordell_shop')}";
            }
            if (array_intersect(['relations', 'segments', 'operations'], $sees) !== [] && isset($r['ops.kessana_take'])) {
                if ((float) ($r['ops.kessana_forgone'] ?? 0) > 0) {
                    $bits[] = 'Halden has handed the Kessana block back';
                } elseif (abs((float) $r['ops.kessana_take'] - 0.62) > 1e-9) {
                    $bits[] = 'the Kessana government now takes '.round((float) $r['ops.kessana_take'] * 100).'% of profit oil (the old contract said 62%)';
                }
            }
            if (in_array('kpis', $sees, true)) {
                $bits[] = 'profit per barrel $'.number_format((float) ($r['kpi.profit_per_barrel'] ?? 0), 2).', return on capital '.round((float) ($r['kpi.roace_pct'] ?? 0), 1).'%';
            }
            if ($bits !== []) {
                $lines[] = $tq->quarter->label().': '.implode('; ', $bits).'.';
            }
        }
        if ($published->isEmpty()) {
            $lines[] = 'No results yet. This is their first quarter in the job.';
        }

        return implode("\n", $lines);
    }

    /** This quarter's prices for this class, plus the capital terms once the projects page is open. */
    private function pricesLine(Quarter $quarter): string
    {
        if ($this->runner === null || ! $this->runner->hasMarket($quarter)) {
            return '';
        }
        $m = $this->runner->marketFor($quarter);
        $line = sprintf('Prices everyone at Halden can see this quarter: US oil (WTI) $%.2f a barrel; refining margins $%.2f on the Gulf Coast, $%.2f in Europe and $%.2f in Asia; the euro at $%.4f; %.2f kroner and %.3f Singapore dollars to the US dollar.',
            $m['wti'], $m['gc'], $m['nwe'], $m['sg'], $m['eurusd'], $m['usdnok'], $m['usdsgd']);
        if ($quarter->company_quarter >= '2028Q2') {
            $t = $this->runner->capitalTerms($quarter);
            $line .= sprintf(' This quarter the board will let Ingrid spend up to $%sM on new projects, and Halden\'s cost of capital is %.1f%%.', number_format($t['envelope']), $t['rate'] * 100);
        }

        return $line;
    }

    private function money(float $m): string
    {
        return ($m < 0 ? '-' : '').'$'.number_format(abs($m)).'M';
    }

    private function costText(): string
    {
        $k = $this->costPerAnswer() * 1000;

        return $k >= 1000 ? '$'.rtrim(rtrim(number_format($k / 1000, 2), '0'), '.').'M' : '$'.number_format($k).'K';
    }
}
