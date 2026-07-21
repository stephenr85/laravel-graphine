<?php

declare(strict_types=1);

namespace Rushing\Graphine\Tests;

use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Drivers\InMemoryDriver;
use Rushing\Graphine\Testing\GraphStoreConformance;

/**
 * The reference driver certifies itself against the conformance test-kit — the
 * oracle proving the kit is real. Every consumer-side driver extends the same
 * GraphStoreConformance base the exact same way (see examples/app-drivers/).
 */
final class InMemoryDriverConformanceTest extends GraphStoreConformance
{
    protected function createDriver(): GraphStore
    {
        return new InMemoryDriver();
    }
}
