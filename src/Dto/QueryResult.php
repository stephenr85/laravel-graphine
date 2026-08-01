<?php

namespace Rushing\Graphine\Dto;

use Rushing\Graphine\Contracts\QueryResultContract;

/**
 * Opaque result of a raw graph-query-language statement (role 3). Rows are
 * left as loosely-typed maps because GQL/openCypher/SPARQL each return their
 * own row shape; the seam does not pretend to unify them beyond "rows".
 *
 * This is deliberately thin: role 3's contract is "adopt the FORMAT", so the
 * seam passes the query language through rather than re-abstracting it. The
 * shipped implementation of {@see QueryResultContract}.
 */
class QueryResult implements QueryResultContract
{
    public function __construct(
        /** @var list<array<string,mixed>> */
        public array $rows,
    ) {}

    public function rows(): array
    {
        return $this->rows;
    }

    public function first(): ?array
    {
        return $this->rows[0] ?? null;
    }

    public function count(): int
    {
        return count($this->rows);
    }
}
