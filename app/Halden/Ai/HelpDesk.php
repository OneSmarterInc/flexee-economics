<?php

namespace App\Halden\Ai;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The help button. It is told how the website works and nothing about the company, so there is
 * nothing in it to give away ("starved, not forbidden"). One question, one answer, nothing stored.
 */
final class HelpDesk
{
    /** @var array<string, mixed> */
    private array $book;

    public function __construct(private readonly LlmClient $llm, private readonly bool $enabled, ?string $root = null)
    {
        $root ??= base_path('packages/content');
        $this->book = json_decode((string) file_get_contents("$root/help.json"), true, flags: JSON_THROW_ON_ERROR);
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /** @return array{screen: array<string, string>, faq: list<array{q: string, a: string}>} */
    public function view(): array
    {
        /** @var list<array{q: string, a: string}> $faq */
        $faq = array_values((array) $this->book['faq']);

        return ['screen' => array_map('strval', (array) $this->book['screen']), 'faq' => $faq];
    }

    /** @return string|null the answer, or null when it failed a check (the student sees a short apology) */
    public function answer(string $question): ?string
    {
        if (! $this->enabled) {
            return null;
        }
        $faq = implode("\n", array_map(fn (array $f) => "Q: {$f['q']}\nA: {$f['a']}", $this->view()['faq']));
        $system = implode("\n", array_map(fn ($r) => "- $r", (array) $this->book['rules']))."\n\nThe answers you may use:\n$faq";
        try {
            $reply = $this->llm->complete($system, [['role' => 'user', 'content' => $question]], 1500);
        } catch (Throwable $e) {
            Log::warning('Help assistant failed', ['error' => $e->getMessage()]);

            return null;
        }
        $text = trim($reply->text);
        // No figures at all: the help desk has none to give, so any dollar amount is invented.
        $reason = $reply->cutOff ? 'cut off' : ReplyCheck::failure($text, [], [$question], 3, 90, ReplyCheck::SCREEN_BANNED);
        if ($reason !== null) {
            Log::info('Help answer dropped', ['reason' => $reason]);

            return null;
        }

        return $text;
    }
}
