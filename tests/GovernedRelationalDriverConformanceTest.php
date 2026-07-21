<?php

declare(strict_types=1);

namespace Rushing\Graphine\Tests;

use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Drivers\GovernedRelationalDriver;
use Rushing\Graphine\Drivers\RelationalDriverFactory;
use Rushing\Graphine\Testing\GraphStoreConformance;
use Rushing\Graphine\Tests\Fixtures\ArrayGraphSource;

/**
 * The GOVERNED member of the family (ADR-0102) certifies against the SAME kit.
 * A source that DECLARES a gate source yields
 * {@see GovernedRelationalDriver}, so the kit runs the
 * governance-as-gating law (gate 0 silences a central node; gate 1 is
 * pass-through) in addition to the spine — proving the factory picks the role-4
 * member by type.
 */
final class GovernedRelationalDriverConformanceTest extends GraphStoreConformance
{
    protected function createDriver(): GraphStore
    {
        // An empty but GOVERNED source: providesGates() = true selects the
        // governed driver; the kit asserts governance itself via assertGovernance.
        return RelationalDriverFactory::make(
            new ArrayGraphSource(governed: true),
            'governed-relational',
        );
    }
}
