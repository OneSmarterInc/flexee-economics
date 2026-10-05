<?php

namespace App\Domain\Scoring;

final readonly class KpiFinancialStateResult
{
    /**
     * @param  array<string, string>  $state
     * @param  array<string, string>  $inputs
     * @param  list<array<string, mixed>>  $appliedRules
     * @param  array<string, mixed>  $provenance
     */
    public function __construct(
        public array $state,
        public array $inputs,
        public array $appliedRules,
        public array $provenance,
    ) {}
}
