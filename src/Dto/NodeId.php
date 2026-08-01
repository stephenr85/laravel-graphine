<?php

namespace Rushing\Graphine\Dto;

use Rushing\Graphine\Contracts\NodeIdContract;

/**
 * Opaque node identity. A value object rather than a bare string so the seam
 * can carry tenant scoping without leaking driver-specific key shapes
 * (Postgres UUID vs AGE graphid vs Neo4j elementId) across the contract.
 *
 * The shipped implementation of {@see NodeIdContract}. Role: cross-cutting (all
 * roles address nodes by this).
 */
class NodeId implements NodeIdContract
{
    public function __construct(
        public string $value,
    ) {}

    public static function of(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(NodeIdContract $other): bool
    {
        return $this->value === $other->value();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
