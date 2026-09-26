<?php

namespace App\Domain\Economics\Week5;

use Brick\Math\BigDecimal;

final readonly class Week5EntityFlow
{
    public function __construct(
        public string $flowId,
        public string $entity,
        public string $currency,
        public BigDecimal $annualMusdPre,
    ) {}

    /**
     * @return array<string, string>
     */
    public function snapshot(): array
    {
        return [
            'flow_id' => $this->flowId,
            'entity' => $this->entity,
            'currency' => $this->currency,
            'annual_musd_pre' => (string) $this->annualMusdPre,
        ];
    }
}
