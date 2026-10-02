<?php

namespace App\Domain\Economics\Week3;

use Brick\Math\BigDecimal;

final readonly class Week3EconomicResult
{
    /**
     * @param  array<string, Week3RefineryResult>  $refineryResults
     * @param  array<string, mixed>  $windowResults
     * @param  array<string, mixed>  $inputSnapshot
     * @param  array<string, mixed>  $outputSnapshot
     */
    public function __construct(
        public array $refineryResults,
        public array $windowResults,
        public BigDecimal $rotterdamIdleDelta,
        public array $inputSnapshot,
        public array $outputSnapshot,
        public string $packageVersion,
        public string $engineIdentifier,
        public string $engineVersion,
    ) {}
}
