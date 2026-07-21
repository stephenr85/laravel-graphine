<?php

declare(strict_types=1);

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Facades\Schema;
use Rushing\Graphine\Contracts\GovernedStore;
use Rushing\Graphine\Drivers\GovernedRelationalDriver;
use Rushing\Graphine\Drivers\RelationalDriver;
use Rushing\Graphine\Drivers\RelationalDriverFactory;
use Rushing\Graphine\Dto\NodeId;
use Rushing\Graphine\Enums\TraversalDirection;
use Rushing\Graphine\Sources\AdjacencyListSource;

// ADR-0102 build ticket 13 — AdjacencyListSource covers BOTH relational edge
// encodings (separate edge table + self-referential FK), config-only, and its
// optional gate selects the governed driver through the factory.

function resolver(): ConnectionResolverInterface
{
    return app('db');
}

it('graphs an edge table (nodes table + edges table), skipping out-of-snapshot edges', function () {
    Schema::create('al_nodes', function ($t) {
        $t->string('id');
        $t->string('ref');
        $t->string('type');
        $t->string('label')->nullable();
        $t->string('scope_id')->nullable();
        $t->primary('id');
    });
    Schema::create('al_edges', function ($t) {
        $t->id();
        $t->string('src');
        $t->string('dst');
        $t->string('scope_id')->nullable();
    });

    // Two scoped nodes + one node in another scope. An edge points at the
    // out-of-scope node and MUST be skipped.
    DB::table('al_nodes')->insert([
        ['id' => 'r1', 'ref' => 'a', 'type' => 'Step', 'label' => 'A', 'scope_id' => 's1'],
        ['id' => 'r2', 'ref' => 'b', 'type' => 'Step', 'label' => 'B', 'scope_id' => 's1'],
        ['id' => 'r9', 'ref' => 'z', 'type' => 'Step', 'label' => 'Z', 'scope_id' => 's2'],
    ]);
    DB::table('al_edges')->insert([
        ['src' => 'r1', 'dst' => 'r2', 'scope_id' => 's1'],
        ['src' => 'r2', 'dst' => 'r9', 'scope_id' => 's1'], // dst is out of the loaded snapshot
    ]);

    $source = new AdjacencyListSource(resolver(), [
        'nodes' => [
            'table' => 'al_nodes',
            'key' => 'id',
            'id' => 'ref',
            'type' => 'type',
            'properties' => ['label'],
            'scope' => ['scope_id' => 's1'],
        ],
        'edges' => [
            'table' => 'al_edges',
            'from' => 'src',
            'to' => 'dst',
            'type' => 'FLOWS_TO',
            'scope' => ['scope_id' => 's1'],
        ],
    ]);

    $driver = RelationalDriverFactory::make($source, 'al');

    expect($driver)->toBeInstanceOf(RelationalDriver::class)
        ->and($driver)->not->toBeInstanceOf(GovernedStore::class);

    // Node identity + type + folded properties survive.
    $a = $driver->getNode(NodeId::of('a'));
    expect($a)->not->toBeNull()
        ->and($a->type)->toBe('Step')
        ->and($a->properties['label'])->toBe('A');

    // The in-snapshot edge a→b is present; the a/b→z edge was skipped.
    $desc = collect($driver->neighbours(NodeId::of('a'), TraversalDirection::Descendants))
        ->map(fn ($n) => $n->id->value)->sort()->values()->all();
    expect($desc)->toBe(['b'])
        ->and($driver->getNode(NodeId::of('z')))->toBeNull();
});

it('graphs a self-referential FK table with no edge table (child_to_parent)', function () {
    Schema::create('al_tree', function ($t) {
        $t->id();
        $t->string('name');
        $t->foreignId('parent_id')->nullable();
    });

    $root = DB::table('al_tree')->insertGetId(['name' => 'root', 'parent_id' => null]);
    $child = DB::table('al_tree')->insertGetId(['name' => 'child', 'parent_id' => $root]);
    $grand = DB::table('al_tree')->insertGetId(['name' => 'grand', 'parent_id' => $child]);

    $source = new AdjacencyListSource(resolver(), [
        'nodes' => [
            'table' => 'al_tree',
            'key' => 'id',
            'id' => 'id',
            'type' => 'name',
        ],
        'parent' => [
            'column' => 'parent_id',
            'direction' => 'child_to_parent',
            'type' => 'CHILD_OF',
        ],
    ]);

    $driver = RelationalDriverFactory::make($source, 'tree');

    // Each CHILD_OF edge points child→parent, so following edge direction
    // (descendants) walks the parent chain: grand's are child then root.
    $parentChain = collect($driver->neighbours(NodeId::of((string) $grand), TraversalDirection::Descendants))
        ->map(fn ($n) => $n->id->value)->sort()->values()->all();
    expect($parentChain)->toBe(collect([$child, $root])->map('strval')->sort()->values()->all());

    // Conversely, the root's tree-descendants are reached the other way (ancestors).
    $treeDescendants = collect($driver->neighbours(NodeId::of((string) $root), TraversalDirection::Ancestors))
        ->map(fn ($n) => $n->id->value)->sort()->values()->all();
    expect($treeDescendants)->toBe(collect([$child, $grand])->map('strval')->sort()->values()->all());

    // A shortest path walks grand → child → root along the CHILD_OF direction.
    $path = $driver->shortestPath(NodeId::of((string) $grand), NodeId::of((string) $root));
    expect($path)->not->toBeNull()->and($path->length())->toBe(2);
});

