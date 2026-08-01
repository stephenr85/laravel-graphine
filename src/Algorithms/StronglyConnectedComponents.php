<?php

namespace Rushing\Graphine\Algorithms;

/**
 * Strongly-connected components via Tarjan's algorithm — ROLE 2 pure compute.
 *
 * Partitions a directed graph into its strongly-connected components (SCCs):
 * maximal node sets in which every node is reachable from every other node by
 * following directed edges. A node that participates in no cycle falls out as
 * its own singleton component.
 *
 * Shared input convention (every algorithm in this namespace speaks it):
 *   - array<string>               $nodes     the node-id set (may arrive as a list).
 *   - array<string, list<string>> $adjacency DIRECTED successors: $adjacency[$u]
 *     lists every $v with an edge u → v. A missing key means no out-edges. Any
 *     edge endpoint absent from $nodes is ignored.
 *
 * Determinism guarantees (tested):
 *   - members WITHIN each component are natural-string sorted.
 *   - components are ordered by their smallest member id (natural sort).
 * The raw discovery order of Tarjan is otherwise implementation-dependent, so
 * this normalization is what callers may rely on.
 *
 * Cyclicity semantics: a self-loop node (edge a → a) forms a cyclic SCC even
 * though it has a single member. Callers detecting cycles should treat
 * "component size > 1, OR a singleton whose node has a self-edge" as cyclic — a
 * bare singleton alone is NOT evidence of a cycle.
 *
 * Assumption: a straightforward recursive Tarjan is used — fine for the expected
 * graph sizes of this package's in-memory driver. Very deep graphs (chains
 * longer than PHP's stack tolerates) would need the iterative variant.
 */
class StronglyConnectedComponents
{
    /**
     * @param  array<string>  $nodes
     * @param  array<string, list<string>>  $adjacency
     * @return list<list<string>>
     */
    public static function tarjan(array $nodes, array $adjacency): array
    {
        $nodeSet = [];
        foreach ($nodes as $node) {
            $nodeSet[$node] = true;
        }

        $index = [];        // node => DFS discovery index
        $lowlink = [];      // node => lowest reachable index
        $onStack = [];      // node => bool (currently on the Tarjan stack)
        $stack = [];        // Tarjan's node stack
        $counter = 0;
        $components = [];

        $strongConnect = function (string $node) use (
            &$strongConnect, &$index, &$lowlink, &$onStack, &$stack, &$counter, &$components, $adjacency, $nodeSet
        ): void {
            $index[$node] = $counter;
            $lowlink[$node] = $counter;
            $counter++;
            $stack[] = $node;
            $onStack[$node] = true;

            foreach ($adjacency[$node] ?? [] as $successor) {
                if (! isset($nodeSet[$successor])) {
                    continue; // ignore dangling endpoints
                }

                if (! isset($index[$successor])) {
                    $strongConnect($successor);
                    $lowlink[$node] = min($lowlink[$node], $lowlink[$successor]);
                } elseif (! empty($onStack[$successor])) {
                    $lowlink[$node] = min($lowlink[$node], $index[$successor]);
                }
            }

            if ($lowlink[$node] === $index[$node]) {
                $component = [];
                do {
                    $member = array_pop($stack);
                    $onStack[$member] = false;
                    $component[] = $member;
                } while ($member !== $node);

                sort($component, SORT_NATURAL);
                $components[] = $component;
            }
        };

        foreach (array_keys($nodeSet) as $node) {
            if (! isset($index[$node])) {
                $strongConnect($node);
            }
        }

        usort($components, fn (array $a, array $b): int => strnatcmp($a[0], $b[0]));

        return $components;
    }
}
