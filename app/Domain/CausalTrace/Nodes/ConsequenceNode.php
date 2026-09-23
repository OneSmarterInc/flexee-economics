<?php

namespace App\Domain\CausalTrace\Nodes;

final class ConsequenceNode extends CausalTraceNode
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(int $id, int $teamSimulationId, ?int $runtimeWeekId, string $label, int $sortOrder, array $payload = [])
    {
        parent::__construct('consequence', $id, $teamSimulationId, $runtimeWeekId, $label, $sortOrder, $payload);
    }
}
