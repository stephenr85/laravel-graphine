<?php

namespace Rushing\Graphine\Contracts;

use Rushing\Graphine\Dto\QueryResult;

/**
 * The interface every raw-query result satisfies (role 3). Typed on the queryable
 * contract so a consumer can return its own row-set representation behind the
 * contract rather than only the shipped {@see QueryResult}.
 *
 * Rows stay loosely-typed maps: GQL/openCypher/SPARQL each return their own shape
 * and the seam does not pretend to unify them beyond "rows".
 */
interface QueryResultContract
{
    /** @return list<array<string,mixed>> */
    public function rows(): array;

    /** @return array<string,mixed>|null the first row, or null when empty */
    public function first(): ?array;

    public function count(): int;
}
