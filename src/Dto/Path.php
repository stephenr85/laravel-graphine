<?php

declare(strict_types=1);

namespace Rushing\Graphine\Dto;

/**
 * Result of a traversal / shortest-path query (role 2). An ordered node walk
 * with the accumulated cost, so callers get a format-agnostic answer whether
 * the compute ran in graphp/graph, a recursive CTE, or rustworkx.
 */
final readonly class Path
{
    public function __construct(
        /** @var list<NodeId> ordered node walk, source first */
        public array $nodes,
        /** Summed edge weight along the walk. */
        public float $cost,
        /** True if a cycle was detected on this walk (role 1/2 cycle detection). */
        public bool $cyclic = false,
    ) {}

    public function length(): int
    {
        return max(0, count($this->nodes) - 1);
    }
}
