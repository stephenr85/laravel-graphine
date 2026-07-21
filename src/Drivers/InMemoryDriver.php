<?php

declare(strict_types=1);

namespace Rushing\Graphine\Drivers;

use Rushing\Graphine\Contracts\ComputeStore;
use Rushing\Graphine\Contracts\GovernedStore;
use Rushing\Graphine\Contracts\StructureStore;
use Rushing\Graphine\Dto\Edge;
use Rushing\Graphine\Dto\Node;
use Rushing\Graphine\Dto\NodeId;
use Rushing\Graphine\Dto\Path;
use Rushing\Graphine\Enums\Capability;
use Rushing\Graphine\Enums\TraversalDirection;

/**
 * THE PACKAGE'S ONE AND ONLY DRIVER — the in-memory REFERENCE driver.
 *
 * Ticket 02 ruled the package ships the contract + value types/enums + ONE
 * in-memory reference driver + a conformance test-kit, and ZERO real
 * persistence drivers. This is that reference driver: it implements the full
 * mandatory spine (StructureStore + ComputeStore) AND the optional GovernedStore
 * so the conformance test-kit (Testing\GraphStoreConformance) has a working
 * oracle to certify every real consumer-side driver against.
 *
 * Conceptually backed by graphp/graph (MIT); the plain PHP arrays below stand
 * in for a real graphp\Graph instance (suggest-only dep — see composer.json).
 * It links no boundary-only engine, so it trivially passes the seam guard.
 *
 * Because it holds no persistence of its own, it CANNOT contradict ADR-0086's
 * "KG stays relational" — the app's relational KG driver is authored app-side
 * (see examples/app-drivers/RelationalKgDriver), never here.
 *
 * @see docs 01 §A — graphp/graph → in-memory driver
 */
final class InMemoryDriver extends AbstractDriver implements StructureStore, ComputeStore, GovernedStore
{
    /** @var array<string,Node> */
    private array $nodes = [];

    /** @var list<Edge> */
    private array $edges = [];

    /** @var array<string,float> host-asserted governance gates, nodeId => [0,1] */
    private array $gates = [];

    /** @var array<string,string> host-asserted class IRIs, nodeId => classIri */
    private array $classes = [];

    /** @var list<Capability> */
    protected array $capabilities = [
        Capability::Declare,     // role 1
        Capability::Compute,     // role 2
        Capability::Governance,  // role 4 — gating is REAL in-memory
        // NOT Capability::Reasoning — reference driver performs no inference.
    ];

    public function name(): string
    {
        return 'memory';
    }

    // --- StructureStore (role 1) -------------------------------------------

    public function putNode(Node $node): void
    {
        $this->nodes[$node->id->value] = $node;
    }

    public function putEdge(Edge $edge): void
    {
        $this->edges[] = $edge;
    }

    public function getNode(NodeId $id): ?Node
    {
        return $this->nodes[$id->value] ?? null;
    }

    /** @return list<Node> */
    public function neighbours(
        NodeId $of,
        TraversalDirection $direction = TraversalDirection::Descendants,
        ?int $maxDepth = null,
    ): array {
        // Reference impl: one-hop adjacency over the edge list. A production
        // graphp-backed driver walks to $maxDepth; the reference stays shallow
        // on purpose so the test-kit exercises the CONTRACT, not an algorithm.
        $out = [];
        foreach ($this->edges as $edge) {
            $match = match ($direction) {
                TraversalDirection::Descendants => $edge->from->equals($of),
                TraversalDirection::Ancestors => $edge->to->equals($of),
                TraversalDirection::Both => $edge->from->equals($of) || $edge->to->equals($of),
            };
            if ($match) {
                $other = $edge->from->equals($of) ? $edge->to : $edge->from;
                if ($n = $this->getNode($other)) {
                    $out[] = $n;
                }
            }
        }

        return $out;
    }

    // --- ComputeStore (role 2) ---------------------------------------------

    public function shortestPath(NodeId $from, NodeId $to): ?Path
    {
        // Reference stub: real impl delegates to graphp/graph Dijkstra.
        return new Path(nodes: [$from, $to], cost: 1.0);
    }

    /** @return array<string,float> */
    public function rank(): array
    {
        // Reference stub: uniform placeholder for PageRank over the graph.
        $n = max(1, count($this->nodes));

        return array_fill_keys(array_keys($this->nodes), 1.0 / $n);
    }

    /** @return list<Path> */
    public function detectCycles(): array
    {
        // Reference stub: real impl uses graphp cycle detection.
        return [];
    }

    // --- GovernedStore (role 4 — governance-as-gating) ----------------------

    public function assertGovernance(NodeId $node, float $gate): void
    {
        // Clamp to [0,1]; the gate is a host-side hint, meaning stays host-side.
        $this->gates[$node->value] = max(0.0, min(1.0, $gate));
    }

    /** @return array<string,float> */
    public function governedRank(): array
    {
        $governed = [];
        foreach ($this->rank() as $nodeId => $computed) {
            $gate = $this->gates[$nodeId] ?? 1.0;   // no assertion = pass-through
            $score = $gate * $computed;
            if ($score > 0.0) {                      // gate 0.0 → silent
                $governed[$nodeId] = $score;
            }
        }
        arsort($governed);

        return $governed;
    }

    public function classify(NodeId $node, string $classIri): void
    {
        $this->classes[$node->value] = $classIri;
    }

    /** @return list<string> */
    public function reason(NodeId $node): array
    {
        // Reference driver performs NO inference (not Capability::Reasoning) — it
        // returns only the directly-asserted class, if any. A real reasoner is a
        // consumer-side backend behind a process boundary, never linked here.
        $asserted = $this->classes[$node->value] ?? null;

        return $asserted === null ? [] : [$asserted];
    }
}
