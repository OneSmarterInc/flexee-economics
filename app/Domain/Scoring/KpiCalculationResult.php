<?php

namespace App\Domain\Scoring;

use App\Enums\KpiSnapshotStatus;
use App\Models\KpiDefinition;
use Brick\Math\BigDecimal;

final readonly class KpiCalculationResult
{
    /**
     * @param  array<string, mixed>  $inputSnapshot
     */
    public function __construct(
        public KpiDefinition $definition,
        public KpiSnapshotStatus $status,
        public ?BigDecimal $value,
        public ?string $unit,
        public int $precision,
        public string $calculationVersion,
        public array $inputSnapshot,
        public ?string $unavailableReason = null,
    ) {}
}
