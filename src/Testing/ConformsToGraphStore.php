<?php

declare(strict_types=1);

namespace Rushing\Graphine\Testing;

use Rushing\Graphine\Contracts\ComputeStore;
use Rushing\Graphine\Contracts\EnumerableStore;
use Rushing\Graphine\Contracts\GovernedStore;
use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Contracts\QueryableStore;
use Rushing\Graphine\Contracts\StructureStore;
use Rushing\Graphine\Dto\Edge;
use Rushing\Graphine\Dto\Node;
use Rushing\Graphine\Dto\NodeId;
use Rushing\Graphine\Enums\Capability;
use Rushing\Graphine\Enums\TraversalDirection;

/**
 * THE CONFORMANCE ASSERTIONS — the reusable body of the test-kit, as a trait.
 *
 * Ticket 02 named the kit a first-class package component. Shipping it as a TRAIT
 * (in addition to the GraphStoreConformance base class) lets a consumer certify a
 * driver from WITHIN its own framework TestCase — e.g. a Laravel/tenancy-aware
 * TestCase that the abstract base class could never extend (PHP is single-
 * inheritance). The consumer does:
 *
 *   final class MyDriverConformanceTest extends \Tests\TestCase
 *   {
 *       use ConformsToGraphStore;
 *       protected function createDriver(): GraphStore { return new MyDriver(...); }
 *   }
 *
 * The kit is CAPABILITY-AWARE: it exercises the mandatory spine on every driver,
 * and the optional sub-contracts (GovernedStore / QueryableStore) only when the
 * driver both advertises the capability AND implements the interface. It asserts
 * the load-bearing LAWS, not a specific algorithm, so a stubbed reference driver
 * and a production driver both pass the same suite.
 */
trait ConformsToGraphStore
{
    /** Return a fresh driver under test. */
    abstract protected function createDriver(): GraphStore;

    public function test_it_reports_a_stable_name(): void
    {
        $this->assertNotSame('', $this->createDriver()->name());
    }

    public function test_supports_is_truthful_about_the_spine(): void
    {
        $driver = $this->createDriver();

        // A driver implementing a spine sub-contract MUST advertise its capability.
        if ($driver instanceof StructureStore) {
            $this->assertTrue($driver->supports(Capability::Declare));
        }
        if ($driver instanceof ComputeStore) {
            $this->assertTrue($driver->supports(Capability::Compute));
        }
    }

    public function test_structure_round_trips_a_node(): void
    {
        $driver = $this->createDriver();
        if (! $driver instanceof StructureStore) {
            $this->markTestSkipped('driver does not implement StructureStore (role 1)');
        }

        $id = NodeId::of('n1');
        $driver->putNode(new Node($id, 'Entity'));

        $this->assertNotNull($driver->getNode($id));
        $this->assertTrue($driver->getNode($id)?->id->equals($id));
    }

    public function test_compute_rank_returns_a_score_map(): void
    {
        $driver = $this->createDriver();
        if (! $driver instanceof ComputeStore) {
            $this->markTestSkipped('driver does not implement ComputeStore (role 2)');
        }
        if (! $driver instanceof StructureStore) {
            $this->markTestSkipped('needs StructureStore to seed nodes');
        }

        $driver->putNode(new Node(NodeId::of('a'), 'Entity'));
        $driver->putNode(new Node(NodeId::of('b'), 'Entity'));

        $ranks = $driver->rank();
        $this->assertArrayHasKey('a', $ranks);
        $this->assertIsFloat($ranks['a']);
    }

