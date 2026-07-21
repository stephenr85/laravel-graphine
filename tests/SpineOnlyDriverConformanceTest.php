<?php

declare(strict_types=1);

namespace Rushing\Graphine\Tests;

use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Testing\GraphStoreConformance;
use Rushing\Graphine\Tests\Fixtures\SpineOnlyDriver;

/**
 * A mandatory-spine-only driver certifies against the SAME kit: it passes the
 * spine laws and SKIPS the optional (governance / query) sections — proving the
 * kit is capability-aware and the opt-in is by type.
 */
final class SpineOnlyDriverConformanceTest extends GraphStoreConformance
{
    protected function createDriver(): GraphStore
    {
        return new SpineOnlyDriver;
    }
}
