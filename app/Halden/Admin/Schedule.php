<?php

namespace App\Halden\Admin;

use App\Models\Quarter;
use App\Models\Section;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The class's deadlines, one per week (the first quarter of each pair in a 7-week class). A deadline can change
 * while its quarter has not been run; moving one can carry every later deadline by the same amount, so a week's
 * delay pushes the whole course along. Times are Eastern on screen and UTC in the database.
 */
final class Schedule
{
    public const TZ = 'America/New_York';

    /**
     * The rows the schedule screen shows.
     *
     * @return list<array{id: int, number: number|int, label: string, week: int, status: string, deadline: string|null, editable: bool, carries: bool}>
     */
    public static function rows(Section $section): array
    {
        $rows = [];
        foreach ($section->quarters()->get() as $q) {
            $carries = $q->isPaired() && ! $q->is($q->weekStart());
            $rows[] = [
                'id' => $q->id, 'number' => $q->number, 'label' => $q->weekLabel(), 'week' => $q->week(), 'status' => $q->status,
                'deadline' => $q->deadline_at?->setTimezone(self::TZ)->format('Y-m-d\TH:i'),
                'editable' => ! $carries && in_array($q->status, [Quarter::UPCOMING, Quarter::OPEN], true),
                'carries' => $carries,
            ];
        }

        return $rows;
    }

    /**
     * Applies the deadlines the instructor set. A row whose submitted time equals its stored one is "not touched";
     * when $shiftFollowing is on, a changed row moves every later untouched row by the same amount (in Eastern time,
     * so clock times hold across daylight saving). Returns how many deadlines changed.
     *
     * @param  array<int, string>  $submitted  quarter id => 'Y-m-d\TH:i' Eastern
     */
    public static function apply(Section $section, array $submitted, bool $shiftFollowing): int
    {
        return DB::transaction(function () use ($section, $submitted, $shiftFollowing): int {
            $quarters = $section->quarters()->whereNotNull('deadline_at')->get();
            $new = [];
            foreach ($quarters as $q) {
                $new[$q->id] = $q->deadline_at->setTimezone(self::TZ);
            }
            foreach ($quarters as $i => $q) {
                if (! array_key_exists($q->id, $submitted)) {
                    continue;
                }
                $wanted = CarbonImmutable::parse($submitted[$q->id], self::TZ);
                if ($wanted->equalTo($new[$q->id])) {
                    continue;
                }
                if (! in_array($q->status, [Quarter::UPCOMING, Quarter::OPEN], true)) {
                    throw new RuntimeException("Q{$q->number} has been run, so its deadline can't change.");
                }
                // A calendar interval (days, then hours), so later deadlines keep their clock time across daylight saving.
                $delta = $new[$q->id]->diffAsCarbonInterval($wanted, false);
                $new[$q->id] = $wanted;
                if ($shiftFollowing) {
                    foreach ($quarters->slice($i + 1) as $later) {
                        $laterSubmitted = isset($submitted[$later->id]) ? CarbonImmutable::parse($submitted[$later->id], self::TZ) : null;
                        $touched = $laterSubmitted !== null && ! $laterSubmitted->equalTo($later->deadline_at->setTimezone(self::TZ));
                        if (! $touched && in_array($later->status, [Quarter::UPCOMING, Quarter::OPEN], true)) {
                            $new[$later->id] = $new[$later->id]->add($delta);
                            // So the later row's own submitted (old) value no longer counts as a change back.
                            $submitted[$later->id] = $new[$later->id]->format('Y-m-d\TH:i');
                        }
                    }
                }
            }
            $changed = 0;
            $previous = null;
            foreach ($quarters as $q) {
                $at = $new[$q->id];
                if ($previous !== null && $at->lessThanOrEqualTo($previous)) {
                    throw new RuntimeException("Q{$q->number}'s deadline must come after the one before it.");
                }
                $previous = $at;
                if (! $at->utc()->equalTo($q->deadline_at)) {
                    $q->update(['deadline_at' => $at->utc()]);
                    $changed++;
                }
            }

            return $changed;
        });
    }
}
