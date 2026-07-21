<?php

declare(strict_types=1);

namespace Rushing\Graphine\Tests\Fixtures;

use Rushing\Graphine\Contracts\GraphSource;
use Rushing\Graphine\Dto\Edge;
use Rushing\Graphine\Dto\Node;
use Rushing\Graphine\Dto\NodeId;

/**
 * An in-memory {@see GraphSource} for certifying the relational driver family
 * (ADR-0102) WITHOUT a database — the source's only job is to yield nodes/edges/
 * gates, so an array-backed one exercises the family's hydrate-once contract
 * exactly like a real relational source would.
 *
 * `providesGates()` is what the factory reads to pick the governed vs spine-only
 * member; this fixture takes it as a constructor flag so a single class can back
 * both conformance runs. An EMPTY source (the default) lets the shipped
 * conformance kit seed its own fixtures through `putNode`/`putEdge`, proving the
 * family passes the same laws the in-memory reference driver does.
 */
final class ArrayGraphSource implements GraphSource
{
    /**
     * @param  list<Node>  $nodes
     * @param  list<Edge>  $edges
     * @param  list<array{0: NodeId, 1: float}>  $gates
     */
    public function __construct(
        private readonly array $nodes = [],
        private readonly array $edges = [],
        private readonly array $gates = [],
        private readonly bool $governed = false,
    ) {}

    /** @return iterable<Node> */
    public function nodes(): iterable
    {
        yield from $this->nodes;
    }

    /** @return iterable<Edge> */
    public function edges(): iterable
    {
        yield from $this->edges;
    }

    /** @return iterable<array{0: NodeId, 1: float}> */
    public function gates(): iterable
    {
        yield from $this->gates;
    }

    public function providesGates(): bool
    {
        return $this->governed;
    }
}
