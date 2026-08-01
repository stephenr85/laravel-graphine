<?php

use Rushing\Graphine\Algorithms\TopologicalSort;

it('orders a linear chain a→b→c as a,b,c', function () {
    $order = TopologicalSort::kahn(
        ['a', 'b', 'c'],
        ['a' => ['b'], 'b' => ['c']],
    );

    expect($order->sorted)->toBe(['a', 'b', 'c'])
        ->and($order->cyclic)->toBe([])
        ->and($order->hasCycle())->toBeFalse();
});

it('orders a diamond with a first, d last, b and c between', function () {
    $order = TopologicalSort::kahn(
        ['a', 'b', 'c', 'd'],
        ['a' => ['b', 'c'], 'b' => ['d'], 'c' => ['d']],
    );

    expect($order->sorted[0])->toBe('a')
        ->and($order->sorted[3])->toBe('d')
        ->and(array_slice($order->sorted, 1, 2))->toContain('b')->toContain('c')
        ->and($order->hasCycle())->toBeFalse();
});

it('includes disconnected/isolated nodes in sorted', function () {
    $order = TopologicalSort::kahn(
        ['a', 'b', 'lonely'],
        ['a' => ['b']],
    );

    expect($order->sorted)->toContain('lonely')
        ->and($order->sorted)->toHaveCount(3)
        ->and($order->cyclic)->toBe([]);
});

it('puts a cycle a→b→a into cyclic and excludes it from sorted', function () {
    $order = TopologicalSort::kahn(
        ['a', 'b'],
        ['a' => ['b'], 'b' => ['a']],
    );

    expect($order->sorted)->toBe([])
        ->and($order->cyclic)->toContain('a')->toContain('b')
        ->and($order->hasCycle())->toBeTrue();
});

it('keeps DAG nodes ahead of a downstream cycle', function () {
    $order = TopologicalSort::kahn(
        ['root', 'a', 'b'],
        ['root' => ['a'], 'a' => ['b'], 'b' => ['a']],
    );

    expect($order->sorted)->toBe(['root'])
        ->and($order->cyclic)->toContain('a')->toContain('b')
        ->and($order->hasCycle())->toBeTrue();
});

it('returns empty results for an empty graph', function () {
    $order = TopologicalSort::kahn([], []);

    expect($order->sorted)->toBe([])
        ->and($order->cyclic)->toBe([])
        ->and($order->hasCycle())->toBeFalse();
});

it('breaks ties in nodes input order for independent sources', function () {
    $order = TopologicalSort::kahn(
        ['z', 'a'],
        [],
    );

    expect($order->sorted)->toBe(['z', 'a']);

    $reversed = TopologicalSort::kahn(
        ['a', 'z'],
        [],
    );

    expect($reversed->sorted)->toBe(['a', 'z']);
});

it('ignores edge endpoints absent from the node set', function () {
    $order = TopologicalSort::kahn(
        ['a', 'b'],
        ['a' => ['b', 'ghost'], 'ghost' => ['a']],
    );

    expect($order->sorted)->toBe(['a', 'b'])
        ->and($order->cyclic)->toBe([]);
});
