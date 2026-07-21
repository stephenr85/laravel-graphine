<?php

declare(strict_types=1);

namespace Rushing\Graphine\Sources;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Rushing\Graphine\Contracts\GraphSource;
use Rushing\Graphine\Dto\Edge;
use Rushing\Graphine\Dto\Node;
use Rushing\Graphine\Dto\NodeId;

/**
 * THE CONFIG-DRIVEN ADJACENCY-LIST SOURCE (ADR-0102) — graph ANY Eloquent table
 * with zero bespoke code.
 *
 * An adjacency list is a SOURCE SHAPE, not a driver: a relational encoding where
 * an edge is an explicit `(from, to)`. This source covers BOTH relational forms
 * behind one config array, and the {@see RelationalDriverFactory} turns it into a
 * working graph:
 *
 *   - EDGE-TABLE mode: a separate edge table (`from`/`to`[/`weight`] columns); a
 *     row per relationship. Nodes come from a named node table.
 *   - SELF-REFERENTIAL FK mode: a single node table with a `parent`-style column;
 *     each non-null FK becomes an edge (direction is a config flag). No edge table
 *     — this is what makes "graph any parent_id model" real.
 *
 * A `gate` config (a column on the node table yielding a `[0,1]` scalar) surfaces
 * through {@see gates()} and makes {@see providesGates()} true, so the factory
 * hands back the GOVERNED driver — capability stays honest by type.
 *
 * EDGE ENDPOINTS reference the node table's KEY column (config `nodes.key`,
 * default the primary key); the graph NodeId is the config `nodes.id` column
 * (default the key). An edge whose endpoint is not among the loaded nodes is
 * SKIPPED — this is how a scoped snapshot (e.g. one circuit) drops edges pointing
 * outside it. No Splicewire vocabulary lives here (ADR-0100 §5) — it reads the
 * consumer's own tables, inside the caller's connection/tenant context.
 *
 * CONFIG SHAPE (edge-table mode):
 *   [
 *     'nodes' => [
 *       'table' => 'circuit_nodes',
 *       'key'   => 'id',      // column edges reference (default primary key)
 *       'id'    => 'ref',     // the graph NodeId column (default = key)
 *       'type'  => 'type',    // Node type column (default literal 'Node')
 *       'properties' => ['label'],   // columns folded into Node->properties
 *       'scope' => ['circuit_id' => $id],   // optional where filter (=/in)
 *     ],
 *     'edges' => [
 *       'table'  => 'circuit_edges',
 *       'from'   => 'source_node_id',
 *       'to'     => 'target_node_id',
 *       'weight' => null,      // column name, or null → default weight 1.0
 *       'type'   => 'FLOWS_TO',
 *       'bidirectional' => false,   // true → a directed edge each way (undirected consumer)
 *       'scope'  => ['circuit_id' => $id],
 *     ],
 *   ]
 *
 * CONFIG SHAPE (self-referential FK mode) — replace `edges` with `parent`:
 *   'parent' => [
 *     'column'    => 'parent_id',
 *     'direction' => 'child_to_parent',  // or 'parent_to_child'
 *     'type'      => 'CHILD_OF',
 *     'weight'    => null,
 *   ]
 *
 * OPTIONAL gate (either mode):
 *   'gate' => ['column' => 'asserted_weight']   // on the node table
 */
final class AdjacencyListSource implements GraphSource
{
    /**
     * @param  array<string,mixed>  $config
     * @param  string|null  $connection  connection name; null = default (the tenant connection)
     */
    public function __construct(
        private readonly ConnectionResolverInterface $resolver,
        private readonly array $config,
        private readonly ?string $connection = null,
    ) {}

    /** @return iterable<Node> */
    public function nodes(): iterable
    {
        $cfg = $this->config['nodes'];
        $typeCol = $cfg['type'] ?? null;
        $propertyCols = $cfg['properties'] ?? [];

        foreach ($this->nodeQuery()->get() as $row) {
            $row = (array) $row;

            $properties = [];
            foreach ($propertyCols as $col) {
                $properties[$col] = $row[$col] ?? null;
            }

            yield new Node(
                NodeId::of($this->graphId($row)),
                $typeCol !== null ? (string) ($row[$typeCol] ?? 'Node') : 'Node',
                $properties,
            );
        }
    }

    /** @return iterable<Edge> */
    public function edges(): iterable
    {
        return isset($this->config['parent'])
            ? $this->selfReferentialEdges()
            : $this->edgeTableEdges();
    }

    /** @return iterable<array{0: NodeId, 1: float}> */
    public function gates(): iterable
    {
        if (! $this->providesGates()) {
            return;
        }

        $gateCol = $this->config['gate']['column'];

        foreach ($this->nodeQuery()->get() as $row) {
            $row = (array) $row;
            if (! array_key_exists($gateCol, $row) || $row[$gateCol] === null) {
                continue;
            }
            yield [NodeId::of($this->graphId($row)), (float) $row[$gateCol]];
        }
    }