it('emits a directed edge each way in bidirectional mode (undirected consumer)', function () {
    // numero's archetype_interactions shape: undirected, weighted rows + a per-node
    // gate column (asserted_weight). One config entry graphs it — no bespoke driver.
    Schema::create('al_arch', function ($t) {
        $t->string('id');
        $t->float('asserted')->nullable();
        $t->primary('id');
    });
    Schema::create('al_arch_edges', function ($t) {
        $t->id();
        $t->string('node_a');
        $t->string('node_b');
        $t->float('weight');
    });

    DB::table('al_arch')->insert([
        ['id' => 'x', 'asserted' => null],
        ['id' => 'y', 'asserted' => 0.0],   // gated to 0 → silenced
    ]);
    DB::table('al_arch_edges')->insert([
        ['node_a' => 'x', 'node_b' => 'y', 'weight' => 0.7],
    ]);

    $source = new AdjacencyListSource(resolver(), [
        'nodes' => ['table' => 'al_arch', 'key' => 'id', 'id' => 'id', 'type' => null],
        'edges' => [
            'table' => 'al_arch_edges',
            'from' => 'node_a',
            'to' => 'node_b',
            'weight' => 'weight',
            'type' => 'INTERACTS',
            'bidirectional' => true,
        ],
        'gate' => ['column' => 'asserted'],
    ]);

    $driver = RelationalDriverFactory::make($source, 'numero-archetypes');
    expect($driver)->toBeInstanceOf(GovernedRelationalDriver::class);

    // Undirected → reachable BOTH ways.
    $fromX = collect($driver->neighbours(NodeId::of('x'), TraversalDirection::Descendants))
        ->map(fn ($n) => $n->id->value)->all();
    $fromY = collect($driver->neighbours(NodeId::of('y'), TraversalDirection::Descendants))
        ->map(fn ($n) => $n->id->value)->all();
    expect($fromX)->toBe(['y'])->and($fromY)->toBe(['x']);

    // The gate-0 silence law (LayerSalience parity) holds on the weighted graph.
    expect($driver->governedRank())->not->toHaveKey('y')
        ->and($driver->governedRank())->toHaveKey('x');
});

it('selects the governed driver when a gate column is configured, and loads the gate', function () {
    Schema::create('al_gated', function ($t) {
        $t->string('id');
        $t->string('type');
        $t->float('gate')->nullable();
        $t->primary('id');
    });
    Schema::create('al_gated_edges', function ($t) {
        $t->id();
        $t->string('src');
        $t->string('dst');
    });

    // A star: leaves point at the hub (most central), hub gated to 0.
    DB::table('al_gated')->insert([
        ['id' => 'hub', 'type' => 'N', 'gate' => 0.0],
        ['id' => 'l1', 'type' => 'N', 'gate' => null],
        ['id' => 'l2', 'type' => 'N', 'gate' => null],
        ['id' => 'l3', 'type' => 'N', 'gate' => null],
    ]);
    DB::table('al_gated_edges')->insert([
        ['src' => 'l1', 'dst' => 'hub'],
        ['src' => 'l2', 'dst' => 'hub'],
        ['src' => 'l3', 'dst' => 'hub'],
    ]);

    $source = new AdjacencyListSource(resolver(), [
        'nodes' => ['table' => 'al_gated', 'key' => 'id', 'id' => 'id', 'type' => 'type'],
        'edges' => ['table' => 'al_gated_edges', 'from' => 'src', 'to' => 'dst', 'type' => 'LINKS'],
        'gate' => ['column' => 'gate'],
    ]);

    expect($source->providesGates())->toBeTrue();

    $driver = RelationalDriverFactory::make($source, 'gated');
    expect($driver)->toBeInstanceOf(GovernedRelationalDriver::class);

    // The gate-0 silencing law holds on the config-loaded gate.
    expect($driver->rank())->toHaveKey('hub')
        ->and($driver->governedRank())->not->toHaveKey('hub')
        ->and($driver->governedRank())->toHaveKey('l1');
});
