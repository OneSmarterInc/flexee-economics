<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Section 1A of the design: these words never reach a student's screen.
 * Checks every string in the content pack, skipping notes for us (keys starting with "_"),
 * internal keys, and {placeholders} that are filled in before display.
 */
class ContentWordsTest extends TestCase
{
    private const BANNED = ['room', 'rooms', 'war room', 'lever', 'levers', 'tier', 'tiers', 'converge', 'commit', 'segment', 'segments', 'cohort', 'cohorts', 'runtime', 'round', 'rounds'];

    // Keys whose values never reach a screen: ids, file names, and the rules the advisors are given (which name the words they must avoid).
    private const INTERNAL_KEYS = ['key', 'band_on', 'file', 'sees', 'rules', 'on'];

    public function test_student_content_uses_plain_words(): void
    {
        $root = dirname(__DIR__, 2).'/packages/content';
        $problems = [];
        $files = ['opening', 'quarters', 'levers', 'advisors'];
        foreach (glob("$root/advisors/q*.json") ?: [] as $f) {
            $files[] = 'advisors/'.basename($f, '.json');
        }
        foreach ($files as $name) {
            $data = json_decode((string) file_get_contents("$root/$name.json"), true, flags: JSON_THROW_ON_ERROR);
            $this->walk($data, $name, $problems);
        }
        $this->assertSame([], $problems);
    }

    /** @param list<string> $problems */
    private function walk(mixed $node, string $path, array &$problems): void
    {
        if (is_array($node)) {
            foreach ($node as $k => $v) {
                if (is_string($k) && (str_starts_with($k, '_') || in_array($k, self::INTERNAL_KEYS, true))) {
                    continue;
                }
                $this->walk($v, "$path.$k", $problems);
            }

            return;
        }
        if (! is_string($node)) {
            return;
        }
        $text = (string) preg_replace('/\{[a-z_]+\}/', '', $node);
        foreach (self::BANNED as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/i', $text) === 1) {
                $problems[] = "$path says \"$word\"";
            }
        }
        if (preg_match('/\b[a-z]+_[a-z_]+\b/', $text) === 1) {
            $problems[] = "$path shows an internal name";
        }
    }
}
