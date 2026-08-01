<?php

namespace Rushing\Graphine\Drivers;

use Rushing\Graphine\Contracts\ComputeStore;
use Rushing\Graphine\Contracts\EdgeContract;
use Rushing\Graphine\Contracts\EnumerableStore;
use Rushing\Graphine\Contracts\GraphSource;
use Rushing\Graphine\Contracts\NodeContract;
use Rushing\Graphine\Contracts\NodeIdContract;
use Rushing\Graphine\Contracts\PathContract;
use Rushing\Graphine\Contracts\StructureStore;
use Rushing\Graphine\Enums\Capability;
use Rushing\Graphine\Enums\TraversalDirection;

/**
 * THE GENERIC RELATIONAL (SNAPSHOT) DRIVER.
 *
 * The storage-agnostic driver every relational consumer rides: it hydrates a
 * {@see GraphSource} into the in-memory spine ONCE and delegates every read and
 * compute to that snapshot. It is generic precisely because it is indifferent to
 * the source SHAPE — an adjacency list and a triple store ride the same driver,
 * differing only in the source they are handed.
 *
 * Each relational consumer used to re-copy exactly this spine-delegation body
 * app-side; the package now lifts it here (taking `illuminate/database` for the
 * source family) so that copy-paste stops.
 *
 * Mandatory spine only: StructureStore (role 1) + ComputeStore (role 2). It is
 * deliberately NOT GovernedStore and NOT QueryableStore — a source that governs
 * selects {@see GovernedRelationalDriver} via {@see RelationalDriverFactory}, so
 * capability stays honest by TYPE, never relaxed
 * to a runtime flag.
 *
 * HYDRATION IS LAZY + SNAPSHOT-CACHED. The spine is built on first read from the
 * source and reused (the "hydrate once" model — a bounded snapshot). A subclass
 * whose writes go to STORAGE (e.g. KG's
 * rdf_triples) calls {@see invalidateSnapshot()} after a write so the next read
 * re-hydrates fresh; a pure read-only consumer never invalidates and pays the
 * hydration cost once.
 */
class RelationalDriver extends AbstractDriver implements ComputeStore, EnumerableStore, StructureStore
{
    /** The mandatory spine — the in-memory reference driver holds the snapshot. */
    protected InMemoryDriver $spine;

    private bool $hydrated = false;

    /** @var list<Capability> */
    protected array $capabilities = [
        Capability::Declare,   // role 1
        Capability::Compute,   // role 2
        Capability::Enumerate, // role 5 — the bounded snapshot dumps free from the spine
        // NO Governance / QueryAtScale — the governed member adds role 4 by type.
    ];

    public function __construct(
        protected GraphSource $source,
        private string $driverName = 'relational',
    ) {
        $this->spine = new InMemoryDriver;
    }

    public function name(): string
    {
        return $this->driverName;
    }

    // --- StructureStore (role 1) — read from the hydrated snapshot -----------

    public function putNode(NodeContract $node): void
    {
        // Declare writes land in the in-memory snapshot only (the conformance kit
        // seeds through here). A consumer that persists overrides this.
        $this->spine()->putNode($node);
    }

    public function putEdge(EdgeContract $edge): void
    {
        $this->spine()->putEdge($edge);
    }

    public function getNode(NodeIdContract $id): ?NodeContract
    {
        // Snapshot-uniform: getNode is answered from the hydrated spine like every
        // other read, never a bespoke live lookup.
        return $this->spine()->getNode($id);
    }

    /** @return list<NodeContract> */
    public function neighbours(
        NodeIdContract $of,
        TraversalDirection $direction = TraversalDirection::Descendants,
        ?int $maxDepth = null,
    ): array {
        return $this->spine()->neighbours($of, $direction, $maxDepth);
    }

    // --- EnumerableStore (role 5) — dump the hydrated snapshot ---------------

    /** @return list<NodeContract> */
    public function nodes(): array
    {
        return $this->spine()->nodes();
    }

    /** @return list<EdgeContract> */
    public function edges(): array
    {
        return $this->spine()->edges();
    }

    // --- ComputeStore (role 2) — delegate to the snapshot --------------------

    public function shortestPath(NodeIdContract $from, NodeIdContract $to): ?PathContract
    {
        return $this->spine()->shortestPath($from, $to);
    }

    /** @return array<string,float> */
    public function rank(): array
    {
        return $this->spine()->rank();
    }

    /** @return list<PathContract> */
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
     * so reads reflect it — preserving "fresh snapshot per read"
     * correctness while a read-only consumer still hydrates only once.
     */
    protected function invalidateSnapshot(): void
    {
        $this->spine = new InMemoryDriver;
        $this->hydrated = false;
    }
}
