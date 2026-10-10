<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One week of the course, which is one quarter at Halden.
 *
 * @property int $id
 * @property int $section_id
 * @property int $number
 * @property string $company_quarter
 * @property string $status
 * @property string|null $event_outcome
 * @property string|null $world
 * @property CarbonImmutable|null $deadline_at
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $seats_rotated_at
 * @property-read Section $section
 */
#[Fillable(['section_id', 'number', 'company_quarter', 'status', 'event_outcome', 'world', 'deadline_at', 'opened_at', 'closed_at', 'published_at', 'seats_rotated_at'])]
class Quarter extends Model
{
    public const UPCOMING = 'upcoming';

    public const OPEN = 'open';

    public const CLOSED = 'closed';

    public const PUBLISHED = 'published';

    protected function casts(): array
    {
        return [
            'deadline_at' => 'datetime',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'published_at' => 'datetime',
            'seats_rotated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return HasMany<TeamQuarter, $this> */
    public function teamQuarters(): HasMany
    {
        return $this->hasMany(TeamQuarter::class);
    }

    /** "Q4 2027" from "2027Q4". */
    public function label(): string
    {
        return substr($this->company_quarter, 4).' '.substr($this->company_quarter, 0, 4);
    }

    /** Company quarters in the course, whatever its length: Q1 2027 to Q2 2030. */
    public const COMPANY_QUARTERS = 14;

    /**
     * A 7-week course plays two company quarters a week with the same settings (decision D1): the team works on the
     * first of the pair, and the second runs on its own at the close. A 14-week course plays one quarter a week.
     */
    public function isPaired(): bool
    {
        return (int) $this->section->weeks < self::COMPANY_QUARTERS;
    }

    /** The week of the course this quarter is played in (1-based). */
    public function week(): int
    {
        return $this->isPaired() ? intdiv($this->number + 1, 2) : $this->number;
    }

    /** Whether this is the quarter the team works on in its week (always, in a 14-week course). */
    public function isFirstOfWeek(): bool
    {
        return ! $this->isPaired() || $this->number % 2 === 1;
    }

    /** The first quarter of this quarter's week: the one the team works on. */
    public function weekStart(): self
    {
        return $this->isFirstOfWeek() ? $this : ($this->previous() ?? $this);
    }

    /** The other quarter of the pair in a 7-week course, or null. */
    public function partner(): ?self
    {
        if (! $this->isPaired()) {
            return null;
        }
        $n = $this->number % 2 === 1 ? $this->number + 1 : $this->number - 1;

        return self::query()->where('section_id', $this->section_id)->where('number', $n)->first();
    }

    /** The last company quarter the week's settings run in: this one, or its partner in a 7-week course. */
    public function playEnd(): int
    {
        return $this->isPaired() && $this->number % 2 === 1 ? $this->number + 1 : $this->number;
    }

    /** "Q4 2027", or "Q3 and Q4 2027" for the week in a 7-week course. */
    public function weekLabel(): string
    {
        if (! $this->isPaired()) {
            return $this->label();
        }
        $first = $this->weekStart();
        $second = self::companyQuarterFor($first->number + 1);

        return substr($first->company_quarter, 4).' and '.substr($second, 4).' '.substr($second, 0, 4);
    }

    /** The quarter in which the team swaps seats when it opens: after the midterm (Quarter 8 of 14; week 5 of 7). */
    public function isRotationQuarter(): bool
    {
        return $this->isFirstOfWeek() && $this->week() === (int) ceil($this->section->weeks / 2) + 1;
    }

    /** The midterm and the end: the quarters whose results compare every measure with the whole class (D4). */
    public function isComparisonQuarter(): bool
    {
        return $this->number === (int) (ceil($this->section->weeks / 2) * self::COMPANY_QUARTERS / $this->section->weeks)
            || $this->number === self::COMPANY_QUARTERS;
    }

    /** The last quarter of the course: the board meeting. No operating decisions; the board defense instead. */
    public function isBoardQuarter(): bool
    {
        return $this->number === self::COMPANY_QUARTERS;
    }

    /** The board quarter this one carries, if any: itself, or its partner in the last week of a 7-week course. */
    public function boardQuarter(): ?self
    {
        if ($this->isBoardQuarter()) {
            return $this;
        }
        $partner = $this->partner();

        return $partner !== null && $partner->isBoardQuarter() ? $partner : null;
    }

    public function previous(): ?self
    {
        return self::query()->where('section_id', $this->section_id)->where('number', $this->number - 1)->first();
    }

    /** Company quarter for course quarter n in a 14-week section: Q1 2027 onwards. */
    public static function companyQuarterFor(int $number): string
    {
        $index = $number - 1;

        return (2027 + intdiv($index, 4)).'Q'.(($index % 4) + 1);
    }
}
