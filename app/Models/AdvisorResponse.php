<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'advisor_consultation_session_id', 'advisor_id', 'content_version', 'response', 'response_snapshot', 'responded_at'])]
class AdvisorResponse extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    protected static function booted(): void
    {
        static::saving(function (AdvisorResponse $response): void {
            $session = AdvisorConsultationSession::query()->findOrFail($response->advisor_consultation_session_id);

            if ($session->tenant_id !== $response->tenant_id || $session->advisor_id !== $response->advisor_id) {
                throw new InvalidArgumentException('Advisor response context must match consultation session.');
            }
        });

        static::updating(function (): void {
            throw new InvalidArgumentException('Advisor responses are immutable once created.');
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Advisor responses are immutable once created.');
        });
    }

    protected function casts(): array
    {
        return [
            'response_snapshot' => 'array',
            'responded_at' => 'datetime',
        ];
    }

    public function respondedAtIso(): ?string
    {
        $respondedAt = $this->getAttribute('responded_at');

        if ($respondedAt === null) {
            return null;
        }

        if ($respondedAt instanceof CarbonInterface) {
            return $respondedAt->toISOString();
        }

        return Carbon::parse((string) $respondedAt)->toISOString();
    }

    /**
     * @return BelongsTo<AdvisorConsultationSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AdvisorConsultationSession::class, 'advisor_consultation_session_id');
    }

    /**
     * @return BelongsTo<Advisor, $this>
     */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Advisor::class);
    }
}
