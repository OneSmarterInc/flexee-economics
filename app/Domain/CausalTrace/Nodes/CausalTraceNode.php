<?php

namespace App\Domain\CausalTrace\Nodes;

abstract class CausalTraceNode
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly int $teamSimulationId,
        public readonly ?int $runtimeWeekId,
        public readonly string $label,
        public readonly int $sortOrder,
        public readonly array $payload = [],
    ) {}
}
