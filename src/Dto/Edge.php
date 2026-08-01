<?php

namespace Rushing\Graphine\Dto;

use Rushing\Graphine\Contracts\EdgeContract;
use Rushing\Graphine\Contracts\NodeIdContract;

/**
 * A directed, optionally-weighted edge — PURE TOPOLOGY. Cross-cutting value type;
 * the shipped implementation of {@see EdgeContract}.
 *
 * `weight` is the STRUCTURAL weight (role 1/2): role-1 requires weighted
 * relationships and role-2 (Dijkstra / weighted centrality) consumes them. It
 * is emphatically NOT the governance gate — the two-weights separation:
 * `Edge.weight` *computes*, the governance gate
 * *gates the computed result*. Fusing them is drift, so the gate never appears
 * on `Edge` — it lives only behind `GovernedStore`.
 *
 * Roles: 1 (declare), 2 (weighted compute operand).
 */
class Edge implements EdgeContract
{
    public function __construct(
        public NodeId $from,
        public NodeId $to,
        /** Relationship type, e.g. "PARENT_OF", "DEPENDS_ON", "GOVERNED_BY". */
        public string $type,
        /** STRUCTURAL weight (role 1/2). NOT the governance gate. */
        public float $weight = 1.0,
        public array $properties = [],
    ) {}

    public function from(): NodeIdContract
    {
        return $this->from;
    }

    public function to(): NodeIdContract
    {
        return $this->to;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function weight(): float
    {
        return $this->weight;
    }

    public function properties(): array
    {
        return $this->properties;
    }
}
