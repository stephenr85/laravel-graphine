<?php

declare(strict_types=1);

namespace Rushing\Graphine\Contracts;

use Rushing\Graphine\Dto\QueryResult;
use Rushing\Graphine\Enums\QueryFormat;

/**
 * ROLE 3 — OPTIONAL native-query passthrough. NOT a mandated query language.
 *
 * Optional, à-la-carte sub-contract OFF the mandatory StructureStore +
 * ComputeStore spine (ticket 02 decision 3). The census caught the same defect
 * here that it caught in role-4-as-bitemporal: role-3-as-a-formal-query-
 * language has ZERO consumers — the KG never speaks SPARQL, Circuits uses Kahn,
 * numero uses PHP resolvers. So this contract is re-scoped to "a driver MAY
 * expose a native-query passthrough", NOT "graphine mandates GQL" (ticket 04
 * point 5). A pure-relational driver satisfies the spine without implementing
 * this at all.
 *
 * When a driver DOES implement it, the seam does NOT re-abstract the language —
 * that would re-invent the wheel it just adopted. It passes the statement + a
 * QueryFormat the driver `speaks()` through and hands back opaque rows. The
 * adopted format wheels are named, never wrapped:
 *   - GQL (ISO/IEC 39075:2024) / openCypher — a consumer's AGE or Neo4j driver.
 *   - SPARQL 1.1 (W3C) — a consumer's RDF-query driver.
 *   - QueryFormat::Native — a driver's own thin passthrough.
 * These drivers are the CONSUMER's, authored app-side (see examples/app-drivers/).
 *
 * @see docs 02 — role 3 "ADOPT THE FORMAT; ADOPT A STORE BEHIND A BOUNDARY"
 */
interface QueryableStore
{
    /**
     * @param  QueryFormat  $format  must be advertised by GraphStore::speaks()
     * @param  array<string,mixed>  $bindings  parameterised query bindings
     */
    public function query(QueryFormat $format, string $statement, array $bindings = []): QueryResult;
}
