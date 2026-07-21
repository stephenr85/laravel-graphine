<?php

declare(strict_types=1);

namespace Rushing\Graphine\Drivers;

use Rushing\Graphine\Contracts\ComputeStore;
use Rushing\Graphine\Contracts\GraphSource;
use Rushing\Graphine\Contracts\StructureStore;
use Rushing\Graphine\Dto\Edge;
use Rushing\Graphine\Dto\Node;
use Rushing\Graphine\Dto\NodeId;
use Rushing\Graphine\Dto\Path;
use Rushing\Graphine\Enums\Capability;
use Rushing\Graphine\Enums\TraversalDirection;

/**
 * THE GENERIC RELATIONAL (SNAPSHOT) DRIVER (ADR-0102).
 *
 * The storage-agnostic driver every relational consumer rides: it hydrates a
 * {@see GraphSource} into the in-memory spine ONCE and delegates every read and
 * compute to that snapshot. It is generic precisely because it is indifferent to
 * the source SHAPE — an adjacency list and a triple store ride the same driver,
 * differing only in the source they are handed.
 *
 * Before ADR-0102 this class did not exist and each consumer (KG, Circuits,
 * numero) re-copied exactly this spine-delegation body app-side; ADR-0102 lifts
 * it into the package (taking `illuminate/database` for the source family) so the
 * copy-paste — including across a second repo — stops.
 *
 * Mandatory spine only: StructureStore (role 1) + ComputeStore (role 2). It is
 * deliberately NOT GovernedStore and NOT QueryableStore — a source that governs
 * selects {@see GovernedRelationalDriver} via {@see RelationalDriverFactory}, so
 * capability stays honest by TYPE (ADR-0100 §3 / build ticket 04), never relaxed
 * to a runtime flag.
 *
 * HYDRATION IS LAZY + SNAPSHOT-CACHED. The spine is built on first read from the
 * source and reused (the "hydrate once" model — ADR-0102 decision 2 / ADR-0086
 * bounded snapshot). A subclass whose writes go to STORAGE (e.g. KG's
 * rdf_triples) calls {@see invalidateSnapshot()} after a write so the next read
 * re-hydrates fresh; a pure read-only consumer never invalidates and pays the
 * hydration cost once.
 */
class RelationalDriver extends AbstractDriver implements ComputeStore, StructureStore
{
    /** The mandatory spine — the in-memory reference driver holds the snapshot. */
    protected InMemoryDriver $spine;

    private bool $hydrated = false;

    /** @var list<Capability> */
    protected array $capabilities = [
        Capability::Declare,   // role 1
        Capability::Compute,   // role 2
        // NO Governance / QueryAtScale — the governed member adds role 4 by type.
    ];

    public function __construct(
        protected readonly GraphSource $source,
        private readonly string $driverName = 'relational',
    ) {
        $this->spine = new InMemoryDriver;
    }

    public function name(): string
    {
        return $this->driverName;
    }

    // --- StructureStore (role 1) — read from the hydrated snapshot -----------

    public function putNode(Node $node): void
    {
        // Declare writes land in the in-memory snapshot only (the conformance kit
        // seeds through here). A consumer that persists overrides this.
        $this->spine()->putNode($node);
    }

    public function putEdge(Edge $edge): void
    {
        $this->spine()->putEdge($edge);
    }

    public function getNode(NodeId $id): ?Node
    {
        // Snapshot-uniform: getNode is answered from the hydrated spine like every
        // other read (ADR-0102 decision 2), never a bespoke live lookup.
        return $this->spine()->getNode($id);
    }

    /** @return list<Node> */
    public function neighbours(
        NodeId $of,
        TraversalDirection $direction = TraversalDirection::Descendants,
        ?int $maxDepth = null,
    ): array {
        return $this->spine()->neighbours($of, $direction, $maxDepth);
    }

    // --- ComputeStore (role 2) — delegate to the snapshot --------------------

    public function shortestPath(NodeId $from, NodeId $to): ?Path
    {
        return $this->spine()->shortestPath($from, $to);
    }

    /** @return array<string,float> */
    public function rank(): array
    {
        return $this->spine()->rank();
    }

    /** @return list<Path> */
    public function detectCycles(): array
    {
        return $this->spine()->detectCycles();
    }

    // --- Snapshot lifecycle ---------------------------------------------------

    /**
     * The hydrated spine, built once from the source on first access. Every read
     * routes through here so hydration is transparent to callers.
     */
    protected function spine(): InMemoryDriver
    {
        if (! $this->hydrated) {
            $this->hydrated = true;      // set first — hydrate() may read the spine
            $this->hydrate();
        }

        return $this->spine;
    }

    /**
     * Pull nodes then edges from the source into the spine. The governed member
     * overrides this to also load gates.
     */
    protected function hydrate(): void
    {
        foreach ($this->source->nodes() as $node) {
            $this->spine->putNode($node);
        }
        foreach ($this->source->edges() as $edge) {
            $this->spine->putEdge($edge);
        }
    }

    /**
     * Drop the cached snapshot so the next read re-hydrates from the source. A
     * consumer whose writes hit STORAGE (not the spine) calls this after a write
     * so reads reflect it — preserving the pre-ADR-0102 "fresh snapshot per read"
     * correctness while a read-only consumer still hydrates only once.
     */
    protected function invalidateSnapshot(): void
    {
        $this->spine = new InMemoryDriver;
        $this->hydrated = false;
    }
}
