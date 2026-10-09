<?php

namespace App\Halden\Ai;

/**
 * Checks every AI-written text before anyone sees it (CLAUDE.md rule 5). A text that fails is dropped,
 * never retried, and the reason goes to faculty only.
 */
final class ReplyCheck
{
    /** Words an advisor must never use, matched as whole words. Words the team itself used are allowed. */
    public const BANNED = [
        'room', 'war room', 'lever', 'levers', 'tier', 'tiers', 'converge', 'commit', 'segment', 'segments',
        'cohort', 'cohorts', 'runtime', 'round', 'rounds', 'simulation', 'sim', 'game', 'course', 'class',
        'student', 'students', 'professor', 'instructor', 'week', 'weeks', 'quarter 1', 'quarter 2',
        'quarter 3', 'quarter 4', 'delve', 'leverage', 'synergy', 'paradigm', 'tapestry', 'moat',
    ];

    /**
     * @param  list<string>  $sources  every text the writer was given (facts, the team's numbers, the team's own words)
     * @param  list<string>  $teamText  what the team wrote, whose words are exempt from the banned list
     * @return string|null the reason it failed, or null if it passed
     */
    public static function failure(string $text, array $sources, array $teamText, int $minWords, int $maxWords): ?string
    {
        $text = trim($text);
        $words = str_word_count(strip_tags($text));
        if ($words < $minWords || $words > $maxWords) {
            return "Length: $words words (allowed $minWords to $maxWords).";
        }
        if (preg_match('/(\*\*|^#{1,6}\s|^\s*[-*•]\s|^\s*\d+\.\s|```)/m', $text) === 1) {
            return 'Formatting: the reply used a list, heading or other markup.';
        }
        $team = mb_strtolower(implode(' ', $teamText));
        foreach (self::BANNED as $word) {
            $pattern = '/\b'.preg_quote($word, '/').'\b/iu';
            if (preg_match($pattern, $text) === 1 && preg_match($pattern, $team) !== 1) {
                return "Banned word: \"$word\".";
            }
        }
        $allowed = self::dollarFigures(implode("\n", $sources));
        foreach (self::dollarFigures($text) as $raw => $value) {
            if (! self::isAllowed($value, $allowed)) {
                return "Dollar figure not in its inputs: $raw.";
            }
        }

        return null;
    }

    /**
     * Every dollar amount in a text, as dollars. "$3,750M", "$3.75 billion" and "-$200M" all count.
     *
     * @return array<string, float> the figure as written => its value in dollars
     */
    public static function dollarFigures(string $text): array
    {
        $found = [];
        $n = preg_match_all('/\$\s?(\d[\d,]*(?:\.\d+)?)\s*(billion|million|thousand|bn|[BMK](?![a-z]))?/i', $text, $m, PREG_SET_ORDER);
        if ($n === false) {
            return [];
        }
        foreach ($m as $hit) {
            $value = (float) str_replace(',', '', $hit[1]);
            $unit = strtolower($hit[2] ?? '');
            $value *= match ($unit) {
                'billion', 'bn', 'b' => 1e9,
                'million', 'm' => 1e6,
                'thousand', 'k' => 1e3,
                default => 1.0,
            };
            $found[trim($hit[0])] = $value;
        }

        return $found;
    }

    /** @param  array<string, float>  $allowed */
    private static function isAllowed(float $value, array $allowed): bool
    {
        foreach ($allowed as $a) {
            // Allow the rounding a person would do when saying a figure out loud.
            if (abs($value - $a) <= max(0.015 * abs($a), 0.005)) {
                return true;
            }
        }

        return false;
    }
}
