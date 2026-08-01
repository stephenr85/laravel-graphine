<?php

namespace Rushing\Graphine\Algorithms;

/**
 * Kahn's topological sort over the shared Algorithms input convention.
 *
 * Input:
 *   - `array<string> $nodes` — the node-id set (may arrive as a list).
 *   - `array<string, list<string>> $adjacency` — DIRECTED SUCCESSORS:
 *     `$adjacency[$u]` lists every `v` for which a directed edge `u → v`
 *     exists. A missing key means no out-edges. Any edge endpoint absent from
 *     `$nodes` is ignored.
 *
 * For an edge `u → v`, `u` precedes `v` in the output (sources first).
 *
 * Cycle semantics: nodes that never reach in-degree 0 — i.e. those sitting in
 * or behind a cycle — are reported in {@see TopologicalOrder::$cyclic} and are
 * excluded from `$sorted`. An empty `$cyclic` means the graph is a DAG.
 *
 * Determinism: when several nodes are simultaneously ready (in-degree 0), they
 * are emitted in the order they appear in the `$nodes` input array.
 */
class TopologicalSort
{
    /**
     * @param  array<string>  $nodes
     * @param  array<string, list<string>>  $adjacency
     */
    public static function kahn(array $nodes, array $adjacency): TopologicalOrder
    {
        // Preserve input order while de-duplicating the node set.
        $order = [];
        foreach ($nodes as $node) {
            $order[$node] = true;
        }
        $nodeSet = array_keys($order);

        // In-degree per node, counting only edges between known nodes.
        $inDegree = array_fill_keys($nodeSet, 0);
        $successors = [];
        foreach ($nodeSet as $u) {
            $successors[$u] = [];
            foreach ($adjacency[$u] ?? [] as $v) {
                if (! isset($inDegree[$v])) {
                    continue; // endpoint not in $nodes — ignore.
                }
                $successors[$u][] = $v;
                $inDegree[$v]++;
            }
        }

        // Ready queue seeded in input order for deterministic tie-breaks.
        $queue = [];
        foreach ($nodeSet as $node) {
            if ($inDegree[$node] === 0) {
                $queue[] = $node;
            }
        }

        $sorted = [];
        $head = 0;
        while ($head < count($queue)) {
            $u = $queue[$head++];
            $sorted[] = $u;
            foreach ($successors[$u] as $v) {
                if (--$inDegree[$v] === 0) {
                    $queue[] = $v;
                }
            }
        }

        // Anything still carrying in-degree is trapped in/behind a cycle.
        $cyclic = [];
        foreach ($nodeSet as $node) {
            if ($inDegree[$node] > 0) {
                $cyclic[] = $node;
            }
        }

        return new TopologicalOrder($sorted, $cyclic);
    }
}
