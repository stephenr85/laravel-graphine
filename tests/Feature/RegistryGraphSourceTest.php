<?php

namespace Rushing\Graphine\Tests\Feature;

use Rushing\Graphine\Drivers\RelationalDriver;
use Rushing\Graphine\Drivers\RelationalDriverFactory;
use Rushing\Graphine\Dto\NodeId;
use Rushing\Graphine\Enums\TraversalDirection;
use Rushing\Graphine\Laravel\Sources\RegistryGraphSource;
use Rushing\Graphine\Tests\Fixtures\UriKey;
use Rushing\Graphine\Tests\TestCase;
use Rushing\Popcorn\Registries\Authorizer;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\RegistryArity;
use Rushing\Popcorn\Registries\RegistryIndex;
use Rushing\Popcorn\Registries\RegistryKey;

class RegistryGraphSourceTest extends TestCase
{
    private function registry(string $root = 'demo'): BasicRegistry
    {
        return new BasicRegistry(new IsRegistry(
            root: $root,
            of: 'string',
            arity: RegistryArity::PickOne,
        ));
    }

    private function nodeTypes(RegistryGraphSource $source): array
    {
        $types = [];
        foreach ($source->nodes() as $node) {
            $types[(string) $node->id()] = $node->type();
        }
        ksort($types);

        return $types;
    }

    private function edgePairs(RegistryGraphSource $source): array
    {
        $pairs = [];
        foreach ($source->edges() as $edge) {
            $pairs[] = (string) $edge->from().' -> '.(string) $edge->to();
        }
        sort($pairs);

        return $pairs;
    }

    public function test_entries_become_nodes_and_derived_branches_are_nodes_too(): void
    {
        $registry = $this->registry();
        $registry->register('invoices.list', 'a');
        $registry->register('invoices.show', 'b');
        $registry->register('orders', 'c');

        // `demo.invoices` is registered by nobody and is a first-class graph node anyway,
        // because GraphSource requires every edge endpoint to be a node.
        $this->assertSame([
            'demo' => 'Branch',
            'demo.invoices' => 'Branch',
            'demo.invoices.list' => 'Entry',
            'demo.invoices.show' => 'Entry',
            'demo.orders' => 'Entry',
        ], $this->nodeTypes(new RegistryGraphSource($registry)));
    }

    public function test_the_asymmetry_a_branch_node_exists_that_the_registry_refuses_to_address(): void
    {
        $registry = $this->registry();
        $registry->register('invoices.list', 'a');

        $types = $this->nodeTypes(new RegistryGraphSource($registry));

        $this->assertSame('Branch', $types['demo.invoices']);
        // The same address, asked of the registry:
        $this->assertFalse($registry->has('invoices'));
    }

    public function test_edges_are_parent_to_child_and_deduplicated(): void
    {
        $registry = $this->registry();
        $registry->register('invoices.list', 'a');
        $registry->register('invoices.show', 'b');

        $this->assertSame([
            'demo -> demo.invoices',
            'demo.invoices -> demo.invoices.list',
            'demo.invoices -> demo.invoices.show',
        ], $this->edgePairs(new RegistryGraphSource($registry)));
    }

    public function test_it_is_spine_only_so_the_factory_returns_the_ungoverned_driver(): void
    {
        $source = new RegistryGraphSource($this->registry());

        $this->assertFalse($source->providesGates());
        $this->assertSame([], iterator_to_array((function () use ($source) {
            yield from $source->gates();
        })()));

        $driver = RelationalDriverFactory::make($source, 'registry');
        $this->assertInstanceOf(RelationalDriver::class, $driver);
        $this->assertNotInstanceOf(\Rushing\Graphine\Contracts\GovernedStore::class, $driver);
    }

