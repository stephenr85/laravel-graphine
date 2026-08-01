<?php

namespace Rushing\Graphine\Dto;

use Rushing\Graphine\Contracts\PathContract;

/**
 * Result of a traversal / shortest-path query (role 2). An ordered node walk
 * with the accumulated cost, so callers get a format-agnostic answer whether
 * the compute ran in graphp/graph, a recursive CTE, or rustworkx. The shipped
 * implementation of {@see PathContract}.
 */
class Path implements PathContract
{
    public function __construct(
        /** @var list<NodeId> ordered node walk, source first */
        public array $nodes,
        /** Summed edge weight along the walk. */
        public float $cost,
        /** True if a cycle was detected on this walk (role 1/2 cycle detection). */
        public bool $cyclic = false,
    ) {}

    public function nodes(): array
    {
        return $this->nodes;
    }

    public function cost(): float
    {
        return $this->cost;
    }

    public function cyclic(): bool
    {
        return $this->cyclic;
    }

    public function length(): int
    {
        return max(0, count($this->nodes) - 1);
    }
}
