<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $advisor_thread_id
 * @property int|null $user_id
 * @property string $role
 * @property string|null $advisor who spoke, in a meeting
 * @property list<string>|null $invited who was in the room, on a question in a meeting
 * @property string $body
 * @property string $status
 * @property string|null $dropped_reason
 * @property int $input_tokens
 * @property int $output_tokens
 * @property string|null $model
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['advisor_thread_id', 'user_id', 'role', 'advisor', 'invited', 'body', 'status', 'dropped_reason', 'input_tokens', 'output_tokens', 'model'])]
class AdvisorMessage extends Model
{
    public const STUDENT = 'student';

    public const ADVISOR = 'advisor';

    public const SHOWN = 'shown';

    public const DROPPED = 'dropped';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['invited' => 'array'];
    }

    /** Answers a team was shown in a quarter. Halden pays for each one. */
    public static function billable(int $teamId, int $quarterId): int
    {
        return self::query()
            ->whereHas('thread', fn ($q) => $q->where('team_id', $teamId)->where('quarter_id', $quarterId))
            ->where('role', self::ADVISOR)->where('status', self::SHOWN)->count();
    }

    /** @return BelongsTo<AdvisorThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(AdvisorThread::class, 'advisor_thread_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
