<?php

namespace App\Halden\Ai;

use App\Models\Quarter;
use App\Models\Team;
use App\Models\TeamQuarter;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "What you're carrying": a short note above each briefing from Quarter 2 on, written once when the
 * quarter opens so the whole team reads the same words. Built only from the team's own record.
 * A draft that fails a check is dropped silently, never retried, and the reason is kept for faculty.
 */
final class Carrying
{
    /** @var array<string, mixed> */
    private array $book;

    public function __construct(
        private readonly LlmClient $llm,
        private readonly Findings $findings,
        private readonly bool $enabled,
        ?string $root = null,
    ) {
        $root ??= base_path('packages/content');
        $this->book = json_decode((string) file_get_contents("$root/carrying.json"), true, flags: JSON_THROW_ON_ERROR);
    }

    public function title(): string
    {
        return (string) $this->book['title'];
    }

    /** Writes the note for every team that doesn't have one yet. Safe to call more than once. */
    public function writeFor(Quarter $quarter): void
    {
        if (! $this->enabled || $quarter->number < 2) {
            return;
        }
        foreach ($quarter->section->teams()->orderBy('id')->get() as $team) {
            $tq = TeamQuarter::query()->firstOrCreate(['team_id' => $team->id, 'quarter_id' => $quarter->id]);
            if ($tq->carrying_status === null) {
                $this->write($team, $quarter, $tq);
            }
        }
    }

    public function write(Team $team, Quarter $quarter, TeamQuarter $tq): void
    {
        $record = $this->record($team, $quarter);
        $system = implode("\n", array_map(fn ($r) => "- $r", (array) $this->book['rules']));
        $text = '';
        try {
            $reply = $this->llm->complete($system, [['role' => 'user', 'content' => $record]], 2000);
            $text = trim($reply->text);
            $reason = $reply->cutOff ? 'The reply was cut off before it finished.'
                : (ReplyCheck::failure($text, [$record], [(string) $team->strategy_become, (string) $team->strategy_by], (int) $this->book['words_min'], (int) $this->book['words_max'])
                    ?? $this->advice($text));
        } catch (Throwable $e) {
            Log::warning('Carrying note failed', ['team' => $team->id, 'quarter' => $quarter->id, 'error' => $e->getMessage()]);
            $reason = 'The AI service did not answer: '.$e->getMessage();
        }
        $tq->update([
            'carrying' => $reason === null ? $text : null,
            'carrying_status' => $reason === null ? 'ok' : 'dropped',
            'carrying_reason' => $reason === null ? null : $reason.($text !== '' ? ' Draft: '.mb_substr($text, 0, 180) : ''),
        ]);
    }

    private function advice(string $text): ?string
    {
        foreach ((array) $this->book['advice_phrases'] as $phrase) {
            if (preg_match('/\b'.preg_quote((string) $phrase, '/').'\b/i', $text) === 1) {
                return "Gave advice (\"$phrase\").";
            }
        }

        return null;
    }

    /** The team's sentence, and up to its last three quarters of settings and results. */
    private function record(Team $team, Quarter $quarter): string
    {
        $lines = [];
        $lines[] = $team->strategy_become && $team->strategy_by
            ? "Their sentence about the company: \"Halden should become a company that {$team->strategy_become} by {$team->strategy_by}.\""
            : 'They have not written a sentence about what the company should become.';
        $earlier = TeamQuarter::query()->where('team_id', $team->id)
            ->whereHas('quarter', fn ($q) => $q->where('number', '<', $quarter->number)->where('status', Quarter::PUBLISHED))
            ->with('quarter')->get()->sortByDesc(fn (TeamQuarter $t) => $t->quarter->number)->take(3)->sortBy(fn (TeamQuarter $t) => $t->quarter->number);
        foreach ($earlier as $tq) {
            $r = $tq->results ?? [];
            $lines[] = $tq->quarter->label().': '.implode('; ', $this->findings->sheet($tq)).'.';
            $lines[] = sprintf('%s results: earnings before interest, tax and depreciation %s; free cash flow %s; profit per barrel $%.2f.',
                $tq->quarter->label(), $this->money((float) ($r['money.ebitda'] ?? 0)), $this->money((float) ($r['money.fcf'] ?? 0)), (float) ($r['kpi.profit_per_barrel'] ?? 0));
        }
        $lines[] = "They are now starting {$quarter->label()}.";

        return implode("\n", $lines);
    }

    private function money(float $m): string
    {
        return ($m < 0 ? '-' : '').'$'.number_format(abs($m)).'M';
    }
}