    /**
     * TRAVERSAL REACHABILITY (role 2). A recursive descendants read reaches the
     * transitive set along edge direction; the ancestors read reaches it the
     * other way. Asserts the direction law, not a specific walk order.
     */
    public function test_traversal_reaches_transitive_neighbours(): void
    {
        $driver = $this->createDriver();
        if (! $driver instanceof StructureStore) {
            $this->markTestSkipped('driver does not implement StructureStore (role 1)');
        }

        foreach (['a', 'b', 'c'] as $id) {
            $driver->putNode(new Node(NodeId::of($id), 'Entity'));
        }
        $driver->putEdge(new Edge(NodeId::of('a'), NodeId::of('b'), 'LINKS'));
        $driver->putEdge(new Edge(NodeId::of('b'), NodeId::of('c'), 'LINKS'));

        $descIds = array_map(
            static fn (Node $n): string => $n->id->value,
            $driver->neighbours(NodeId::of('a'), TraversalDirection::Descendants),
        );
        $this->assertContains('b', $descIds, 'descendant one hop away must be reached');
        $this->assertContains('c', $descIds, 'descendant two hops away must be reached (recursive)');
        $this->assertNotContains('a', $descIds, 'the origin is not its own neighbour');

        $ancIds = array_map(
            static fn (Node $n): string => $n->id->value,
            $driver->neighbours(NodeId::of('c'), TraversalDirection::Ancestors),
        );
        $this->assertContains('b', $ancIds);
        $this->assertContains('a', $ancIds, 'ancestor two hops away must be reached (recursive)');
    }

    /**
     * PATH SHAPE (role 2). A shortest path is an ordered walk, source first,
     * target last, with a non-negative accumulated cost and a length that
     * matches the node count. Asserts the shape law across driver tiers.
     */
    public function test_shortest_path_has_source_first_target_last(): void
    {
        $driver = $this->createDriver();
        if (! $driver instanceof ComputeStore) {
            $this->markTestSkipped('driver does not implement ComputeStore (role 2)');
        }
        if (! $driver instanceof StructureStore) {
            $this->markTestSkipped('needs StructureStore to seed nodes');
        }

        foreach (['a', 'b', 'c'] as $id) {
            $driver->putNode(new Node(NodeId::of($id), 'Entity'));
        }
        $driver->putEdge(new Edge(NodeId::of('a'), NodeId::of('b'), 'LINKS', 1.0));
        $driver->putEdge(new Edge(NodeId::of('b'), NodeId::of('c'), 'LINKS', 1.0));

        $path = $driver->shortestPath(NodeId::of('a'), NodeId::of('c'));
        $this->assertNotNull($path, 'a reachable target must yield a path');
        $this->assertTrue($path->nodes[0]->equals(NodeId::of('a')), 'source first');
        $this->assertTrue($path->nodes[count($path->nodes) - 1]->equals(NodeId::of('c')), 'target last');
        $this->assertGreaterThanOrEqual(0.0, $path->cost, 'cost is non-negative');
        $this->assertSame(count($path->nodes) - 1, $path->length(), 'length = edges walked');

        $this->assertNull(
            $driver->shortestPath(NodeId::of('c'), NodeId::of('a')),
            'no directed path backwards → null',
        );
    }

    /**
     * THE GOVERNANCE-AS-GATING LAW (ticket 03) — a CENTRAL node silenced. Only
     * for drivers that opt into role 4 by TYPE (the type-level opt-in that
     * replaced the nullable `?Coherence` field). A gate of 0.0 silences a node
     * no matter how central it computes — the whole evidenced contract (numero
     * asserted_weight). We build a hub with high centrality, confirm it tops the
     * ungoverned rank, then gate it to 0.0 and assert it drops out entirely.
     */
    public function test_governance_gate_of_zero_silences_a_central_node(): void
    {
        $driver = $this->createDriver();
        if (! $driver instanceof GovernedStore) {
            $this->markTestSkipped('driver does not implement GovernedStore (role 4) — governance is à-la-carte');
        }
        if (! $driver instanceof StructureStore || ! $driver instanceof ComputeStore) {
            $this->markTestSkipped('needs the StructureStore+ComputeStore spine to seed + rank');
        }

        $this->assertTrue($driver->supports(Capability::Governance));

        // A star: three leaves all point at the hub → the hub is the most central.
        foreach (['hub', 'l1', 'l2', 'l3'] as $id) {
            $driver->putNode(new Node(NodeId::of($id), 'Entity'));
        }
        foreach (['l1', 'l2', 'l3'] as $leaf) {
            $driver->putEdge(new Edge(NodeId::of($leaf), NodeId::of('hub'), 'LINKS'));
        }

        $rank = $driver->rank();
        $this->assertGreaterThan(
            max($rank['l1'], $rank['l2'], $rank['l3']),
            $rank['hub'],
            'the hub must be the most central node before governance',
        );

        $driver->assertGovernance(NodeId::of('hub'), 0.0);

        $governed = $driver->governedRank();
        $this->assertArrayNotHasKey('hub', $governed, 'gate 0.0 must silence even the most central node');
        $this->assertArrayHasKey('l1', $governed, 'un-gated nodes survive (pass-through gate 1.0)');
    }

