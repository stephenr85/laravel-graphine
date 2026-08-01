<?php

namespace Rushing\Graphine\Algorithms;

/**
 * Result of a topological sort (see {@see TopologicalSort::kahn()}).
 *
 * `$sorted` is the DAG portion in dependency order — for a successor edge
 * `u → v`, `u` appears before `v` (sources first). `$cyclic` holds the node
 * ids that could NOT be ordered because they sit in, or downstream of, a cycle;
 * those ids are EXCLUDED from `$sorted`. An empty `$cyclic` therefore means the
 * whole input was a DAG.
 */
class TopologicalOrder
{
    public function __construct(
        /** @var list<string> Topologically ordered node ids, sources first. */
        public array $sorted,
        /** @var list<string> Node ids trapped in/behind a cycle; empty ⇒ DAG. */
        public array $cyclic = [],
    ) {}

    public function hasCycle(): bool
    {
        return $this->cyclic !== [];
    }
}
