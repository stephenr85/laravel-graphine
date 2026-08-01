<?php

namespace Rushing\Graphine\Contracts;

use Rushing\Graphine\Dto\Path;

/**
 * The interface every traversal / shortest-path result satisfies (role 2). Typed on
 * the compute contract so a consumer can return its own walk representation behind
 * the contract rather than only the shipped {@see Path}.
 */
interface PathContract
{
    /** @return list<NodeIdContract> ordered node walk, source first */
    public function nodes(): array;

    /** Summed edge weight along the walk. */
    public function cost(): float;

    /** True if a cycle was detected on this walk. */
    public function cyclic(): bool;

    /** Edges walked = node count minus one (never negative). */
    public function length(): int;
}
