<?php

namespace App\Domain\Economics\Week5;

use Brick\Math\BigDecimal;

final readonly class Week5FxRate
{
    public function __construct(
        public string $pair,
        public BigDecimal $pre,
        public BigDecimal $post,
    ) {}

    /**
     * @return array<string, string>
     */
    public function snapshot(): array
    {
        return [
            'pair' => $this->pair,
            'pre' => (string) $this->pre,
            'post' => (string) $this->post,
        ];
    }
}
