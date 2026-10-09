<?php

namespace App\Halden\Ai;

interface LlmClient
{
    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     */
    public function complete(string $system, array $messages, int $maxTokens): LlmReply;
}
