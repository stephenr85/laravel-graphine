<?php

declare(strict_types=1);

namespace Rushing\Graphine\Dto;

use Stringable;

/**
 * Opaque node identity. A value object rather than a bare string so the seam
 * can carry tenant scoping without leaking driver-specific key shapes
 * (Postgres UUID vs AGE graphid vs Neo4j elementId) across the contract.
 *
 * Role: cross-cutting (all roles address nodes by this).
 */
final readonly class NodeId implements Stringable
{
    public function __construct(
        public string $value,
    ) {}

    public static function of(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
