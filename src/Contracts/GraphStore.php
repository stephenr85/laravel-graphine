<?php

declare(strict_types=1);

namespace Rushing\Graphine\Contracts;

use Rushing\Graphine\Enums\Capability;
use Rushing\Graphine\Enums\QueryFormat;

/**
 * THE SEAM — the wagon.
 *
 * `GraphStore` is the ONE coherent entry contract every driver implements. It
 * is deliberately SMALL: identity + capability introspection only. The four
 * roles hang off it as separate cohesive sub-contracts (StructureStore,
 * ComputeStore, QueryableStore, GovernedStore) that a driver composes as far
 * as it actually reaches.
 *
 * This is the explicit answer to "no monolithic god-interface": a driver that
 * only does role 2 implements GraphStore + ComputeStore and nothing else, and
 * `supports()` tells callers the truth. Role coverage across the ecosystem is
 * disjoint (docs 01), so the contract models that disjointness instead of
 * lying about it.
 *
 * Manager-driver pattern (Illuminate\Support\Manager), laravel-popcorn-style:
 * config picks the default driver; runtime extend() adds more; the app resolves
 * `GraphStore` from the container and never names a concrete driver.
 *
 * @see docs 02 — "the seam that reconciles them is itself the wagon"
 */
interface GraphStore
{
    /**
     * Stable driver name (matches the config key). Used by the seam guard test
     * and by tenancy scoping to key per-tenant graph namespaces.
     */
    public function name(): string;

    /**
     * Truthful capability introspection. Callers branch on this rather than
     * instanceof, so a driver swap can widen/narrow coverage without breaking
     * call sites that guarded correctly.
     */
    public function supports(Capability $capability): bool;

    /**
     * Which query-format wheel(s) this driver speaks (role 3). Empty for pure
     * in-memory/compute drivers that expose no wire query language.
     *
     * @return list<QueryFormat>
     */
    public function speaks(): array;
}
