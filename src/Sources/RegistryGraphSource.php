<?php

namespace Rushing\Graphine\Laravel\Sources;

use RuntimeException;
use Rushing\Graphine\Contracts\GraphSource;
use Rushing\Graphine\Dto\Edge;
use Rushing\Graphine\Dto\Node;
use Rushing\Graphine\Dto\NodeId;
use Rushing\Popcorn\Registries\BranchKey;
use Rushing\Popcorn\Registries\Key;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryKey;

/**
 * THE REGISTRY KEYSPACE AS A GRAPH — a `popcorn` {@see Registry} backed onto the
 * {@see GraphSource} seam, so the dotted keyspace can be traversed by the relational
 * driver family instead of only enumerated.
 *
 * Optional bridge, sited here because it needs BOTH packages and `laravel-graphine`
 * already requires `laravel-popcorn` (registry-kernel ticket 10 D2). Nothing is added to
 * `php-popcorn`, nothing to `php-graphine`, and no package edge is created — the kernel
 * does not learn that graphs exist.
 *
 * ## The backing, as ticket 10 D9 defined it
 *
 * - **entries → {@see nodes()}** — one node per live, visible key.
 * - **dotted parent→child → {@see edges()}** — the key tree, which ticket 05 established is
 *   real *"for enumeration, routing and display"* even though resolution is flat.
 * - **{@see providesGates()} is false** — spine-only. Gates are host-asserted governance
 *   scalars; a registry asserts none, so {@see \Rushing\Graphine\Drivers\RelationalDriverFactory}
 *   keeps selecting the ungoverned member of the family and the capability stays honest by type.
 *
 * ## Identity is the SEGMENT JOIN, never the rendering
 *
 * `NodeIdContract` is a string, and a {@see RegistryKey}'s `__toString()` is the owner's
 * PRESENTATION, never its identity (ticket 05 as amended by ticket 11). So the node id is
 * built by joining `segments()`, and the owner's rendering rides in the `display` property —
 * the same `{segments, display}` split ticket 16's TS-parity constraint 4 already requires of
 * any port. A foreign key type whose segments themselves contain a `.` would collide two
 * distinct addresses onto one node; that is refused loudly rather than merged silently,
 * because a graph that quietly welds two keyspaces together is worse than one that will not build.
 *
 * ## Branch nodes exist here that the registry itself refuses to address
 *
 * `GraphSource::nodes()` must yield *"nodes that appear only as an edge endpoint"*, so every
 * derived branch address becomes a real node with type `Branch`. That is a genuine asymmetry,
 * not an implementation detail: the registry throws `AmbiguousRegistryMatch` when you probe a
 * branch (registry-kernel ticket 17 D1 → ticket 46), while the graph hands you a first-class
 * node for the same address. Read a `Branch` node as an ADDRESS, not as an entry — resolving
 * one through the registry will still throw.
 *
 * ## The zero-segment root is not representable
 *
 * {@see RegistryIndex}'s own self-hosting entry sits at {@see Key::root()}, which has no string
 * spelling at all (`routeTo('')` throws — ticket 16 D5). A `NodeId` is a string, so that one
 * entry is SKIPPED rather than given an invented address. An index graphed through this source
 * is therefore a forest of described roots, not a tree with the index at its apex.
 *
 * ## Filtering
 *
 * Reads go through `keys()`, which filters through the index's {@see Authorizer} if one is
 * installed — so a graph hydrated inside a request is an ACTOR'S graph and must not be cached
 * across actors (ticket 09 D5). `unfiltered: true` is the artisan/tooling path, and note it
 * escapes only one level (ticket 17 D6 → ticket 45): handing this source
 * `$index->unfiltered()` does NOT unfilter the entries of the registries inside it.
 */
class RegistryGraphSource implements GraphSource
{
    /** @var array{nodes: array<string, Node>, edges: list<Edge>}|null */
    private ?array $snapshot = null;

