<?php

declare(strict_types=1);

namespace Rushing\Graphine\Tests\Fixtures;

use Rushing\Graphine\Contracts\ComputeStore;
use Rushing\Graphine\Contracts\StructureStore;
use Rushing\Graphine\Drivers\AbstractDriver;
use Rushing\Graphine\Drivers\InMemoryDriver;
use Rushing\Graphine\Dto\Edge;
use Rushing\Graphine\Dto\Node;
use Rushing\Graphine\Dto\NodeId;
use Rushing\Graphine\Dto\Path;
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
final class SpineOnlyDriver extends AbstractDriver implements ComputeStore, StructureStore
{
    private readonly InMemoryDriver $spine;

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

    public function putNode(Node $node): void
    {
        $this->spine->putNode($node);
    }

    public function putEdge(Edge $edge): void
    {
        $this->spine->putEdge($edge);
    }

    public function getNode(NodeId $id): ?Node
    {
        return $this->spine->getNode($id);
    }

    /** @return list<Node> */
    public function neighbours(
        NodeId $of,
        TraversalDirection $direction = TraversalDirection::Descendants,
        ?int $maxDepth = null,
    ): array {
        return $this->spine->neighbours($of, $direction, $maxDepth);
    }

    public function shortestPath(NodeId $from, NodeId $to): ?Path
    {
        return $this->spine->shortestPath($from, $to);
    }

    /** @return array<string,float> */
    public function rank(): array
    {
        return $this->spine->rank();
    }

    /** @return list<Path> */
    public function detectCycles(): array
    {
        return $this->spine->detectCycles();
    }
}
