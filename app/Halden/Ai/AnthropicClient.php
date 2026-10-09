<?php

namespace App\Halden\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class AnthropicClient implements LlmClient
{
    public function __construct(private readonly string $apiKey, private readonly string $model) {}

    public function complete(string $system, array $messages, int $maxTokens): LlmReply
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout(60)->retry(2, 1500, throw: false)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => $messages,
        ]);
        if (! $response->successful()) {
            throw new RuntimeException('The advisor service did not answer (HTTP '.$response->status().').');
        }
        $text = '';
        foreach ((array) $response->json('content', []) as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text') {
                $text .= (string) $block['text'];
            }
        }

        return new LlmReply(
            trim($text),
            (int) $response->json('usage.input_tokens', 0),
            (int) $response->json('usage.output_tokens', 0),
            (string) $response->json('model', $this->model),
        );
    }
}
