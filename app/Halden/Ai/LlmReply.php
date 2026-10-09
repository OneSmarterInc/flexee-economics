<?php

namespace App\Halden\Ai;

final readonly class LlmReply
{
    public function __construct(
        public string $text,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public string $model = '',
    ) {}
}
