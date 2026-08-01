<?php

use Rushing\Graphine\Algorithms\ConnectedComponents;

it('splits two disjoint edges into two components', function () {
    $components = ConnectedComponents::compute(
        ['a', 'b', 'c', 'd'],
        ['a' => ['b'], 'c' => ['d']],
    );

    expect($components)->toBe([['a', 'b'], ['c', 'd']]);
});

it('treats a directed edge as undirected, binding both endpoints', function () {
    $components = ConnectedComponents::compute(
        ['a', 'b'],
        ['a' => ['b']],
    );

    expect($components)->toBe([['a', 'b']]);
});

it('returns an isolated node as its own singleton component', function () {
    $components = ConnectedComponents::compute(
        ['x'],
        [],
    );

    expect($components)->toBe([['x']]);
});

it('collapses a fully connected chain into one component', function () {
    $components = ConnectedComponents::compute(
        ['a', 'b', 'c', 'd'],
        ['a' => ['b'], 'b' => ['c'], 'c' => ['d']],
    );

    expect($components)->toBe([['a', 'b', 'c', 'd']]);
});

it('returns an empty list for an empty graph', function () {
    expect(ConnectedComponents::compute([], []))->toBe([]);
});

it('sorts members naturally and orders components by smallest member', function () {
    $components = ConnectedComponents::compute(
        ['node10', 'node2', 'z', 'a', 'm'],
        ['z' => ['a'], 'node10' => ['node2']],
    );

    // Component {a, z} sorts to [a, z]; its smallest member 'a' comes first.
    // Component {node2, node10} sorts naturally to [node2, node10].
    // 'm' is an isolated singleton. Ordering by smallest member: a < m < node2.
    expect($components)->toBe([
        ['a', 'z'],
        ['m'],
        ['node2', 'node10'],
    ]);
});

it('drops edge endpoints absent from the node set', function () {
    $components = ConnectedComponents::compute(
        ['a', 'b'],
        ['a' => ['ghost'], 'b' => []],
    );

    expect($components)->toBe([['a'], ['b']]);
});
