<?php

namespace App\Domain\CausalTrace\Nodes;

final class KpiNode extends CausalTraceNode
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(int $id, int $teamSimulationId, ?int $runtimeWeekId, string $label, int $sortOrder, array $payload = [])
    {
        parent::__construct('kpi', $id, $teamSimulationId, $runtimeWeekId, $label, $sortOrder, $payload);
    }
}
