<?php

namespace Rushing\Graphine\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Laravel\GraphStoreManager;

/**
 * Ergonomic front door to the configured graph store(s).
 *
 * Method calls proxy through {@see GraphStoreManager} to the default driver
 * (a {@see GraphStore}); `driver()` selects a
 * specific store.
 *
 * @method static \Rushing\Graphine\Contracts\GraphStore driver(?string $driver = null)
 * @method static string getDefaultDriver()
 * @method static string name()
 * @method static bool supports(\Rushing\Graphine\Enums\Capability $capability)
 * @method static array speaks()
 * @method static \Rushing\Graphine\Contracts\QueryResultContract query(\Rushing\Graphine\Enums\QueryFormat $format, string $statement, array $bindings = [])
 *
 * @see GraphStoreManager
 */
class Graph extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GraphStoreManager::class;
    }
}
