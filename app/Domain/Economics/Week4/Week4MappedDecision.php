<?php

namespace App\Domain\Economics\Week4;

use Brick\Math\BigDecimal;

final readonly class Week4MappedDecision
{
    /**
     * @param  array<string, mixed>  $inputSnapshot
     * @param  array<string, mixed>  $submissionSnapshot
     */
    public function __construct(
        public Week4EconomicInputs $inputs,
        public BigDecimal $transferPrice,
        public array $inputSnapshot,
        public array $submissionSnapshot,
    ) {}
}
