<?php

declare(strict_types=1);

namespace Rushing\Graphine\Drivers;

use Rushing\Graphine\Contracts\ComputeStore;
use Rushing\Graphine\Contracts\EnumerableStore;
use Rushing\Graphine\Contracts\GovernedStore;
use Rushing\Graphine\Contracts\StructureStore;
use Rushing\Graphine\Dto\Edge;
use Rushing\Graphine\Dto\Node;
use Rushing\Graphine\Dto\NodeId;
use Rushing\Graphine\Dto\Path;
use Rushing\Graphine\Enums\Capability;
use Rushing\Graphine\Enums\TraversalDirection;

/**
 * THE PACKAGE'S ONE AND ONLY DRIVER — the in-memory REFERENCE driver.
 *
 * Ticket 02 ruled the package ships the contract + value types/enums + ONE
 * in-memory reference driver + a conformance test-kit, and ZERO real
 * persistence drivers. This is that reference driver: it implements the full
 * mandatory spine (StructureStore + ComputeStore) AND the optional GovernedStore
 * so the conformance test-kit (Testing\GraphStoreConformance) has a working
 * oracle to certify every real consumer-side driver against.
 *
 * Conceptually backed by graphp/graph (MIT); the plain PHP arrays below stand
 * in for a real graphp\Graph instance (suggest-only dep — see composer.json).
 * It links no boundary-only engine, so it trivially passes the seam guard.
 *
 * Because it holds no persistence of its own, it CANNOT contradict ADR-0086's
 * "KG stays relational" — the app's relational KG driver is authored app-side
 * (see examples/app-drivers/RelationalKgDriver), never here.
 *
 * @see docs 01 §A — graphp/graph → in-memory driver
 */
final class InMemoryDriver extends AbstractDriver implements ComputeStore, EnumerableStore, GovernedStore, StructureStore
{
    /** @var array<string,Node> */
    private array $nodes = [];

    /** @var list<Edge> */
    private array $edges = [];

    /** @var array<string,float> host-asserted governance gates, nodeId => [0,1] */
    private array $gates = [];

    /** @var array<string,string> host-asserted class IRIs, nodeId => classIri */
    private array $classes = [];

    /** @var list<Capability> */
    protected array $capabilities = [
        Capability::Declare,     // role 1
        Capability::Compute,     // role 2
        Capability::Governance,  // role 4 — gating is REAL in-memory
        Capability::Enumerate,   // role 5 — the snapshot IS the store, so the dump is free
        // NOT Capability::Reasoning — reference driver performs no inference.
    ];

    public function name(): string
    {
        return 'memory';
    }

    // --- StructureStore (role 1) -------------------------------------------

    public function putNode(Node $node): void
    {
        $this->nodes[$node->id->value] = $node;
    }

    public function putEdge(Edge $edge): void
    {
        $this->edges[] = $edge;
    }

    public function getNode(NodeId $id): ?Node
    {
        return $this->nodes[$id->value] ?? null;
    }

    // --- EnumerableStore (role 5) — dump the whole snapshot ----------------

    /** @return list<Node> */
    public function nodes(): array
    {
        return array_values($this->nodes);
    }

    /** @return list<Edge> */
    public function edges(): array
    {
        return $this->edges;
    }

    /** @return list<Node> */
    public function neighbours(
        NodeId $of,
        TraversalDirection $direction = TraversalDirection::Descendants,
        ?int $maxDepth = null,
    ): array {
        // Recursive adjacency read — the transitive ancestors/descendants set,
        // mirroring staudenmeir/laravel-adjacency-list's recursive relations
        // (and a WITH RECURSIVE CTE). BFS bounded by $maxDepth; the visited set
        // makes it cycle-safe, so $maxDepth = null means "all reachable".
        $visited = [$of->value => true];
        $out = [];
        /** @var list<array{NodeId,int}> $frontier */
        $frontier = [[$of, 0]];

        while ($frontier !== []) {
            [$current, $depth] = array_shift($frontier);
            if ($maxDepth !== null && $depth >= $maxDepth) {
                continue;
            }
            foreach ($this->step($current, $direction) as $next) {
                if (isset($visited[$next->value])) {
                    continue;
                }
                $visited[$next->value] = true;
                if ($node = $this->getNode($next)) {
                    $out[] = $node;
                }
                $frontier[] = [$next, $depth + 1];
            }
        }

        return $out;
    }

