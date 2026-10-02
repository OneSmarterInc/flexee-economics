<?php

namespace App\Domain\Capital\Week6;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week6ProjectCashFlows
{
    /**
     * @param  list<BigDecimal>  $flows
     */
    public function __construct(
        public string $key,
        public array $flows,
    ) {}

    public function outlayMusd(): BigDecimal
    {
        return $this->flows[0]->abs();
    }

    /**
     * @return list<string>
     */
    public function snapshot(): array
    {
        return array_map(
            fn (BigDecimal $flow): string => (string) $flow->toScale(3, RoundingMode::Unnecessary),
            $this->flows,
        );
    }
}
