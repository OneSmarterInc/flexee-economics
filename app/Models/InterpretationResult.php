<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'interpretation_request_id', 'provider', 'model', 'prompt_version', 'context_hash', 'response', 'response_snapshot', 'generated_at'])]
class InterpretationResult extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (InterpretationResult $result): void {
            $request = InterpretationRequest::query()->findOrFail($result->interpretation_request_id);

            if ($request->tenant_id !== $result->tenant_id) {
                throw new InvalidArgumentException('Interpretation result tenant must match request.');
            }

            if ($request->context_hash !== $result->context_hash || $request->prompt_version !== $result->prompt_version) {
                throw new InvalidArgumentException('Interpretation result must preserve request context and prompt versions.');
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Interpretation results are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Interpretation results are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'response_snapshot' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<InterpretationRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(InterpretationRequest::class, 'interpretation_request_id');
    }
}
