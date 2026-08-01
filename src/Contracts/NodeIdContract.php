<?php

namespace Rushing\Graphine\Contracts;

use Rushing\Graphine\Dto\NodeId;
use Stringable;

/**
 * The interface every node-identity value type satisfies. Typed across the whole
 * seam so a consumer can supply its own identity representation — including a
 * spatie/laravel-data Data object — behind the contract, not just the shipped
 * {@see NodeId}.
 *
 * Identity is read through methods (not public properties) so any implementation
 * conforms, regardless of how it stores the value internally.
 */
interface NodeIdContract extends Stringable
{
    /** The opaque identity string. */
    public function value(): string;

    /** Value equality against another identity. */
    public function equals(NodeIdContract $other): bool;
}
