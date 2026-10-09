<?php

namespace App\Halden\Ai;

use App\Models\AdvisorThread;
use App\Models\FacultyDraft;
use App\Models\Quarter;
use App\Models\TeamQuarter;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * The three drafting tools for faculty: memo feedback, a proposed writing score and a check that the memo
 * matches the decisions. They only ever draft. A draft that fails a check is kept, marked dropped,
 * and shown to faculty with the reason; it is never retried on its own.
 */
final class FacultyDrafts
{
    /** @var array<string, mixed> */
    private array $book;

    public function __construct(
        private readonly LlmClient $llm,
        private readonly Findings $findings,
        private readonly AdvisorRoom $room,
        private readonly bool $enabled,
        ?string $root = null,
    ) {
        $root ??= base_path('packages/content');
        $this->book = json_decode((string) file_get_contents("$root/faculty_ai.json"), true, flags: JSON_THROW_ON_ERROR);
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /** @return array<string, string> */
    public function screenText(): array
    {
        return array_map('strval', (array) $this->book['screen']);
    }

    /** @return array<string, string> kind => label */
    public function labels(): array
    {
        $out = [];
        foreach (FacultyDraft::KINDS as $k) {
            $out[$k] = (string) $this->book[$k]['label'];
        }

        return $out;
    }

    /**
     * Everything the tools are given, so faculty can see it too.
     *
     * @return array{memo: string, sheet: list<string>, found: list<string>}
     */
    public function inputs(TeamQuarter $tq): array
    {
        $consulted = AdvisorThread::query()->where('team_id', $tq->team_id)->where('quarter_id', $tq->quarter_id)
            ->whereHas('messages', fn ($q) => $q->where('role', 'advisor')->where('status', 'shown'))
            ->pluck('advisor')->all();
        $names = [];
        foreach ($this->room->cards() as $c) {
            $names[$c['key']] = $c['name'];
        }
        $key = array_values(array_map('strval', (array) ($this->book['key_advisors'][(string) $tq->quarter->number] ?? [])));

        return [
            'memo' => (string) $tq->memo,
            'sheet' => $this->findings->sheet($tq),
            'found' => $this->findings->found($tq, array_values(array_map('strval', $consulted)), $key, $names),
        ];
    }

    /**
     * Drafts all three for one team's quarter. Each runs on its own, so one failure doesn't stop the others.
     *
     * @return list<FacultyDraft>
     */
    public function draftAll(TeamQuarter $tq, User $by): array
    {
        if (! $this->enabled) {
            throw new RuntimeException('The drafting tools need an Anthropic API key on the server.');
        }
        if (! in_array($tq->quarter->status, [Quarter::CLOSED, Quarter::PUBLISHED], true)) {
            throw new RuntimeException('Drafts are made after the quarter closes, from what the team actually ran.');
        }
        $in = $this->inputs($tq);

        return array_map(fn (string $kind) => $this->draft($tq, $kind, $in, $by), FacultyDraft::KINDS);
    }

    /** @param  array{memo: string, sheet: list<string>, found: list<string>}  $in */
    private function draft(TeamQuarter $tq, string $kind, array $in, User $by): FacultyDraft
    {
        /** @var array<string, mixed> $spec */
        $spec = $this->book[$kind];
        $rules = [...(array) $this->book['common_rules'], ...(array) $spec['instructions']];
        $system = implode("\n", array_map(fn ($r) => "- $r", $rules));

        // The memo goes in whole. Nothing a student wrote is ever cut before a model reads it.
        $memo = trim($in['memo']) === '' ? '(The team saved no memo.)' : $in['memo'];
        $user = $tq->quarter->label()." · {$tq->team->name}\n\n"
            ."THE TEAM'S MEMO:\n$memo\n\n"
            ."WHAT THE TEAM SET THIS QUARTER:\n".implode("\n", $in['sheet'])."\n\n"
            ."WHAT THE MODEL FOUND:\n".implode("\n", $in['found']);

        $text = '';
        $data = null;
        $reason = null;
        $reply = new LlmReply('');
        try {
            $reply = $this->llm->complete($system, [['role' => 'user', 'content' => $user]], 4000);
            $text = trim($reply->text);
            if ($reply->cutOff) {
                throw new RuntimeException('the reply was cut off before it finished');
            }
            $sources = [$user];
            $team = [$in['memo']];
            if ($kind === 'feedback') {
                $reason = ReplyCheck::failure($text, $sources, $team, (int) $spec['words_min'], (int) $spec['words_max'])
                    ?? $this->threeParagraphs($text);
            } else {
                $data = $this->json($text);
                $note = (string) ($data['reason'] ?? $data['note'] ?? '');
                $reason = $this->shape($kind, $data, $spec) ?? ReplyCheck::failure($note, $sources, $team, 3, 90);
            }
        } catch (JsonException) {
            $reason = 'The reply was not in the expected form.';
        } catch (Throwable $e) {
            Log::warning('Faculty draft failed', ['kind' => $kind, 'team_quarter' => $tq->id, 'error' => $e->getMessage()]);
            $reason = 'The AI service did not answer: '.$e->getMessage();
        }

        if ($kind === 'writing' && $reason === null && is_int($data['score'] ?? null)) {
            $tq->writing_score_ai = $data['score'];
            $tq->writing_score = $tq->finalWritingScore();
            $tq->save();
        }

        return FacultyDraft::query()->create([
            'team_quarter_id' => $tq->id,
            'kind' => $kind,
            'text' => $text,
            'data' => $data,
            'status' => $reason === null ? 'ok' : 'dropped',
            'dropped_reason' => $reason,
            'input_tokens' => $reply->inputTokens,
            'output_tokens' => $reply->outputTokens,
            'model' => $reply->model,
            'requested_by' => $by->id,
        ]);
    }

    private function threeParagraphs(string $text): ?string
    {
        foreach (['What held:', 'What was thin:', 'What to carry:'] as $start) {
            if (! str_contains($text, $start)) {
                return "Shape: the draft is missing the '$start' paragraph.";
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function json(string $text): array
    {
        $text = trim((string) preg_replace('/^```(?:json)?|```$/m', '', $text));
        $data = json_decode($text, true, flags: JSON_THROW_ON_ERROR);

        return is_array($data) ? $data : throw new JsonException('not an object');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $spec
     */
    private function shape(string $kind, array $data, array $spec): ?string
    {
        if ($kind === 'writing') {
            $score = $data['score'] ?? null;
            if (! is_int($score) || $score < (int) $spec['scale_min'] || $score > (int) $spec['scale_max']) {
                return 'The proposed score was not a whole number from '.$spec['scale_min'].' to '.$spec['scale_max'].'.';
            }

            return is_string($data['reason'] ?? null) ? null : 'The proposed score came without a reason.';
        }

        return is_bool($data['mismatch'] ?? null) && is_string($data['note'] ?? null) ? null : 'The check did not say clearly whether the memo matches.';
    }
}
