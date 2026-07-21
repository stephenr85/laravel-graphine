<?php

declare(strict_types=1);

namespace Rushing\Graphine\Contracts;

use Rushing\Graphine\Dto\NodeId;
use Rushing\Graphine\Dto\Path;

/**
 * ROLE 2 — Traverse & compute. Graph algorithms over the topology.
 *
 * MANDATORY spine (with StructureStore) — every real graph consumer exercises
 * both (ticket 02 decision 3). Cohesive sub-contract: pure read-only
 * computation. Two implementation tiers satisfy this same interface:
 *   - the package's in-memory reference driver (graphp/graph) — fits in memory.
 *   - a consumer's Python/rustworkx driver — heavy compute over a PROCESS
 *     BOUNDARY (Capability::HeavyCompute). ⚠️ ops cost UNMEASURED — gate #2;
 *     authored app-side (see examples/app-drivers/PythonComputeDriver).
 *
 * @see docs 02 — role 2 "ADOPT (in-memory library) + a deferred compute boundary"
 */
interface ComputeStore
{
    /** Shortest weighted path (Dijkstra). Role 2. */
    public function shortestPath(NodeId $from, NodeId $to): ?Path;

    /**
     * PageRank-style importance ranking.
     *
     * @return array<string,float> nodeId => score
     */
    public function rank(): array;

    /** Cycle detection. Returns cyclic Paths, empty if acyclic. Role 1/2. @return list<Path> */
    public function detectCycles(): array;
}
