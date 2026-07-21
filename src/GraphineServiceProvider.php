<?php

declare(strict_types=1);

namespace Rushing\Graphine;

use Illuminate\Support\ServiceProvider;
use Rushing\Graphine\Contracts\GraphStore;

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
final class GraphineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/graphine.php', 'graphine');

        $this->app->singleton(GraphStoreManager::class, fn ($app) => new GraphStoreManager($app));

        // Resolving the bare contract yields the configured default driver.
        $this->app->bind(GraphStore::class, fn ($app) => $app->make(GraphStoreManager::class)->driver());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/graphine.php' => config_path('graphine.php'),
            ], 'graphine-config');
        }
    }
}
