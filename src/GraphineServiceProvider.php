<?php

namespace Rushing\Graphine\Laravel;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Laravel\Facades\Graph;
use Rushing\Popcorn\Registries\RegistryIndex;

/**
 * Package service provider. Binds the Manager as a singleton and aliases the
 * `GraphStore` contract to the default driver, so consumers type-hint the
 * contract and let config decide the backend.
 *
 * Out of the box the default driver is the in-memory reference driver; a
 * consumer registers its own persistence driver via
 * GraphStoreManager::extend() (typically in its own service provider) and
 * repoints `graphine.default`. The package binds no persistence of its own.
 *
 * (Skeleton: paths/publishing are illustrative, not wired to a real package.)
 */
class GraphineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/graphine.php', 'graphine');

        $this->app->singleton(GraphStoreManager::class, fn ($app) => new GraphStoreManager($app));

        // Resolving the bare contract yields the configured default driver.
        $this->app->bind(GraphStore::class, fn ($app) => $app->make(GraphStoreManager::class)->driver());
    }

    public function boot(): void
    {
        if (class_exists(AliasLoader::class)) {
            AliasLoader::getInstance()->alias('Graph', Graph::class);
        }

        // Owners describe DOWN into the index from their own provider — the direction rule the index
        // inherits from `ManifestIndex`: popcorn never learns a consumer's name. Resolving the manager
        // here is deliberate and is what makes `keys()` honest: a deferred registrant is invisible to
        // enumeration (registry-kernel ticket 10 D5), so the manager exists from boot and consumers
        // register into it eagerly rather than inside `resolving()`.
        $this->app->make(RegistryIndex::class)->describe($this->app->make(GraphStoreManager::class));

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/graphine.php' => config_path('graphine.php'),
            ], 'graphine-config');
        }
    }
}
