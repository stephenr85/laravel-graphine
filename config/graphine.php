<?php

/**
 * Graphine package config — laravel-popcorn-style driver selection.
 *
 * The package ships the in-memory reference driver (the default) and the generic
 * relational driver family; both work without app config. A consumer registers
 * any additional persistence driver via GraphStoreManager::extend() and points
 * `default` at it. This file therefore carries only the in-memory driver + the
 * extension seam, NOT app-store config.
 *
 * Worked examples of consumer-side drivers (a relational KG, AGE, Neo4j,
 * python-compute) live app-side under examples/app-drivers/, never in this
 * package.
 */
return [

    // The default is the in-memory reference driver; a consumer overrides this
    // once it has registered its own via extend().
    'default' => env('GRAPHINE_DRIVER', 'memory'),

    'drivers' => [

        'memory' => [
            // graphp/graph (MIT) — in-memory reference driver. Roles 1 + 2 + the
            // optional role-4 gating surface, so the conformance test-kit has a
            // working oracle. Suggest-only dep; the reference driver uses PHP arrays.
        ],

        // --- EXTENSION SEAM ---------------------------------------------------
        // Consumers add their own driver keys here and register a factory with
        // GraphStoreManager::extend('<key>', fn () => new MyDriver(...)). The
        // package ships no consumer persistence-driver config — see
        // examples/app-drivers/ for the app-side worked examples.
    ],
];
