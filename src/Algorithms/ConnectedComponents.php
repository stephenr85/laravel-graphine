<?php

namespace Rushing\Graphine\Algorithms;

/**
 * Connected components under UNDIRECTED (weakly-connected) reachability.
 *
 * The shared input convention across this namespace hands us directed
 * successors — `$adjacency[$u]` is the list of `v` with a directed edge
 * `u → v`. This algorithm ignores that direction: every edge is treated as
 * bidirectional, so `a → b` alone binds `a` and `b` into one component. Edge
 * endpoints not present in `$nodes` are dropped; every node in `$nodes` lands
 * in exactly one returned component.
 *
 * Determinism is part of the contract, not incidental:
 *   - members WITHIN each component are natural-string sorted (`strnatcmp`);
 *   - components are ordered by their smallest member id (same comparator).
 * The same graph therefore always yields the same nested-list shape.
 *
 * Implementation is union-find (disjoint-set) with path compression.
 */
class ConnectedComponents
{
    /**
     * @param  array<string>  $nodes  node-id set (may arrive as a list)
     * @param  array<string, list<string>>  $adjacency  directed successors, treated as undirected
     * @return list<list<string>> components, members sorted, ordered by smallest member
     */
    public static function compute(array $nodes, array $adjacency): array
    {
        // Normalize the node set and seed each node as its own singleton set.
        $parent = [];
        foreach ($nodes as $node) {
            $parent[$node] = $node;
        }

        // Union both endpoints of every in-set edge, direction ignored.
        foreach ($adjacency as $u => $successors) {
            if (! array_key_exists($u, $parent)) {
                continue;
            }
            foreach ($successors as $v) {
                if (! array_key_exists($v, $parent)) {
                    continue;
                }
                self::union($parent, $u, $v);
            }
        }

        // Bucket nodes by their representative root.
        $buckets = [];
        foreach ($parent as $node => $_) {
            $root = self::find($parent, $node);
            $buckets[$root][] = $node;
        }

        // Sort members within each component (natural string order).
        $components = [];
        foreach ($buckets as $members) {
            usort($members, 'strnatcmp');
            $components[] = $members;
        }

        // Order components by their smallest member id.
        usort($components, fn (array $a, array $b) => strnatcmp($a[0], $b[0]));

        return $components;
    }

    /**
     * Resolve the set representative for a node, compressing the path.
     *
     * @param  array<string, string>  $parent
     */
    protected static function find(array &$parent, string $node): string
    {
        while ($parent[$node] !== $node) {
            $parent[$node] = $parent[$parent[$node]];
            $node = $parent[$node];
        }

        return $node;
    }

    /**
     * Merge the sets containing two nodes.
     *
     * @param  array<string, string>  $parent
     */
    protected static function union(array &$parent, string $a, string $b): void
    {
        $rootA = self::find($parent, $a);
        $rootB = self::find($parent, $b);

        if ($rootA !== $rootB) {
            $parent[$rootB] = $rootA;
        }
    }
}
