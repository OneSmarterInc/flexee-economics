<?php

namespace App\Jobs;

use App\Domain\Interpretation\InterpretiveAssistantProvider;
use App\Models\InterpretationRequest;
use App\Models\InterpretationResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class GenerateInterpretationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $interpretationRequestId,
    ) {}

    public function handle(InterpretiveAssistantProvider $provider): void
    {
        $request = InterpretationRequest::query()->findOrFail($this->interpretationRequestId);

        if (InterpretationResult::query()
            ->where('tenant_id', $request->tenant_id)
            ->where('interpretation_request_id', $request->id)
            ->exists()) {
            return;
        }

        $generated = $provider->generate($request->contextSnapshot(), $request->focus, $request->prompt_version);

        InterpretationResult::query()->create([
            'tenant_id' => $request->tenant_id,
            'interpretation_request_id' => $request->id,
            'provider' => $provider->providerName(),
            'model' => $provider->modelName(),
            'prompt_version' => $request->prompt_version,
            'context_hash' => $request->context_hash,
            'response' => $generated['response'],
            'response_snapshot' => $generated['response_snapshot'],
            'generated_at' => Carbon::now(),
        ]);
    }
}
