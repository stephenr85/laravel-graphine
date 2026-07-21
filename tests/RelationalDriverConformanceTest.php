<?php

declare(strict_types=1);

namespace Rushing\Graphine\Tests;

use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Drivers\RelationalDriver;
use Rushing\Graphine\Drivers\RelationalDriverFactory;
use Rushing\Graphine\Testing\GraphStoreConformance;
use Rushing\Graphine\Tests\Fixtures\ArrayGraphSource;

/**
 * The GENERIC relational driver (ADR-0102) certifies against the SAME shipped
 * kit, driven by an in-test {@see ArrayGraphSource}. A non-governed source
 * yields the plain {@see RelationalDriver}: it passes
 * the spine laws and SKIPS the optional governance / query sections — the
 * à-la-carte-by-type law, now upheld by the factory-picked family member.
 */
final class RelationalDriverConformanceTest extends GraphStoreConformance
{
    protected function createDriver(): GraphStore
    {
        // An EMPTY source — the conformance kit seeds its own fixtures through
        // putNode/putEdge, exactly as it does for the in-memory reference driver.
        return RelationalDriverFactory::make(new ArrayGraphSource, 'relational');
    }
}
