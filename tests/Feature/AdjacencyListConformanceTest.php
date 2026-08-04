<?php

namespace Rushing\Graphine\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Laravel\Drivers\RelationalDriverFactory;
use Rushing\Graphine\Laravel\Sources\AdjacencyListSource;
use Rushing\Graphine\Testing\ConformsToGraphStore;
use Rushing\Graphine\Tests\TestCase;

/**
 * AdjacencyListSource certifies through the driver family against the SHIPPED
 * conformance kit. The source points at EMPTY tables,
 * so the kit seeds its own fixtures through putNode/putEdge into the hydrated
 * spine — proving the config-driven source integrates with the family and passes
 * the same spine laws as every other driver, inside a real DB connection.
 */
class AdjacencyListConformanceTest extends TestCase
{
    use ConformsToGraphStore;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('conf_nodes', function ($t) {
            $t->string('id');
            $t->string('type');
            $t->primary('id');
        });
        Schema::create('conf_edges', function ($t) {
            $t->id();
            $t->string('src');
            $t->string('dst');
        });
    }

    protected function createDriver(): GraphStore
    {
        $source = new AdjacencyListSource(app('db'), [
            'nodes' => ['table' => 'conf_nodes', 'key' => 'id', 'id' => 'id', 'type' => 'type'],
            'edges' => ['table' => 'conf_edges', 'from' => 'src', 'to' => 'dst', 'type' => 'LINKS'],
        ]);

        return RelationalDriverFactory::make($source, 'adjacency');
    }
}