    /**
     * One directed step from a node, honouring traversal direction.
     *
     * @return list<NodeId>
     */
    private function step(NodeId $from, TraversalDirection $direction): array
    {
        $next = [];
        foreach ($this->edges as $edge) {
            if ($direction !== TraversalDirection::Ancestors && $edge->from->equals($from)) {
                $next[] = $edge->to;   // descend along the edge direction
            }
            if ($direction !== TraversalDirection::Descendants && $edge->to->equals($from)) {
                $next[] = $edge->from;  // ascend against the edge direction
            }
        }

        return $next;
    }

    // --- ComputeStore (role 2) ---------------------------------------------

    public function shortestPath(NodeId $from, NodeId $to): ?Path
    {
        // Dijkstra over the directed, weighted edge list. Real behaviour: a
        // graphp-backed driver would delegate to its Dijkstra; the arrays here
        // implement the same shortest-weighted-path law the test-kit asserts.
        if ($this->getNode($from) === null || $this->getNode($to) === null) {
            return null;
        }

        /** @var array<string,float> $dist */
        $dist = [$from->value => 0.0];
        /** @var array<string,?string> $prev */
        $prev = [$from->value => null];
        /** @var array<string,true> $settled */
        $settled = [];

        while (true) {
            // Pick the unsettled node with the smallest tentative distance.
            $u = null;
            $best = INF;
            foreach ($dist as $id => $d) {
                if (! isset($settled[$id]) && $d < $best) {
                    $best = $d;
                    // PHP coerces a numeric-string array key to int; node ids are
                    // strings on NodeId, so normalise before it flows into NodeId::of().
                    $u = (string) $id;
                }
            }
            if ($u === null) {
                break; // no reachable unsettled node left
            }
            if ($u === $to->value) {
                break; // target settled — done
            }
            $settled[$u] = true;

            foreach ($this->edges as $edge) {
                if (! $edge->from->equals(NodeId::of($u))) {
                    continue;
                }
                $v = $edge->to->value;
                $alt = $dist[$u] + max(0.0, $edge->weight);
                if (! isset($dist[$v]) || $alt < $dist[$v]) {
                    $dist[$v] = $alt;
                    $prev[$v] = $u;
                }
            }
        }

        if (! isset($dist[$to->value])) {
            return null; // unreachable
        }

        // Reconstruct the node walk, source first.
        $walk = [];
        for ($at = $to->value; $at !== null; $at = $prev[$at] ?? null) {
            array_unshift($walk, NodeId::of($at));
        }

        return new Path(nodes: $walk, cost: $dist[$to->value]);
    }

    /** @return array<string,float> */
    public function rank(): array
    {
        // Weighted PageRank over the topology. Real behaviour, deterministic:
        // rank flows along out-edges proportional to weight; dangling nodes
        // redistribute uniformly. This is the compute the governance gate then
        // modulates (role 4), never fused with it.
        $ids = array_keys($this->nodes);
        $n = count($ids);
        if ($n === 0) {
            return [];
        }

        $damping = 0.85;
        $base = (1.0 - $damping) / $n;

        /** @var array<string,float> $rank */
        $rank = array_fill_keys($ids, 1.0 / $n);

        // Precompute weighted out-adjacency once.
        /** @var array<string,array<string,float>> $out */
        $out = array_fill_keys($ids, []);
        /** @var array<string,float> $outSum */
        $outSum = array_fill_keys($ids, 0.0);
        foreach ($this->edges as $edge) {
            $f = $edge->from->value;
            $t = $edge->to->value;
            if (! isset($rank[$f]) || ! isset($rank[$t])) {
                continue; // edge referencing an unknown node
            }
            $w = max(0.0, $edge->weight);
            $out[$f][$t] = ($out[$f][$t] ?? 0.0) + $w;
            $outSum[$f] += $w;
        }

        for ($iter = 0; $iter < 40; $iter++) {
            $next = array_fill_keys($ids, $base);
            $dangling = 0.0;
            foreach ($ids as $id) {
                if ($outSum[$id] <= 0.0) {
                    $dangling += $rank[$id];

                    continue;
                }
                foreach ($out[$id] as $t => $w) {
                    $next[$t] += $damping * $rank[$id] * ($w / $outSum[$id]);
                }
            }
            // Dangling mass spreads uniformly.
            if ($dangling > 0.0) {
                $share = $damping * $dangling / $n;
                foreach ($ids as $id) {
                    $next[$id] += $share;
                }
            }
            $rank = $next;
        }

        return $rank;
    }

