<?php

declare(strict_types=1);

namespace Rushing\Graphine\Dto;

/**
 * A graph node — PURE TOPOLOGY. Cross-cutting value type accepted/returned by
 * every driver.
 *
 * Role-4 governance does NOT ride here (ticket 03). A node carries only its
 * identity, its type/label, and a domain property bag. Whether a node is
 * "governed" is decided at the driver seam by type (`$driver instanceof
 * GovernedStore`), never by a nullable field on the node — the anti-drift rule
 * from numero's ADR-0011, generalized: the structural spine stays governance-
 * blind, and the governance gate is a host-side hint the engine never reads as
 * a schema key.
 *
 * Roles: 1 (declare), 2 (compute operand).
 */
final readonly class Node
{
    public function __construct(
        public NodeId $id,
        /** Node type / label, e.g. "Entity", "Regulation", "Component". */
        public string $type,
        /** Arbitrary domain attributes. NO governance gate lives here. */
        public array $properties = [],
    ) {}
}
