<?php

namespace Rushing\Graphine\Tests\Fixtures\Guard\Leaky;

// LEAK (planted): imports a Neo4j SERVER-internal namespace in-process instead of
// the MIT Bolt client. This is exactly the boundary violation the seam guard must
// catch — the file is never autoloaded/instantiated; the guard reads it as source.
use Neo4j\Server\Bootstrap;

class LeakyNeo4jDriver
{
    public function boot(): void
    {
        $_ = Bootstrap::class;
    }
}
