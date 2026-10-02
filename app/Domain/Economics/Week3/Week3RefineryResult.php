<?php

namespace App\Domain\Economics\Week3;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week3RefineryResult
{
    public function __construct(
        public string $key,
        public string $name,
        public BigDecimal $crack,
        public BigDecimal $complexity,
        public BigDecimal $variableCost,
        public BigDecimal $fixedCost,
        public BigDecimal $avoidableFixed,
        public BigDecimal $haldenShare,
        public BigDecimal $contribution,
        public BigDecimal $net,
        public BigDecimal $shutdownCrack,
        public BigDecimal $idleDelta,
    ) {}

    /**
     * @return array<string, string>
     */
    public function snapshot(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'crack' => $this->decimal($this->crack),
            'complexity' => $this->decimal($this->complexity),
            'variable_cost' => $this->decimal($this->variableCost),
            'fixed_cost' => $this->decimal($this->fixedCost),
            'avoidable_fixed' => $this->decimal($this->avoidableFixed),
            'halden_share' => $this->decimal($this->haldenShare),
            'contribution' => $this->decimal($this->contribution),
            'net' => $this->decimal($this->net),
            'shutdown_crack' => $this->decimal($this->shutdownCrack),
            'idle_delta' => $this->decimal($this->idleDelta),
        ];
    }

    private function decimal(BigDecimal $value): string
    {
        return (string) $value->toScale(6, RoundingMode::HalfUp);
    }
}