    public function test_a_foreign_key_is_identified_by_segments_and_displayed_by_its_own_rendering(): void
    {
        $registry = new BasicRegistry(new IsRegistry(
            root: 'ns',
            of: 'string',
            arity: RegistryArity::PickOne,
        ));

        // A foreign key is never stamped with the root (ticket 20 D3) and never parsed.
        $registry->register(new UriKey(['jsonns', 'grounding', '2'], 'https://splicewire.dev/ns/grounding/2'), 'x');

        $properties = [];
        foreach ((new RegistryGraphSource($registry))->nodes() as $node) {
            $properties[(string) $node->id()] = $node->properties();
        }

        $this->assertArrayHasKey('jsonns.grounding.2', $properties);
        $this->assertSame(
            'https://splicewire.dev/ns/grounding/2',
            $properties['jsonns.grounding.2']['display'],
        );
        // The branch above it renders as the segment join — it is not the owner's URI.
        $this->assertSame('jsonns.grounding', $properties['jsonns.grounding']['display']);
    }

    public function test_a_foreign_key_whose_segments_contain_the_separator_refuses_to_graph(): void
    {
        $registry = new BasicRegistry(new IsRegistry(
            root: 'ns',
            of: 'string',
            arity: RegistryArity::PickOne,
        ));

        $registry->register(new UriKey(['a.b', 'c'], 'one'), 'x');
        $registry->register(new UriKey(['a', 'b.c'], 'two'), 'y');

        $this->expectExceptionMessageMatches('/Two distinct registry addresses render as the node id `a\.b\.c`/');

        iterator_to_array((function () use ($registry) {
            yield from (new RegistryGraphSource($registry))->nodes();
        })());
    }

    public function test_the_index_graphs_as_a_forest_because_its_own_root_has_no_spelling(): void
    {
        $index = new RegistryIndex;
        $index->describe($this->registry('beam.particle'));
        $index->describe($this->registry('beam.particle.fragments.ops'));

        // The self-hosting zero-segment entry is skipped; interleaved roots nest (ticket 26 D0).
        $this->assertSame([
            'beam' => 'Branch',
            'beam.particle' => 'Entry',
            'beam.particle.fragments' => 'Branch',
            'beam.particle.fragments.ops' => 'Entry',
        ], $this->nodeTypes(new RegistryGraphSource($index)));
    }

    public function test_a_filtered_read_yields_a_filtered_graph(): void
    {
        $registry = $this->registry();
        $registry->register('invoices.list', 'a', ability: 'invoices.view');
        $registry->register('orders', 'b');

        $registry->authorizeWith(new class implements Authorizer
        {
            public function allows(string $ability, RegistryKey $key): bool
            {
                return false;
            }
        });

        // The gated entry is absent, and so is the `demo.invoices` BRANCH it was the only
        // reason for — a filtered graph loses structure, not just leaves.
        $this->assertSame([
            'demo' => 'Branch',
            'demo.orders' => 'Entry',
        ], $this->nodeTypes(new RegistryGraphSource($registry)));
        $this->assertSame([
            'demo' => 'Branch',
            'demo.invoices' => 'Branch',
            'demo.invoices.list' => 'Entry',
            'demo.orders' => 'Entry',
        ], $this->nodeTypes(new RegistryGraphSource($registry, unfiltered: true)));
    }

    /**
     * B1, run rather than argued: every traversal the hydrated graph answers over a pure key
     * tree is already answerable from `keys()` + `segments()`, with no graph in the picture.
     */
    public function test_b1_the_graph_answers_nothing_the_keyspace_does_not_already_answer(): void
    {
        $registry = $this->registry();
        foreach (['invoices.list', 'invoices.show', 'invoices.ops.void', 'orders.list'] as $key) {
            $registry->register($key, $key);
        }

        $driver = RelationalDriverFactory::make(new RegistryGraphSource($registry), 'registry');

        $viaGraph = array_map(
            fn ($node) => (string) $node->id(),
            $driver->neighbours(NodeId::of('demo.invoices'), TraversalDirection::Descendants, maxDepth: 1),
        );
        sort($viaGraph);

        $viaRegistry = array_map('strval', $registry->children('invoices'));
        sort($viaRegistry);

        $this->assertSame($viaRegistry, $viaGraph);
        $this->assertNotSame([], $viaGraph);
    }
}
