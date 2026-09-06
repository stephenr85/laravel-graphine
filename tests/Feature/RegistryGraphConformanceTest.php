<?php

namespace Rushing\Graphine\Tests\Feature;

use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Drivers\RelationalDriverFactory;
use Rushing\Graphine\Laravel\Sources\RegistryGraphSource;
use Rushing\Graphine\Testing\ConformsToGraphStore;
use Rushing\Graphine\Tests\TestCase;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\IsRegistry;

/**
 * `RegistryGraphSource` certifies through the driver family against the SHIPPED conformance
 * kit, the same way `AdjacencyListSource` does. The source points at an EMPTY registry so the
 * kit seeds its own fixtures through putNode/putEdge into the hydrated spine — what is being
 * proved here is that a registry-backed source integrates with the family and passes the same
 * spine laws, not that the kit's fixtures are registry-shaped.
 */
class RegistryGraphConformanceTest extends TestCase
{
    use ConformsToGraphStore;

    protected function createDriver(): GraphStore
    {
        $registry = new BasicRegistry(new IsRegistry(
            root: 'conformance',
            description: 'string',
        ));

        return RelationalDriverFactory::make(new RegistryGraphSource($registry), 'registry');
    }
}
