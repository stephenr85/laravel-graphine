<?php

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
 * DEFAULT = 'memory'. The package ships the in-memory reference driver (the
 * default) AND a generic relational driver family — RelationalDriver /
 * GovernedRelationalDriver, factory-selected over a GraphSource — so a consumer
 * can graph any relational table out of the box. Specialized backends (AGE,
 * Neo4j, Python heavy-compute) stay the CONSUMER's, registered via extend() —
 * see examples/app-drivers/ for worked, app-side examples.
 *
 * @method GraphStore driver(?string $driver = null)
 */
class GraphStoreManager extends Manager
{
    public function getDefaultDriver(): string
    {
        // The default is the in-memory reference driver; a consumer overrides this
        // in config once it has registered its own (e.g. a relational driver).
        return $this->config->get('graphine.default', 'memory');
    }

    protected function createMemoryDriver(): GraphStore
    {
        return new InMemoryDriver;
    }
}
