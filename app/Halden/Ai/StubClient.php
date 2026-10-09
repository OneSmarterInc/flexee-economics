<?php

namespace App\Halden\Ai;

/**
 * Stands in for the real model in tests and when no API key is set. It answers with a fixed,
 * harmless sentence, or with whatever a test queues up, and records what it was asked.
 */
final class StubClient implements LlmClient
{
    /** @var list<string> */
    private array $queued = [];

    /** @var list<array{system: string, messages: list<array{role: string, content: string}>}> */
    public array $calls = [];

    public function queue(string ...$replies): void
    {
        foreach ($replies as $r) {
            $this->queued[] = $r;
        }
    }

    public function complete(string $system, array $messages, int $maxTokens): LlmReply
    {
        $this->calls[] = ['system' => $system, 'messages' => $messages];
        $text = array_shift($this->queued)
            ?? 'That depends on what the numbers in front of you say. Look at what each part of the business earns on its own, then decide what you are willing to defend to the board.';

        return new LlmReply($text, (int) ceil(strlen($system) / 4), (int) ceil(strlen($text) / 4), 'stub');
    }
}
