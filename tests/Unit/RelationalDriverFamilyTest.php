<?php

declare(strict_types=1);

namespace Rushing\Graphine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rushing\Graphine\Contracts\GovernedStore;
use Rushing\Graphine\Contracts\QueryableStore;
use Rushing\Graphine\Drivers\GovernedRelationalDriver;
use Rushing\Graphine\Drivers\RelationalDriver;
use Rushing\Graphine\Drivers\RelationalDriverFactory;
use Rushing\Graphine\Dto\Edge;
use Rushing\Graphine\Dto\Node;
use Rushing\Graphine\Dto\NodeId;
use Rushing\Graphine\Enums\Capability;
use Rushing\Graphine\Tests\Fixtures\ArrayGraphSource;

/**
 * ADR-0102 driver-family laws that are cheaper to assert directly than through
 * the full conformance kit: factory selection, instanceof/supports agreement for
 * BOTH members (build ticket 04 à-la-carte law), snapshot hydration from a
 * populated source, and gate loading from the source (not seeded by the caller).
 */
final class RelationalDriverFamilyTest extends TestCase
{
    public function test_factory_selects_the_spine_only_member_for_an_ungoverned_source(): void
    {
        $driver = RelationalDriverFactory::make(new ArrayGraphSource, 'relational');

        $this->assertInstanceOf(RelationalDriver::class, $driver);
        $this->assertNotInstanceOf(GovernedStore::class, $driver);
    }

    public function test_factory_selects_the_governed_member_for_a_gate_declaring_source(): void
    {
        $driver = RelationalDriverFactory::make(new ArrayGraphSource(governed: true), 'g');

        $this->assertInstanceOf(GovernedRelationalDriver::class, $driver);
        $this->assertInstanceOf(GovernedStore::class, $driver);
    }

    public function test_instanceof_and_supports_agree_for_both_family_members(): void
    {
        foreach ([
            RelationalDriverFactory::make(new ArrayGraphSource, 'relational'),
            RelationalDriverFactory::make(new ArrayGraphSource(governed: true), 'g'),
        ] as $driver) {
            $this->assertSame(
                $driver instanceof GovernedStore,
                $driver->supports(Capability::Governance),
                'supports(Governance) must agree with instanceof GovernedStore',
            );
            // Neither family member speaks a wire format — role 3 stays off the spine.
            $this->assertNotInstanceOf(QueryableStore::class, $driver);
            $this->assertFalse($driver->supports(Capability::QueryAtScale));
            $this->assertSame([], $driver->speaks());
        }
    }

    public function test_it_hydrates_the_spine_from_the_source_once(): void
    {
        $source = new ArrayGraphSource(
            nodes: [
                new Node(NodeId::of('a'), 'Entity'),
                new Node(NodeId::of('b'), 'Entity'),
                new Node(NodeId::of('c'), 'Entity'),
            ],
            edges: [
                new Edge(NodeId::of('a'), NodeId::of('b'), 'LINKS'),
                new Edge(NodeId::of('b'), NodeId::of('c'), 'LINKS'),
            ],
        );

        $driver = RelationalDriverFactory::make($source, 'relational');

        // getNode is snapshot-uniform — answered from the hydrated source.
        $this->assertNotNull($driver->getNode(NodeId::of('a')));
        $this->assertSame(2, $driver->shortestPath(NodeId::of('a'), NodeId::of('c'))?->length());
        $this->assertArrayHasKey('c', $driver->rank());
    }

    public function test_governed_member_loads_gates_from_the_source(): void
    {
        // A star: three leaves point at the hub, so the hub computes most-central.
        // The source gates the hub to 0 — the governed driver must silence it
        // WITHOUT the caller ever calling assertGovernance.
        $source = new ArrayGraphSource(
            nodes: [
                new Node(NodeId::of('hub'), 'Entity'),
                new Node(NodeId::of('l1'), 'Entity'),
                new Node(NodeId::of('l2'), 'Entity'),
                new Node(NodeId::of('l3'), 'Entity'),
            ],
            edges: [
                new Edge(NodeId::of('l1'), NodeId::of('hub'), 'LINKS'),
                new Edge(NodeId::of('l2'), NodeId::of('hub'), 'LINKS'),
                new Edge(NodeId::of('l3'), NodeId::of('hub'), 'LINKS'),
            ],
            gates: [[NodeId::of('hub'), 0.0]],
            governed: true,
        );

        $driver = RelationalDriverFactory::make($source, 'g');
        $this->assertInstanceOf(GovernedStore::class, $driver);

        $this->assertArrayHasKey('hub', $driver->rank(), 'the hub is present before gating');
        $governed = $driver->governedRank();
        $this->assertArrayNotHasKey('hub', $governed, 'a source gate of 0 silences the hub');
        $this->assertArrayHasKey('l1', $governed, 'un-gated nodes survive');
    }
}
