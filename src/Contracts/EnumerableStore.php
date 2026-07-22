<?php

declare(strict_types=1);

namespace Rushing\Graphine\Contracts;

use Rushing\Graphine\Dto\Edge;
use Rushing\Graphine\Dto\Node;

/**
 * ROLE 5 — OPTIONAL whole-snapshot enumeration. A bounded DUMP, not a walk.
 *
 * Optional, à-la-carte sub-contract OFF the mandatory StructureStore +
 * ComputeStore spine — the same shape as {@see QueryableStore} (role 3): a
 * driver MAY expose it, and capability stays honest by TYPE (a driver that
 * enumerates is `instanceof EnumerableStore` and advertises
 * `Capability::Enumerate`; one that can't simply omits both).
 *
 * WHY IT IS NOT `neighbours()`. The mandatory spine reads a graph from an ANCHOR
 * outward (`StructureStore::neighbours(NodeId ...)`). A consumer that must render
 * the whole bounded graph with no single anchor — a visualization's fragment /
 * scope / silo / hub-ranking modes — has no `NodeId` to hand `neighbours()`. This
 * role answers that need directly: the full node set and the full edge set of the
 * bounded snapshot, in one dump.
 *
 * WHO IMPLEMENTS IT. The relational (snapshot) family gets it for free — the
 * spine already holds every node and edge in memory, so the dump is a read of an
 * array it already built. A traverse-native driver over millions of nodes (AGE /
 * Neo4j) DECLINES the role — it cannot cheaply enumerate an unbounded store — and
 * serves the same consumer via `neighbours()` from an anchor, preserving the
 * à-la-carte-by-`instanceof` law and the hybrid escalation it was designed for.
 *
 * The dump is a READ of the bounded snapshot the driver already scoped; it is the
 * caller's job to scope the source (e.g. a scoped relational source) so the dump
 * stays bounded. Enumeration never promises the whole underlying store.
 */
interface EnumerableStore
{
    /**
     * Every node in the bounded snapshot, in no guaranteed order.
     *
     * @return list<Node>
     */
    public function nodes(): array;

    /**
     * Every edge in the bounded snapshot, in no guaranteed order.
     *
     * @return list<Edge>
     */
    public function edges(): array;
}
