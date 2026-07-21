<?php

declare(strict_types=1);

namespace Rushing\Graphine\Enums;

/**
 * The roles from the research, expressed as capabilities a driver may or may
 * not fulfill. A caller interrogates a driver with
 * GraphStore::supports(Capability) rather than type-checking the concrete
 * class — the seam stays honest about disjoint role coverage (the whole reason
 * a monolithic god-interface was rejected).
 *
 * Mandatory spine = Declare (role 1) + Compute (role 2); every real graph
 * consumer exercises both. Governance (role 4) and QueryAtScale (role 3) are
 * optional and à-la-carte (ticket 02 decision 3).
 *
 * @see docs 01 — "Role coverage is disjoint across packages"
 */
enum Capability: string
{
    /** Role 1 — declare/persist node+edge topology (hierarchy, weights, recursion). */
    case Declare = 'declare';

    /** Role 2 — traverse & compute (BFS/DFS/shortest-path/rank/cycle detection). */
    case Compute = 'compute';

    /** Role 3 — persist & query at scale via a native/adopted query format (GQL/SPARQL). Optional. */
    case QueryAtScale = 'query_at_scale';

    /**
     * Role 4 — GOVERNANCE-AS-GATING (ticket 03): a host-asserted scalar gate
     * modulates role-2 compute output (`score = gate · computed`). Off the
     * structural spine; never bitemporal/locality (those had zero consumers and
     * were cut). Optional.
     */
    case Governance = 'governance';

    /** Role 2 (heavy) — compute crossing a real process/network boundary (Python/rustworkx). */
    case HeavyCompute = 'heavy_compute';

    /** Role 4 (reasoning) — OWL/rules reasoning, delegated behind a boundary. Advertise only when a backend is wired. */
    case Reasoning = 'reasoning';
}
