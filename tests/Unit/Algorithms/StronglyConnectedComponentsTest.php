<?php

use Rushing\Graphine\Algorithms\StronglyConnectedComponents;

it('collapses two mutually reachable nodes into one component', function () {
    $components = StronglyConnectedComponents::tarjan(
        ['a', 'b'],
        ['a' => ['b'], 'b' => ['a']],
    );

    expect($components)->toBe([['a', 'b']]);
});

it('yields three singletons for a pure DAG', function () {
    $components = StronglyConnectedComponents::tarjan(
        ['a', 'b', 'c'],
        ['a' => ['b'], 'b' => ['c']],
    );

    expect($components)->toBe([['a'], ['b'], ['c']]);
});

it('separates a 3-cycle from its tail nodes', function () {
    // a → b → c → a  (cycle), c → d → e (tail)
    $components = StronglyConnectedComponents::tarjan(
        ['a', 'b', 'c', 'd', 'e'],
        ['a' => ['b'], 'b' => ['c'], 'c' => ['a', 'd'], 'd' => ['e']],
    );

    expect($components)->toBe([['a', 'b', 'c'], ['d'], ['e']]);
});

it('treats an isolated node as its own singleton', function () {
    $components = StronglyConnectedComponents::tarjan(
        ['a', 'b', 'lonely'],
        ['a' => ['b'], 'b' => ['a']],
    );

    expect($components)->toBe([['a', 'b'], ['lonely']]);
});

it('returns an empty list for an empty graph', function () {
    expect(StronglyConnectedComponents::tarjan([], []))->toBe([]);
});

it('collapses a self-loop to a singleton, indistinguishable by shape from an isolated node', function () {
    // Per the documented contract, a self-loop (a→a) is a CYCLIC singleton, yet its
    // return shape is identical to an isolated (acyclic) node — a caller that needs to
    // tell them apart must inspect the adjacency for the self-edge, not the component list.
    $selfLoop = StronglyConnectedComponents::tarjan(['a'], ['a' => ['a']]);
    $isolated = StronglyConnectedComponents::tarjan(['a'], []);

    expect($selfLoop)->toBe([['a']])
        ->and($isolated)->toBe([['a']])
        ->and($selfLoop)->toBe($isolated, 'cyclicity of a self-loop is not observable from the SCC list alone');
});

it('sorts members and orders components by smallest member id', function () {
    // Two SCCs discovered in a non-sorted order; check normalization.
    $components = StronglyConnectedComponents::tarjan(
        ['z', 'y', 'b', 'a'],
        ['z' => ['y'], 'y' => ['z'], 'b' => ['a'], 'a' => ['b']],
    );

    // members natural-sorted within, components ordered by smallest member.
    expect($components)->toBe([['a', 'b'], ['y', 'z']]);
});

it('ignores edge endpoints absent from the node set', function () {
    $components = StronglyConnectedComponents::tarjan(
        ['a', 'b'],
        ['a' => ['b', 'ghost'], 'b' => ['a']],
    );

    expect($components)->toBe([['a', 'b']]);
});
