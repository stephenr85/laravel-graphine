<?php

declare(strict_types=1);

namespace Rushing\Graphine\Enums;

/**
 * The query-format wheels a driver may speak (research-wheel role 3:
 * "adopt the format"). These are the ratified/standard interchange formats;
 * a driver advertises which it understands via GraphStore::supports().
 *
 * The format is the wheel — Graphine builds the wagon, never the language.
 *
 * @see docs 02-research-wheel-verdict.md — role 3 "ADOPT THE FORMAT"
 */
enum QueryFormat: string
{
    /**
     * Property-graph query. ISO/IEC 39075:2024 GQL — the first new ISO
     * database-language standard since SQL (1987). openCypher is explicitly
     * converging toward it, so we treat them as one wheel-family here.
     */
    case Gql = 'gql';

    /** openCypher dialect — accepted where a store predates GQL (AGE, Neo4j). */
    case OpenCypher = 'opencypher';

    /** RDF-graph query. SPARQL 1.1 (W3C) — the wheel for the semantic/RDF tributary. */
    case Sparql = 'sparql';

    /**
     * Graphine's own thin fluent traversal builder — NOT a wire format.
     * The lowest-common-denominator every driver must satisfy so callers can
     * stay format-agnostic for the common cases (roles 1 & 2).
     */
    case Native = 'native';
}
