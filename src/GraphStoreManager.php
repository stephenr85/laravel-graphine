<?php

declare(strict_types=1);

namespace Rushing\Graphine;

use Illuminate\Support\Manager;
use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Drivers\InMemoryDriver;

/**
 * THE WAGON'S HUB — laravel-popcorn-style pluggable driver resolution.
 *
 * Extends Illuminate\Support\Manager: config picks the DEFAULT driver, and
 * consumers register their OWN drivers with `extend('kg', fn () => new
 * RelationalKgDriver(...))` at runtime — without touching this package. The app
 * resolves `GraphStore` from the container and never names a concrete driver;
 * that is the whole point of the seam.
 *
 * DEFAULT = 'memory' (ticket 04). The package ships EXACTLY ONE driver — the
 * in-memory reference driver. It owns no persistence, so it structurally cannot
 * contradict ADR-0086's "KG stays relational": there is no store here to push.
 * Real persistence drivers (relational KG, AGE, Neo4j, Python-compute,
 * governance-gating) are the CONSUMER's, registered via extend() — see
 * examples/app-drivers/ for worked, app-side examples.
 *
 * @method GraphStore driver(?string $driver = null)
 * @see docs 02 — "config picks the default driver; runtime extend() adds more"
 */
final class GraphStoreManager extends Manager
{
    public function getDefaultDriver(): string
    {
        // The package's only shipped driver is the in-memory reference driver.
        // A consumer overrides this in config once it has registered its own.
        return $this->config->get('graphine.default', 'memory');
    }

    protected function createMemoryDriver(): GraphStore
    {
        return new InMemoryDriver();
    }
}
