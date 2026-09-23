<?php

namespace App\Domain\Capital\Week6;

final readonly class Week6CapitalEconomicsResult
{
    /**
     * @param  array<string, mixed>  $inputSnapshot
     * @param  array<string, mixed>  $outputSnapshot
     */
    public function __construct(
        public string $status,
        public array $inputSnapshot,
        public array $outputSnapshot,
        public ?string $portfolioNpvMusd,
        public ?string $portfolioIrrPercent,
        public ?string $capitalRequiredMusd,
        public ?bool $capitalEnvelopeFeasible,
        public ?string $unavailableReason,
    ) {}
}
