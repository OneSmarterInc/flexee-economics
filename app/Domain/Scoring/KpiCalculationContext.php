<?php

namespace App\Domain\Scoring;

final readonly class KpiCalculationContext
{
    /**
     * @param  array<string, string|int|float|null>  $availableInputs
     * @param  array<string, mixed>  $inputSnapshot
     */
    public function __construct(
        public int $tenantId,
        public int $sectionSimulationId,
        public int $sectionSimulationWeekId,
        public int $teamSimulationId,
        public int $teamId,
        public string $sourceType,
        public ?int $sourceId,
        public array $availableInputs,
        public array $inputSnapshot,
    ) {}
}