    public function providesGates(): bool
    {
        return isset($this->config['gate']['column']);
    }

    // --- edge encodings -------------------------------------------------------

    /** @return iterable<Edge> */
    private function edgeTableEdges(): iterable
    {
        $map = $this->nodeIdByKey();
        $cfg = $this->config['edges'];
        $fromCol = $cfg['from'];
        $toCol = $cfg['to'];
        $weightCol = $cfg['weight'] ?? null;
        $type = (string) ($cfg['type'] ?? 'LINKS');
        // Undirected relationship → a directed edge EACH WAY, same weight (the
        // faithful modelling of an undirected consumer's edges: graphine's Edge is
        // directed, so bidirectional emits both directions rather than inventing a
        // first-class undirected edge). numero's archetype_interactions is the case.
        $bidirectional = (bool) ($cfg['bidirectional'] ?? false);

        $query = $this->connection()->table($cfg['table']);
        $this->applyScope($query, $cfg['scope'] ?? []);

        foreach ($query->get() as $row) {
            $row = (array) $row;
            $fromKey = (string) $row[$fromCol];
            $toKey = (string) $row[$toCol];

            // An endpoint outside the loaded node set → edge outside the snapshot.
            if (! isset($map[$fromKey], $map[$toKey])) {
                continue;
            }

            $weight = $this->weightOf($row, $weightCol);

            yield new Edge(NodeId::of($map[$fromKey]), NodeId::of($map[$toKey]), $type, $weight);

            if ($bidirectional && $fromKey !== $toKey) {
                yield new Edge(NodeId::of($map[$toKey]), NodeId::of($map[$fromKey]), $type, $weight);
            }
        }
    }

    /** @return iterable<Edge> */
    private function selfReferentialEdges(): iterable
    {
        $map = $this->nodeIdByKey();
        $nodeCfg = $this->config['nodes'];
        $cfg = $this->config['parent'];
        $keyCol = $nodeCfg['key'] ?? $this->primaryKey();
        $parentCol = $cfg['column'];
        $weightCol = $cfg['weight'] ?? null;
        $type = (string) ($cfg['type'] ?? 'CHILD_OF');
        $parentToChild = ($cfg['direction'] ?? 'child_to_parent') === 'parent_to_child';

        foreach ($this->nodeQuery()->get() as $row) {
            $row = (array) $row;
            $parent = $row[$parentCol] ?? null;
            if ($parent === null) {
                continue;
            }

            $childKey = (string) $row[$keyCol];
            $parentKey = (string) $parent;
            if (! isset($map[$childKey], $map[$parentKey])) {
                continue;
            }

            [$from, $to] = $parentToChild
                ? [$map[$parentKey], $map[$childKey]]
                : [$map[$childKey], $map[$parentKey]];

            yield new Edge(NodeId::of($from), NodeId::of($to), $type, $this->weightOf($row, $weightCol));
        }
    }

    // --- helpers --------------------------------------------------------------

    /**
     * Map the node KEY column (what edges reference) → the graph NodeId column.
     *
     * @return array<string,string>
     */
    private function nodeIdByKey(): array
    {
        $keyCol = $this->config['nodes']['key'] ?? $this->primaryKey();

        $map = [];
        foreach ($this->nodeQuery()->get() as $row) {
            $row = (array) $row;
            $map[(string) $row[$keyCol]] = $this->graphId($row);
        }

        return $map;
    }

    private function nodeQuery(): Builder
    {
        $cfg = $this->config['nodes'];
        $query = $this->connection()->table($cfg['table']);
        $this->applyScope($query, $cfg['scope'] ?? []);

        return $query;
    }

    /**
     * The graph NodeId for a node row: the configured `id` column, FALLING BACK
     * to the `key` column when the id is null/empty. The fallback is what lets a
     * table with an optional handle column (e.g. a nullable `ref`) graph cleanly —
     * a node with no handle is still identified by its key.
     *
     * @param  array<string,mixed>  $row
     */
    private function graphId(array $row): string
    {
        $cfg = $this->config['nodes'];
        $keyCol = $cfg['key'] ?? $this->primaryKey();
        $idCol = $cfg['id'] ?? $keyCol;

        $value = $row[$idCol] ?? null;
        if ($value === null || $value === '') {
            $value = $row[$keyCol] ?? null;
        }

        return (string) $value;
    }

    private function primaryKey(): string
    {
        return $this->config['nodes']['key'] ?? 'id';
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function weightOf(array $row, ?string $weightCol): float
    {
        if ($weightCol === null) {
            return 1.0;
        }

        return (float) ($row[$weightCol] ?? 1.0);
    }

    /**
     * @param  array<string,mixed>  $scope
     */
    private function applyScope(Builder $query, array $scope): void
    {
        foreach ($scope as $column => $value) {
            is_array($value)
                ? $query->whereIn($column, $value)
                : $query->where($column, $value);
        }
    }

    private function connection(): ConnectionInterface
    {
        return $this->resolver->connection($this->connection);
    }
}