    /**
     * THE PASS-THROUGH HALF OF THE LAW (ticket 03): `gate = 1` leaves compute
     * unchanged. An ungoverned (or explicitly gate-1) node's governed score
     * equals its ungoverned rank — governance modulates, it does not recompute.
     */
    public function test_governance_gate_of_one_leaves_compute_unchanged(): void
    {
        $driver = $this->createDriver();
        if (! $driver instanceof GovernedStore) {
            $this->markTestSkipped('driver does not implement GovernedStore (role 4) — governance is à-la-carte');
        }
        if (! $driver instanceof StructureStore || ! $driver instanceof ComputeStore) {
            $this->markTestSkipped('needs the StructureStore+ComputeStore spine to seed + rank');
        }

        $driver->putNode(new Node(NodeId::of('a'), 'Entity'));
        $driver->putNode(new Node(NodeId::of('b'), 'Entity'));
        $driver->putEdge(new Edge(NodeId::of('a'), NodeId::of('b'), 'LINKS'));

        $driver->assertGovernance(NodeId::of('a'), 1.0);

        $rank = $driver->rank();
        $governed = $driver->governedRank();

        $this->assertArrayHasKey('a', $governed);
        $this->assertEqualsWithDelta(
            $rank['a'],
            $governed['a'],
            1e-9,
            'gate 1.0 is pure pass-through — the governed score equals the computed rank',
        );
    }

    /**
     * THE À-LA-CARTE-BY-TYPE LAW (ticket 04). Optional roles are opted into by
     * TYPE, and `supports()` must agree with the interfaces the driver actually
     * implements — never a nullable field, never a lie. A spine-only driver is
     * simply not `instanceof` the optional contracts.
     */
    public function test_optional_roles_are_opt_in_by_type(): void
    {
        $driver = $this->createDriver();

        $this->assertSame(
            $driver instanceof GovernedStore,
            $driver->supports(Capability::Governance),
            'supports(Governance) must agree with instanceof GovernedStore',
        );

        $this->assertSame(
            $driver instanceof QueryableStore,
            $driver->supports(Capability::QueryAtScale),
            'supports(QueryAtScale) must agree with instanceof QueryableStore',
        );

        $this->assertSame(
            $driver instanceof EnumerableStore,
            $driver->supports(Capability::Enumerate),
            'supports(Enumerate) must agree with instanceof EnumerableStore',
        );

        if ($driver->speaks() !== []) {
            $this->assertInstanceOf(
                QueryableStore::class,
                $driver,
                'a driver that speaks a wire format must implement QueryableStore',
            );
            $this->assertTrue($driver->supports(Capability::QueryAtScale));
        }

        if (! $driver instanceof QueryableStore) {
            $this->assertSame([], $driver->speaks(), 'a non-queryable driver speaks nothing');
        }
    }

