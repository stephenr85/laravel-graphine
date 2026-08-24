<?php

namespace Rushing\Graphine\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\Graphine\Laravel\GraphineServiceProvider;
use Rushing\Popcorn\Laravel\PopcornServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * `PopcornServiceProvider` is listed explicitly and must stay listed. Requiring
     * `rushing/laravel-popcorn` is not booting it — testbench does not auto-discover package providers,
     * and `RegistryIndex` is auto-resolvable, so without this line `make(RegistryIndex::class)` hands
     * back a FRESH index per call and anything described into it is written to an object nobody reads:
     * a green suite over an empty index (registry-kernel ticket 27 D3).
     */
    protected function getPackageProviders($app): array
    {
        return [PopcornServiceProvider::class, GraphineServiceProvider::class];
    }

    /**
     * The relational source family reads through illuminate/database,
     * so DB-backed tests need a connection. An in-memory sqlite default keeps the
     * suite self-contained; tests that don't touch the DB are unaffected.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }
}
