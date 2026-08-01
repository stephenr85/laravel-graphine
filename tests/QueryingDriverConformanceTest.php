<?php

namespace Rushing\Graphine\Tests;

use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Testing\GraphStoreConformance;
use Rushing\Graphine\Tests\Fixtures\QueryingDriver;

/**
 * A spine + QueryableStore driver certifies against the SAME kit: it passes the
 * spine laws AND the native-query passthrough section, while skipping governance.
 */
class QueryingDriverConformanceTest extends GraphStoreConformance
{
    protected function createDriver(): GraphStore
    {
        return new QueryingDriver;
    }
}
