<?php

namespace App\Domain\Capital;

use App\Models\DiscountRateConsequence;

final readonly class CapitalAllocationContext
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public ?DiscountRateConsequence $discountRateConsequence,
        public ?string $discountRatePercent,
        public ?string $capitalEnvelopeMusd,
        public string $status,
        public array $snapshot,
    ) {}

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }
}