    /** @return list<Path> */
    public function detectCycles(): array
    {
        // Colour-marked DFS: a back-edge to a node on the current stack is a
        // cycle. Returns one Path per distinct cycle found (nodes on the cycle,
        // cyclic = true). Real behaviour over the directed edge list.
        $ids = array_keys($this->nodes);
        /** @var array<string,int> $colour 0=white 1=grey 2=black */
        $colour = array_fill_keys($ids, 0);
        /** @var list<Path> $cycles */
        $cycles = [];
        /** @var array<string,true> $seenCycle */
        $seenCycle = [];

        $visit = function (int|string $u, array $stack) use (&$visit, &$colour, &$cycles, &$seenCycle): void {
            // A numeric-string node id arrives as an int array key; normalise so the
            // stack, colour lookups and NodeId::of() all see the string form.
            $u = (string) $u;
            $colour[$u] = 1;
            $stack[] = $u;
            foreach ($this->edges as $edge) {
                if (! $edge->from->equals(NodeId::of($u))) {
                    continue;
                }
                $v = $edge->to->value;
                if (! isset($colour[$v])) {
                    continue;
                }
                if ($colour[$v] === 1) {
                    // Back-edge → extract the cycle slice from the stack.
                    $pos = array_search($v, $stack, strict: true);
                    if ($pos !== false) {
                        $slice = array_slice($stack, $pos);
                        $key = implode('>', $slice);
                        if (! isset($seenCycle[$key])) {
                            $seenCycle[$key] = true;
                            $cycles[] = new Path(
                                nodes: array_map(static fn (string $id) => NodeId::of($id), $slice),
                                cost: 0.0,
                                cyclic: true,
                            );
                        }
                    }
                } elseif ($colour[$v] === 0) {
                    $visit($v, $stack);
                }
            }
            $colour[$u] = 2;
        };

        foreach ($ids as $id) {
            if ($colour[$id] === 0) {
                $visit($id, []);
            }
        }

        return $cycles;
    }

    // --- GovernedStore (role 4 — governance-as-gating) ----------------------

    public function assertGovernance(NodeId $node, float $gate): void
    {
        // Clamp to [0,1]; the gate is a host-side hint, meaning stays host-side.
        $this->gates[$node->value] = max(0.0, min(1.0, $gate));
    }

    /** @return array<string,float> */
    public function governedRank(): array
    {
        $governed = [];
        foreach ($this->rank() as $nodeId => $computed) {
            $gate = $this->gates[$nodeId] ?? 1.0;   // no assertion = pass-through
            $score = $gate * $computed;
            if ($score > 0.0) {                      // gate 0.0 → silent
                $governed[$nodeId] = $score;
            }
        }
        arsort($governed);

        return $governed;
    }

    public function classify(NodeId $node, string $classIri): void
    {
        $this->classes[$node->value] = $classIri;
    }

    /** @return list<string> */
    public function reason(NodeId $node): array
    {
        // The package ships the delegation SIGNATURE only — never an in-process
        // reasoner (the seam guard forbids linking one). Inference is always a
        // consumer-side backend behind a process boundary (owlready2 / external
        // triplestore / Postgres rules), and the backend is UNDECIDED (gate #4).
        // So the reference driver throws rather than fake inference; it does not
        // advertise Capability::Reasoning, and callers must gate on supports().
        throw new \RuntimeException(
            'InMemoryDriver::reason() ships the delegation signature only: '
            .'reasoning backend UNDECIDED (gate #4), and no reasoner may be linked in-process.'
        );
    }
}
