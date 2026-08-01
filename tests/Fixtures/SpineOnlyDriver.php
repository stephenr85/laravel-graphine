<?php

namespace Rushing\Graphine\Tests\Fixtures;

use Rushing\Graphine\Contracts\ComputeStore;
use Rushing\Graphine\Contracts\EdgeContract;
use Rushing\Graphine\Contracts\NodeContract;
use Rushing\Graphine\Contracts\NodeIdContract;
use Rushing\Graphine\Contracts\PathContract;
use Rushing\Graphine\Contracts\StructureStore;
use Rushing\Graphine\Drivers\AbstractDriver;
use Rushing\Graphine\Drivers\InMemoryDriver;
use Rushing\Graphine\Enums\Capability;
use Rushing\Graphine\Enums\TraversalDirection;

/**
 * A MANDATORY-SPINE-ONLY driver: StructureStore + ComputeStore and nothing else.
 *
 * It is DELIBERATELY not `GovernedStore` and not `QueryableStore` — the à-la-carte
 * opt-in is by TYPE, so this driver simply omits those interfaces. It advertises
 * exactly `Declare` + `Compute` and `speaks()` no wire format. The conformance kit
 * runs the spine on it and SKIPS the optional roles, proving the type-level opt-in.
 *
 * Behaviour is delegated to an in-memory spine so the fixture stays about the
 * SHAPE (which roles it exposes), not a re-implemented algorithm.
 */
class SpineOnlyDriver extends AbstractDriver implements ComputeStore, StructureStore
{
    private InMemoryDriver $spine;

    /** @var list<Capability> */
    protected array $capabilities = [
        Capability::Declare,
        Capability::Compute,
        // No Governance, no QueryAtScale — this driver reaches neither.
    ];

    public function __construct()
    {
        $this->spine = new InMemoryDriver;
    }

    public function name(): string
    {
        return 'spine-only';
    }

    public function putNode(NodeContract $node): void
    {
        $this->spine->putNode($node);
    }

    public function putEdge(EdgeContract $edge): void
    {
        $this->spine->putEdge($edge);
    }

    public function getNode(NodeIdContract $id): ?NodeContract
    {
        return $this->spine->getNode($id);
    }

    /** @return list<NodeContract> */
    public function neighbours(
        NodeIdContract $of,
        TraversalDirection $direction = TraversalDirection::Descendants,
        ?int $maxDepth = null,
    ): array {
        return $this->spine->neighbours($of, $direction, $maxDepth);
    }

    public function shortestPath(NodeIdContract $from, NodeIdContract $to): ?PathContract
    {
        return $this->spine->shortestPath($from, $to);
    }

    /** @return array<string,float> */
    public function rank(): array
    {
        return $this->spine->rank();
    }

    /** @return list<PathContract> */
    public function detectCycles(): array
    {
        return $this->spine->detectCycles();
    }
}
