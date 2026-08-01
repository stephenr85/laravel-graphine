<?php

namespace Rushing\Graphine\Contracts;

use Rushing\Graphine\Dto\Edge;

/**
 * The interface every directed-edge value type satisfies. Typed across the seam so
 * a consumer can supply its own edge representation behind the contract rather than
 * only the shipped {@see Edge}.
 *
 * `weight()` is the STRUCTURAL weight (role 1/2) — role-2 weighted compute consumes
 * it. It is emphatically NOT the governance gate; the two-weights separation holds.
 */
interface EdgeContract
{
    public function from(): NodeIdContract;

    public function to(): NodeIdContract;

    /** Relationship type, e.g. "PARENT_OF", "DEPENDS_ON". */
    public function type(): string;

    /** STRUCTURAL weight (role 1/2). NOT the governance gate. */
    public function weight(): float;

    /** @return array<string,mixed> */
    public function properties(): array;
}
