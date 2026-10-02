<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['ulid', 'tenant_id', 'section_simulation_id', 'section_simulation_week_id', 'simulation_week_id', 'execution_version', 'status', 'steps', 'outputs', 'failure_message', 'started_by_user_id', 'started_at', 'completed_at', 'failed_at'])]
class WeekExecutionRecord extends Model
{
    use BelongsToTenant, HasUlidRouteKey;

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected static function booted(): void
    {
        static::saving(function (WeekExecutionRecord $record): void {
            $runtimeWeek = SectionSimulationWeek::query()->findOrFail($record->section_simulation_week_id);

            if ($runtimeWeek->tenant_id !== $record->tenant_id || $runtimeWeek->section_simulation_id !== $record->section_simulation_id || $runtimeWeek->simulation_week_id !== $record->simulation_week_id) {
                throw new InvalidArgumentException('Week execution record runtime week context is inconsistent.');
            }

            if ($record->started_by_user_id !== null) {
                $actor = User::query()->findOrFail($record->started_by_user_id);

                if ($actor->tenant_id !== $record->tenant_id) {
                    throw new InvalidArgumentException('Week execution actor tenant is inconsistent.');
                }
            }
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException('Week execution records are immutable audit records.');
        });
    }

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'outputs' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SectionSimulationWeek, $this>
     */
    public function runtimeWeek(): BelongsTo
    {
        return $this->belongsTo(SectionSimulationWeek::class, 'section_simulation_week_id');
    }
}
