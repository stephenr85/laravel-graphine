<?php

namespace Rushing\Graphine\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\Graphine\Laravel\GraphineServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [GraphineServiceProvider::class];
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