    public function __construct(
        private Registry $registry,
        private bool $unfiltered = false,
        private string $edgeType = 'PARENT_OF',
    ) {}

    /** @return iterable<Node> */
    public function nodes(): iterable
    {
        return array_values($this->snapshot()['nodes']);
    }

    /** @return iterable<Edge> */
    public function edges(): iterable
    {
        return $this->snapshot()['edges'];
    }

    /**
     * Always empty. A registry asserts no governance scalar — see {@see providesGates()}.
     *
     * @return iterable<array{0: NodeId, 1: float}>
     */
    public function gates(): iterable
    {
        return [];
    }

    public function providesGates(): bool
    {
        return false;
    }

    /**
     * Nodes and edges in one pass, memoised.
     *
     * The whole tree is derived from `keys()` plus `segments()`. {@see \Rushing\Popcorn\Registries\Nested}
     * is deliberately NOT used: `children()`/`descendants()` would hand back the same prefixes this
     * computes, so requiring it would narrow which registries can be graphed for no gain.
     *
     * @return array{nodes: array<string, Node>, edges: list<Edge>}
     */
    private function snapshot(): array
    {
        if ($this->snapshot !== null) {
            return $this->snapshot;
        }

        $registry = $this->unfiltered ? $this->registry->unfiltered() : $this->registry;

        /** @var array<string, Node> $nodes */
        $nodes = [];
        /** @var array<string, list<string>> $seenSegments */
        $seenSegments = [];
        /** @var list<Edge> $edges */
        $edges = [];
        /** @var array<string, true> $seenEdges */
        $seenEdges = [];

        foreach ($registry->keys() as $key) {
            $segments = $key->segments();

            // The index's own zero-segment root has no string spelling, so no NodeId.
            if ($segments === []) {
                continue;
            }

            $parentId = null;

            foreach ($segments as $depth => $_) {
                $prefix = array_slice($segments, 0, $depth + 1);
                $id = $this->identify($prefix, $seenSegments);
                $isLeaf = $depth === count($segments) - 1;

                // A prefix first seen as a branch is upgraded when its own entry shows up.
                if (! isset($nodes[$id]) || $isLeaf) {
                    $nodes[$id] = $this->node($id, $prefix, $isLeaf ? $key : new BranchKey($prefix), $isLeaf);
                }

                if ($parentId !== null && ! isset($seenEdges["{$parentId}\x00{$id}"])) {
                    $seenEdges["{$parentId}\x00{$id}"] = true;
                    $edges[] = new Edge(NodeId::of($parentId), NodeId::of($id), $this->edgeType, 1.0);
                }

                $parentId = $id;
            }
        }

        return $this->snapshot = ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * The node id for a segment list: the segment join, guarded against two distinct
     * addresses landing on one node.
     *
     * @param  list<string>  $segments
     * @param  array<string, list<string>>  $seenSegments
     */
    private function identify(array $segments, array &$seenSegments): string
    {
        $id = implode(Key::SEPARATOR, $segments);

        if (isset($seenSegments[$id]) && $seenSegments[$id] !== $segments) {
            throw new RuntimeException(
                "Two distinct registry addresses render as the node id `{$id}`: "
                    .json_encode($seenSegments[$id]).' and '.json_encode($segments).'. A foreign '
                    .'RegistryKey whose segments contain the `.` separator cannot be graphed, because '
                    .'NodeId is a string and the join is no longer reversible. Give the registry a key '
                    .'type whose segments are separator-free, or graph it on its own.'
            );
        }

        $seenSegments[$id] = $segments;

        return $id;
    }

    /**
     * @param  list<string>  $segments
     */
    private function node(string $id, array $segments, RegistryKey $key, bool $isLeaf): Node
    {
        return new Node(NodeId::of($id), $isLeaf ? 'Entry' : 'Branch', [
            'segments' => $segments,
            'depth' => count($segments),
            // The owner's own rendering, kept beside identity and never used as it.
            'display' => (string) $key,
        ]);
    }
}
