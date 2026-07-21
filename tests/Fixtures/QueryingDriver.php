<?php

declare(strict_types=1);

namespace Rushing\Graphine\Tests\Fixtures;

use InvalidArgumentException;
use Rushing\Graphine\Contracts\ComputeStore;
use Rushing\Graphine\Contracts\QueryableStore;
use Rushing\Graphine\Contracts\StructureStore;
use Rushing\Graphine\Drivers\AbstractDriver;
use Rushing\Graphine\Drivers\InMemoryDriver;
use Rushing\Graphine\Dto\Edge;
use Rushing\Graphine\Dto\Node;
use Rushing\Graphine\Dto\NodeId;
use Rushing\Graphine\Dto\Path;
use Rushing\Graphine\Dto\QueryResult;
use Rushing\Graphine\Enums\Capability;
use Rushing\Graphine\Enums\QueryFormat;
use Rushing\Graphine\Enums\TraversalDirection;

/**
 * A FULLER driver: the mandatory spine PLUS the optional `QueryableStore` (role 3).
 *
 * It exists to prove the à-la-carte contract's other half — a driver that opts
 * INTO role 3 by type and `speaks()` a native format. `query()` is a genuine
 * PASSTHROUGH: the statement + bindings go through opaque and come back as opaque
 * rows. The seam never re-abstracts the query language — that is the whole point
 * of "adopt the format, don't reinvent it" (ticket 04 point 5).
 */
final class QueryingDriver extends AbstractDriver implements ComputeStore, QueryableStore, StructureStore
{
    private readonly InMemoryDriver $spine;

    /** @var list<Capability> */
    protected array $capabilities = [
        Capability::Declare,
        Capability::Compute,
        Capability::QueryAtScale,
    ];

    /** @var list<QueryFormat> */
    protected array $formats = [QueryFormat::Native];

    public function __construct()
    {
        $this->spine = new InMemoryDriver;
    }

    public function name(): string
    {
        return 'querying';
    }

    public function putNode(Node $node): void
    {
        $this->spine->putNode($node);
    }

    public function putEdge(Edge $edge): void
    {
        $this->spine->putEdge($edge);
    }

    public function getNode(NodeId $id): ?Node
    {
        return $this->spine->getNode($id);
    }

    /** @return list<Node> */
    public function neighbours(
        NodeId $of,
        TraversalDirection $direction = TraversalDirection::Descendants,
        ?int $maxDepth = null,
    ): array {
        return $this->spine->neighbours($of, $direction, $maxDepth);
    }

    public function shortestPath(NodeId $from, NodeId $to): ?Path
    {
        return $this->spine->shortestPath($from, $to);
    }

    /** @return array<string,float> */
    public function rank(): array
    {
        return $this->spine->rank();
    }

    /** @return list<Path> */
    public function detectCycles(): array
    {
        return $this->spine->detectCycles();
    }

    /**
     * Native-query PASSTHROUGH. The statement is opaque to the seam — this
     * fixture echoes it back as an opaque row rather than parsing or re-wrapping
     * it, standing in for a real store handing raw driver rows straight through.
     *
     * @param  array<string,mixed>  $bindings
     */
    public function query(QueryFormat $format, string $statement, array $bindings = []): QueryResult
    {
        if (! in_array($format, $this->formats, strict: true)) {
            throw new InvalidArgumentException(
                "querying driver does not speak {$format->value} — check GraphStore::speaks()"
            );
        }

        // Opaque in, opaque out — no re-abstraction of the query language.
        return new QueryResult([
            ['statement' => $statement, 'bindings' => $bindings],
        ]);
    }
}
