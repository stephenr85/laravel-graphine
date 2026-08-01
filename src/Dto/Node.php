<?php

namespace Rushing\Graphine\Dto;

use Rushing\Graphine\Contracts\NodeContract;
use Rushing\Graphine\Contracts\NodeIdContract;

/**
 * A graph node — PURE TOPOLOGY. Cross-cutting value type accepted/returned by
 * every driver; the shipped implementation of {@see NodeContract}.
 *
 * Role-4 governance does NOT ride here. A node carries only its
 * identity, its type/label, and a domain property bag. Whether a node is
 * "governed" is decided at the driver seam by type (`$driver instanceof
 * GovernedStore`), never by a nullable field on the node — the anti-drift rule,
 * generalized: the structural spine stays governance-
 * blind, and the governance gate is a host-side hint the engine never reads as
 * a schema key.
 *
 * Roles: 1 (declare), 2 (compute operand).
 */
class Node implements NodeContract
{
    public function __construct(
        public NodeId $id,
        /** Node type / label, e.g. "Entity", "Regulation", "Component". */
        public string $type,
        /** Arbitrary domain attributes. NO governance gate lives here. */
        public array $properties = [],
    ) {}

    public function id(): NodeIdContract
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function properties(): array
    {
        return $this->properties;
    }
}
