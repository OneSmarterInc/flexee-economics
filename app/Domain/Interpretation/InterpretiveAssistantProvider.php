<?php

namespace App\Domain\Interpretation;

interface InterpretiveAssistantProvider
{
    public function providerName(): string;

    public function modelName(): ?string;

    /**
     * @param  array<string, mixed>  $context
     * @return array{response: string, response_snapshot: array<string, mixed>}
     */
    public function generate(array $context, string $focus, string $promptVersion): array;
}
