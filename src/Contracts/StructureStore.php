<?php

declare(strict_types=1);

namespace Rushing\Graphine\Contracts;

use Rushing\Graphine\Dto\Edge;
use Rushing\Graphine\Dto\Node;
use Rushing\Graphine\Dto\NodeId;
use Rushing\Graphine\Enums\TraversalDirection;

/**
 * ROLE 1 — Declare structure. Persist and read node+edge topology with
 * hierarchy, weighting and (possibly cyclic) recursion.
 *
 * MANDATORY spine (with ComputeStore) — every real graph consumer exercises
 * both (ticket 02 decision 3). Cohesive sub-contract: everything here is
 * "shape the graph / read its neighbourhood". The package's reference
 * implementation is the in-memory driver; a consumer's relational driver
 * (e.g. over staudenmeir/laravel-adjacency-list recursive CTEs, or the KG's
 * rdf_triples + PHP traversal) is authored app-side — see
 * examples/app-drivers/RelationalKgDriver.
 *
 * @see docs 01 §A — "staudenmeir/laravel-adjacency-list … the recommended default"
 */
interface StructureStore
{
    public function putNode(Node $node): void;

    public function putEdge(Edge $edge): void;

    public function getNode(NodeId $id): ?Node;

    /**
     * Adjacency read — ancestors/descendants/both. Maps directly onto the
     * adjacency-list library's ancestors()/descendants() relations, or a
     * WITH RECURSIVE CTE.
     *
     * @return list<Node>
     */
    public function neighbours(
        NodeId $of,
        TraversalDirection $direction = TraversalDirection::Descendants,
        ?int $maxDepth = null,
    ): array;
}
