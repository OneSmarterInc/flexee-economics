<?php

namespace App\Domain\CausalTrace;

use App\Domain\CausalTrace\Nodes\CausalTraceNode;

final class CausalTrace
{
    /**
     * @param  list<CausalTraceNode>  $nodes
     */
    public function __construct(
        public readonly string $direction,
        public readonly string $rootType,
        public readonly int $rootId,
        public readonly array $nodes,
    ) {}

    /**
     * @return list<string>
     */
    public function nodeTypes(): array
    {
        return array_map(
            fn (CausalTraceNode $node): string => $node->type,
            $this->nodes,
        );
    }
}
