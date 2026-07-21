<?php

declare(strict_types=1);

namespace Rushing\Graphine\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\Graphine\GraphineServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [GraphineServiceProvider::class];
    }
}
