<?php

namespace Rushing\Graphine\Contracts;

use Rushing\Graphine\Enums\TraversalDirection;

/**
 * ROLE 1 — Declare structure. Persist and read node+edge topology with
 * hierarchy, weighting and (possibly cyclic) recursion.
 *
 * MANDATORY spine (with ComputeStore) — every real graph consumer exercises
 * both. Cohesive sub-contract: everything here is
 * "shape the graph / read its neighbourhood". The package's reference
 * implementation is the in-memory driver; a consumer's relational driver
 * (e.g. over staudenmeir/laravel-adjacency-list recursive CTEs, or the KG's
 * rdf_triples + PHP traversal) is authored app-side — see
 * examples/app-drivers/RelationalKgDriver.
 */
interface StructureStore
{
    public function putNode(NodeContract $node): void;

    public function putEdge(EdgeContract $edge): void;

    public function getNode(NodeIdContract $id): ?NodeContract;

    /**
     * Adjacency read — ancestors/descendants/both. Maps directly onto the
     * adjacency-list library's ancestors()/descendants() relations, or a
     * WITH RECURSIVE CTE.
     *
     * @return list<NodeContract>
     */
    public function neighbours(
        NodeIdContract $of,
        TraversalDirection $direction = TraversalDirection::Descendants,
        ?int $maxDepth = null,
    ): array;
}
