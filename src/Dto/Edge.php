<?php

declare(strict_types=1);

namespace Rushing\Graphine\Dto;

/**
 * A directed, optionally-weighted edge — PURE TOPOLOGY. Cross-cutting value type.
 *
 * `weight` is the STRUCTURAL weight (role 1/2): role-1 requires weighted
 * relationships and role-2 (Dijkstra / weighted centrality) consumes them. It
 * is emphatically NOT the governance gate — the two-weights separation from
 * numero's ADR-0011 (ticket 03): `Edge.weight` *computes*, the governance gate
 * *gates the computed result*. Fusing them is the drift the doctrine forbids,
 * so the gate never appears on `Edge` — it lives only behind `GovernedStore`.
 *
 * Roles: 1 (declare), 2 (weighted compute operand).
 */
final readonly class Edge
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
}
