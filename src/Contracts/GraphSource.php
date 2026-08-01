<?php

namespace Rushing\Graphine\Contracts;

use Rushing\Graphine\Drivers\RelationalDriver;

/**
 * THE GRAPH-SOURCE SEAM — the one thing that varies per consumer.
 *
 * A `GraphSource` sits between *how graph structure is stored* and *how it is
 * traversed*. It yields the nodes, edges, and (optional) governance gates of
 * some storage representation; the {@see RelationalDriver}
 * family hydrates them into the in-memory spine ONCE and answers every read —
 * including `getNode` — from that snapshot (a bounded-snapshot model).
 * The source is the only per-consumer variation; everything downstream (compute,
 * governance modulation, capability reporting) is shared.
 *
 * The three relational consumers (KG's rdf_triples, Circuits'
 * circuit_*, numero's archetype_interactions) proved to be the SAME move — pull
 * (from, to[, weight]) out of storage, hydrate, delegate all compute. That move
 * is the driver family; this interface is the only seam it leaves open.
 *
 * A source that yields gates (declared by {@see providesGates()}) selects the
 * GOVERNED member of the family; one that does not stays spine-only. The
 * capability the resulting driver advertises therefore stays honest BY TYPE
 * — the factory picks the class from the source.
 *
 * Constructed WITH ITS OWN SCOPE (a fragment id, a circuit id, a tenant): the
 * source closes over whatever it needs to read, so the driver stays scope-blind.
 */
interface GraphSource
{
    /**
     * Every node of the (scoped) graph — including nodes that appear only as an
     * edge endpoint, so the hydrated spine can answer `getNode` for them.
     *
     * @return iterable<NodeContract>
     */
    public function nodes(): iterable;

    /**
     * Every directed (optionally weighted) edge of the (scoped) graph.
     *
     * @return iterable<EdgeContract>
     */
    public function edges(): iterable;

    /**
     * Host-asserted governance gates — a `[NodeId, float]` pair per gated node,
     * the scalar in [0,1] (`score = gate · computed`; `gate = 0` silences). Empty
     * for a spine-only source. NEVER a Node/Edge field — the two-weights
     * separation rides here, off the structural spine.
     *
     * @return iterable<array{0: NodeIdContract, 1: float}>
     */
    public function gates(): iterable;

    /**
     * Does this source DECLARE a gate source? This is a static declaration of
     * intent (distinct from whether {@see gates()} happens to yield rows at
     * runtime) that the factory reads to pick the governed vs spine-only member
     * of the driver family. Keeping it type-level preserves the à-la-carte-by-type
     * law: capability is decided at wiring time, never a per-node runtime check.
     */
    public function providesGates(): bool;
}
