<?php

declare(strict_types=1);

namespace Rushing\Graphine\Testing;

use PHPUnit\Framework\TestCase;
use Rushing\Graphine\Contracts\ComputeStore;
use Rushing\Graphine\Contracts\GovernedStore;
use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Contracts\StructureStore;
use Rushing\Graphine\Dto\Edge;
use Rushing\Graphine\Dto\Node;
use Rushing\Graphine\Dto\NodeId;
use Rushing\Graphine\Enums\Capability;
use Rushing\Graphine\Enums\TraversalDirection;

/**
 * THE CONFORMANCE TEST-KIT — the seam by which any driver self-certifies.
 *
 * Ticket 02 named this a first-class package component (the "portfolio flex"):
 * the package ships the contract + value types + one reference driver + THIS
 * abstract test. A consumer's driver — authored app-side over its own wheel —
 * extends this class, returns its driver from createDriver(), and inherits the
 * whole contract-conformance suite for free. The in-memory reference driver is
 * the oracle every real driver is measured against.
 *
 * The kit is CAPABILITY-AWARE: it exercises the mandatory spine on every driver,
 * and the optional sub-contracts (GovernedStore / role-4 gating) only when the
 * driver both advertises the capability AND implements the interface — mirroring
 * the à-la-carte, type-level opt-in the seam is built on (ticket 03). It asserts
 * the two load-bearing LAWS, not a specific algorithm, so a stubbed reference
 * driver and a production driver both pass the same suite.
 *
 * Ships in src/ (autoloaded, not test-only) so consumers can extend it; it needs
 * a phpunit-providing host.
 */
abstract class GraphStoreConformance extends TestCase
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
     * THE GOVERNANCE-AS-GATING LAW (ticket 03). Only for drivers that opt into
     * role 4 by TYPE — this is the type-level opt-in that replaced the nullable
     * `?Coherence` field. A gate of 0.0 silences a node no matter how it
     * computes; that is the whole evidenced contract (numero asserted_weight).
     */
    public function test_governance_gate_of_zero_silences_a_node(): void
    {
        $driver = $this->createDriver();
        if (! $driver instanceof GovernedStore) {
            $this->markTestSkipped('driver does not implement GovernedStore (role 4) — governance is à-la-carte');
        }
        if (! $driver instanceof StructureStore) {
            $this->markTestSkipped('needs StructureStore to seed nodes');
        }

        $this->assertTrue($driver->supports(Capability::Governance));

        $driver->putNode(new Node(NodeId::of('kept'), 'Entity'));
        $driver->putNode(new Node(NodeId::of('silenced'), 'Entity'));
        $driver->putEdge(new Edge(NodeId::of('kept'), NodeId::of('silenced'), 'LINKS'));

        $driver->assertGovernance(NodeId::of('silenced'), 0.0);

        $governed = $driver->governedRank();
        $this->assertArrayNotHasKey('silenced', $governed, 'gate 0.0 must drop the node');
        $this->assertArrayHasKey('kept', $governed, 'un-gated node must survive (pass-through gate 1.0)');
    }
}
