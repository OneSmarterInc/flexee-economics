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
 * @property CarbonImmutable|null $deadline_at
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $published_at
 * @property-read Section $section
 */
#[Fillable(['section_id', 'number', 'company_quarter', 'status', 'deadline_at', 'opened_at', 'closed_at', 'published_at'])]
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
