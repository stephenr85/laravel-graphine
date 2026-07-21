<?php

declare(strict_types=1);

/**
 * Graphine package config — laravel-popcorn-style driver selection.
 *
 * The PACKAGE ships exactly one driver: the in-memory reference driver, which
 * is the default. Everything else is the CONSUMER's — a consumer registers its
 * own persistence driver via GraphStoreManager::extend() and points `default`
 * at it. This file therefore carries only the in-memory driver + the extension
 * seam, NOT app-store config (ticket 04 feed c).
 *
 * Worked examples of consumer-side drivers (relational KG, AGE, Neo4j,
 * python-compute, governance-gating) — and the adoption triggers/gates they
 * carry — live app-side under examples/app-drivers/, never in this package.
 */
return [

    // The package's only shipped driver is the in-memory reference driver.
    // A consumer overrides this once it has registered its own via extend().
    'default' => env('GRAPHINE_DRIVER', 'memory'),

    'drivers' => [

        'memory' => [
            // graphp/graph (MIT) — in-memory reference driver. Roles 1 + 2 + the
            // optional role-4 gating surface, so the conformance test-kit has a
            // working oracle. Suggest-only dep; the prototype uses PHP arrays.
        ],

        // --- EXTENSION SEAM ---------------------------------------------------
        // Consumers add their own driver keys here and register a factory with
        // GraphStoreManager::extend('<key>', fn () => new MyDriver(...)). The
        // package deliberately ships no persistence driver config — see
        // examples/app-drivers/ for the app-side worked examples and the
        // gate #1 (AGE tenancy) / gate #2 (Python-seam ops) adoption triggers.
    ],
];
