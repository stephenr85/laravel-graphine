<?php

declare(strict_types=1);

use Rushing\Graphine\Drivers\InMemoryDriver;
use Rushing\Graphine\Dto\Edge;
use Rushing\Graphine\Dto\Node;
use Rushing\Graphine\Dto\NodeId;
use Rushing\Graphine\Enums\Capability;

it('accepts a classification but ships only the reasoning delegation signature', function () {
    $driver = new InMemoryDriver;

    // classify() is accepted (the typed input a real reason() backend consumes)...
    $driver->classify(NodeId::of('n1'), 'https://example.org/onto#Regulation');

    // ...but the reference driver never advertises reasoning and never links a
    // reasoner in-process: reason() throws carrying trigger #4.
    expect($driver->supports(Capability::Reasoning))->toBeFalse();

    $driver->reason(NodeId::of('n1'));
})->throws(RuntimeException::class, 'gate #4');

it('advertises governance and gates compute without touching structural weight', function () {
    $driver = new InMemoryDriver;

    expect($driver->supports(Capability::Governance))->toBeTrue();

    // A gate clamps to [0,1] and modulates rank; it is not an Edge/Node field.
    $driver->putNode(new Node(NodeId::of('a'), 'Entity'));
    $driver->putNode(new Node(NodeId::of('b'), 'Entity'));
    $driver->putEdge(new Edge(NodeId::of('a'), NodeId::of('b'), 'LINKS', weight: 5.0));

    $driver->assertGovernance(NodeId::of('b'), 0.0);

    expect($driver->governedRank())->not->toHaveKey('b');
});
