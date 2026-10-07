<?php

namespace App\Domain\Economics\Week8;

use Brick\Math\BigDecimal;

final readonly class Week8InterimEbitdaBridgeResult
{
    /**
     * @param  array<string, mixed>  $provenance
     */
    public function __construct(
        public BigDecimal $deltaWti,
        public BigDecimal $ebitdaEffectMusd,
        public array $provenance,
    ) {}
}
