<?php

namespace Rushing\Graphine\Contracts;

/**
 * ROLE 2 — Traverse & compute. Graph algorithms over the topology.
 *
 * MANDATORY spine (with StructureStore) — every real graph consumer exercises
 * both. Cohesive sub-contract: pure read-only
 * computation. Two implementation tiers satisfy this same interface:
 *   - the package's in-memory reference driver (graphp/graph) — fits in memory.
 *   - a consumer's Python/rustworkx driver — heavy compute over a PROCESS
 *     BOUNDARY (Capability::HeavyCompute). ⚠️ ops cost UNMEASURED;
 *     authored app-side (see examples/app-drivers/PythonComputeDriver).
 */
interface ComputeStore
{
    /** Shortest weighted path (Dijkstra). Role 2. */
    public function shortestPath(NodeIdContract $from, NodeIdContract $to): ?PathContract;

    /**
     * PageRank-style importance ranking.
     *
     * @return array<string,float> nodeId => score
     */
    public function rank(): array;

    /** Cycle detection. Returns cyclic Paths, empty if acyclic. Role 1/2. @return list<PathContract> */
    public function detectCycles(): array;
}
