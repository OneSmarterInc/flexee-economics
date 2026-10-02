<?php

namespace App\Domain\Economics\Week8;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week8CohortAdjustment
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public BigDecimal $refiningCrackShift,
        public array $snapshot,
    ) {}

    public static function none(): self
    {
        return new self(
            refiningCrackShift: BigDecimal::zero(),
            snapshot: [
                'status' => 'none',
                'refining_crack_shift' => '0.00',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSnapshot(): array
    {
        return array_merge($this->snapshot, [
            'refining_crack_shift' => (string) $this->refiningCrackShift->toScale(2, RoundingMode::HalfUp),
        ]);
    }
}