    /**
     * NATIVE-QUERY PASSTHROUGH (ticket 04 point 5). For a driver that opts into
     * role 3, every format it `speaks()` is answerable and returns opaque rows —
     * the seam passes the statement through rather than re-abstracting a query
     * language. Skipped entirely for spine-only drivers.
     */
    public function test_queryable_passthrough_returns_opaque_rows(): void
    {
        $driver = $this->createDriver();
        if (! $driver instanceof QueryableStore) {
            $this->markTestSkipped('driver does not implement QueryableStore (role 3) — query is à-la-carte');
        }

        $this->assertNotEmpty($driver->speaks(), 'a queryable driver must advertise at least one format');

        foreach ($driver->speaks() as $format) {
            $result = $driver->query($format, 'MATCH (n) RETURN n', ['limit' => 1]);
            $this->assertGreaterThanOrEqual(0, $result->count(), 'passthrough returns a row set');
        }
    }

    /**
     * WHOLE-SNAPSHOT ENUMERATION (ticket 18). For a driver that opts into role 5,
     * `nodes()`/`edges()` dump the FULL bounded snapshot — every seeded node and
     * every seeded edge, the anchorless read a visualization needs. Asserts the
     * dump agrees with what was declared, not an order. Skipped for drivers that
     * decline the role (a traverse-native store that cannot cheaply enumerate).
     */
    public function test_enumerable_dump_agrees_with_the_seeded_spine(): void
    {
        $driver = $this->createDriver();
        if (! $driver instanceof EnumerableStore) {
            $this->markTestSkipped('driver does not implement EnumerableStore (role 5) — enumeration is à-la-carte');
        }
        if (! $driver instanceof StructureStore) {
            $this->markTestSkipped('needs StructureStore to seed the snapshot');
        }

        $this->assertTrue($driver->supports(Capability::Enumerate));

        foreach (['a', 'b', 'c'] as $id) {
            $driver->putNode(new Node(NodeId::of($id), 'Entity'));
        }
        $driver->putEdge(new Edge(NodeId::of('a'), NodeId::of('b'), 'LINKS'));
        $driver->putEdge(new Edge(NodeId::of('b'), NodeId::of('c'), 'LINKS'));

        // The node dump is the full seeded set — a whole-graph read, not a walk
        // from an anchor (which would miss the anchorless viz modes ticket 18 exists for).
        $nodeIds = array_map(static fn (Node $n): string => $n->id->value, $driver->nodes());
        sort($nodeIds);
        $this->assertSame(['a', 'b', 'c'], $nodeIds, 'nodes() dumps every node in the bounded snapshot');

        // The edge dump is the full seeded edge set, endpoints preserved.
        $edgePairs = array_map(
            static fn (Edge $e): string => $e->from->value.'->'.$e->to->value,
            $driver->edges(),
        );
        sort($edgePairs);
        $this->assertSame(['a->b', 'b->c'], $edgePairs, 'edges() dumps every edge in the bounded snapshot');
    }

    /**
     * CALLERS BRANCH ON supports(), NOT concrete instanceof (ticket 04). A guard
     * written against the capability keeps working when a driver widens or
     * narrows coverage — proven here by a supports()-guarded score path that
     * yields a map on ANY spine driver, governed or not.
     */
    public function test_supports_guarded_path_survives_capability_changes(): void
    {
        $driver = $this->createDriver();
        if (! $driver instanceof StructureStore || ! $driver instanceof ComputeStore) {
            $this->markTestSkipped('needs the spine to seed + score');
        }

        $driver->putNode(new Node(NodeId::of('a'), 'Entity'));
        $driver->putNode(new Node(NodeId::of('b'), 'Entity'));
        $driver->putEdge(new Edge(NodeId::of('a'), NodeId::of('b'), 'LINKS'));

        // The call site guards on the CAPABILITY, not the concrete class.
        $scores = $driver->supports(Capability::Governance) && $driver instanceof GovernedStore
            ? $driver->governedRank()
            : $driver->rank();

        $this->assertNotEmpty($scores, 'a supports()-guarded score path yields a map regardless of coverage');
    }
}
