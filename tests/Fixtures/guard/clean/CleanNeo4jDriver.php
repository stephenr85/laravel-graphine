<?php

declare(strict_types=1);

namespace Rushing\Graphine\Tests\Fixtures\Guard\Clean;

// CLEAN: imports only the MIT Bolt CLIENT (Laudis\Neo4j\*), which talks to the
// Neo4j server over a socket. No server-internal namespace is linked in-process,
// so the seam guard must pass this file.
use Laudis\Neo4j\ClientBuilder;

final class CleanNeo4jDriver
{
    public function connect(): void
    {
        // A real driver would: ClientBuilder::create()->withDriver(...)->build();
        // referenced only to model a legitimate client import.
        $_ = ClientBuilder::class;
    }
}
