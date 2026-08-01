# `rushing/laravel-graphine`

A pluggable Laravel **graph-substrate seam**: one `GraphStore` contract, four
role sub-contracts, a set of value types, a **reference in-memory driver**, a
**generic relational driver family** that graphs any Eloquent table out of the
box, and a **conformance test-kit** any driver certifies against. Specialized
persistence and compute backends — AGE, Neo4j, a Python heavy-compute service —
stay the consumer's adapter behind the same contract.

> The format is the wheel; graphine builds the wagon. Adopt every graph library;
> build the seam that lets you swap them behind one contract.

## Install

```bash
composer require rushing/laravel-graphine
```

The service provider auto-registers (Laravel package discovery). Out of the box
the default driver is the in-memory reference driver.

## What the package ships (and what it leaves to the consumer)

| Ships in the package (OSS-clean, no host concepts) | Stays the consumer's adapter |
|---|---|
| `GraphStore` contract + 4 role sub-contracts + the `GraphSource` seam | A specialized backend behind a network/process boundary |
| Value types (`Node`, `Edge`, `NodeId`, `Path`, `QueryResult`) | An AGE / Neo4j native-query driver (role 3) |
| Enums (`Capability`, `QueryFormat`, `TraversalDirection`) | A Python / rustworkx heavy-compute driver (role 2) |
| **Reference in-memory driver** (roles 1/2/4) | A bespoke storage driver with its own gate semantics |
| **Generic relational driver family** — `RelationalDriver` / `GovernedRelationalDriver`, factory-selected | |
| **Config-driven `AdjacencyListSource`** — graph any edge-table or `parent_id` table | |
| **Pure algorithm kernels** (`Algorithms\` — Kahn, Tarjan, components) | |
| **Conformance test-kit** (`Testing\GraphStoreConformance`) | |

## The seam in one picture

```
                 GraphStore  (contract — identity + capability introspection)
                      │   the WAGON: thin, Manager-driver pattern + drivers
   ┌──────────────────┼───────────────────────────────────────────────────────┐
   │  MANDATORY SPINE                  │  OPTIONAL, À-LA-CARTE                   │
   │  StructureStore(1) ComputeStore(2)│  QueryableStore(3)  GovernedStore(4)   │
   └──────────────────┼───────────────────────────────────────────────────────┘
                      │
  PACKAGE ships:                       CONSUMER authors its own:
  ├── InMemoryDriver (roles 1/2/4)     ├── an AGE / Neo4j query driver (role 3)
  ├── RelationalDriver family          ├── a heavy-compute driver (role 2)
  │     (roles 1/2, +4 governed)       │     over a process boundary
  └── AdjacencyListSource              └── a bespoke storage backend
        (graph any relational table)         with its own gate semantics
```

## À-la-carte: mandatory spine + optional roles

Role coverage across graph engines is **disjoint**, so the contract is
à-la-carte rather than one god-interface:

- `GraphStore` — the only universal contract: `name()`, `supports(Capability)`,
  `speaks(): QueryFormat[]`. Small on purpose — a marker + capability
  introspection, never a god-interface.
- **Mandatory spine:** `StructureStore` (role 1, declare topology) +
  `ComputeStore` (role 2, traverse/rank/paths). Every real graph consumer
  exercises both.
- **Optional, à-la-carte:** `GovernedStore` (role 4, governance-as-gating) +
  `QueryableStore` (role 3, native-query passthrough). Opt-in is by **type**
  (`$driver instanceof GovernedStore`), never a nullable field.
- Callers branch on `supports()`, never `instanceof` the concrete class, so a
  driver swap can widen or narrow coverage without breaking guarded call sites.

## Role 4 — governance-as-gating

Governance is a **host-asserted scalar gate** that modulates role-2 compute
output — `score = gate · computed`; `gate = 0` silences a node no matter how
central it computes. It is deliberately **off the structural spine**: `Node` /
`Edge` are pure topology. Structural `Edge.weight` (role 1/2) and the governance
gate (role 4) are two different roles — fusing them is drift. Valid/transaction
time and spatial locality are a **documented extension point**: a consumer that
needs a temporal/spatial stamp coins it inside its own driver.

## Role 3 — optional native-query passthrough

`QueryableStore` is **optional** and re-scoped to a native-query passthrough — a
statement in a `QueryFormat` the driver `speaks()`, opaque rows out. The seam
never re-abstracts the language; the adopted format wheels (GQL / openCypher /
SPARQL) are named, never wrapped. A pure-relational driver satisfies the
mandatory spine without speaking any wire language.

## Algorithms — pure kernels, usable without a store

`Rushing\Graphine\Algorithms\*` are stateless functions over plain arrays: a
node-id list plus a **successor adjacency** map (`$adjacency[$u]` lists every `v`
with an edge `u → v`). They touch no `Node`, `Edge`, or driver, so a caller that
already holds an adjacency map runs them directly — no store, no service
provider:

- `TopologicalSort::kahn()` → `TopologicalOrder{sorted, cyclic}`. Kahn's order,
  sources first. A cycle is **reported** in `cyclic`, never thrown — the caller
  chooses whether to warn, drop, or fail. `splicewire/laravel-beam-sync` uses it
  exactly this way to order a dependency cone and degrade on a cycle.
- `StronglyConnectedComponents::tarjan()` → each SCC as a member list. A singleton
  is not proof of acyclicity — a self-loop is a cyclic singleton; check the edge.
- `ConnectedComponents::compute()` → weakly-connected components (edges read
  undirected).

Role 2 surfaces the ordering on the seam as well: `ComputeStore::topologicalSort()`
runs the same kernel over a driver's own topology. So a store consumer reaches it
through the contract and a bare caller reaches the kernel directly — one
implementation, two entry points. These kernels are the only code here that
depends on no graphine abstraction; they live in the package because the
reference driver and its consumers both need them.

## Graph any relational table out of the box

You don't need a bespoke driver to graph a table you already have. Point the
config-driven `AdjacencyListSource` at it and let `RelationalDriverFactory` pick
the driver member:

```php
use Rushing\Graphine\Sources\AdjacencyListSource;
use Rushing\Graphine\Drivers\RelationalDriverFactory;

$source = new AdjacencyListSource($connectionResolver, [
    'nodes' => ['table' => 'circuit_nodes', 'key' => 'id', 'type' => 'type'],
    'edges' => ['table' => 'circuit_edges', 'from' => 'source_node_id', 'to' => 'target_node_id'],
]);

app(\Rushing\Graphine\GraphStoreManager::class)
    ->extend('circuits', fn () => RelationalDriverFactory::make($source, 'circuits'));
```

One config array covers two source shapes:

- **Edge-table** — a separate `(from, to[, weight])` table, one row per edge.
- **Self-referential FK** — swap `edges` for `parent` (`['column' => 'parent_id',
  'direction' => 'child_to_parent']`); each non-null FK becomes an edge. This is
  what makes "graph any `parent_id` model" real, no edge table needed.

The factory selects by **type**, not a runtime flag: a source that declares a
`gate` column (`providesGates()` true) yields the `GovernedRelationalDriver`
(role 4); otherwise the spine-only `RelationalDriver`. Either way the driver
hydrates the source into the in-memory spine **once** and answers every read and
compute from that bounded snapshot — a read-only consumer pays hydration once; a
consumer whose writes hit storage invalidates the snapshot so the next read
re-hydrates.

## Bring your own value types

Every value type the seam passes — nodes, edges, identities, paths, query results —
is an interface (`NodeContract`, `EdgeContract`, `NodeIdContract`, `PathContract`,
`QueryResultContract`) that the shipped `Dto\*` classes implement. The contracts read
state through methods (`$node->id()`, `$edge->weight()`), so a consumer can hand the
seam its own representation — e.g. a spatie/laravel-data `Data` object — without
adopting graphine's concrete DTOs. Reach for the shipped `Dto\*` classes unless you
have a reason not to; the interfaces are the extension point.

## Registering your own driver

For a backend the shipped drivers don't cover, the app resolves the **contract**,
never a concrete driver:

```php
public function __construct(private GraphStore $graph) {}   // default driver, from config

// In your service provider:
app(\Rushing\Graphine\GraphStoreManager::class)
    ->extend('kg', fn () => new \App\Graph\Drivers\RelationalKgDriver(/* … */));

// config/graphine.php:  'default' => env('GRAPHINE_DRIVER', 'kg'),
```

## Certifying a driver

Extend the shipped conformance kit and return your driver — it inherits the whole
contract-conformance suite. The kit is capability-aware: it runs the mandatory
spine on every driver and the role-4 gating law only on drivers that implement
`GovernedStore`.

```php
use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Testing\GraphStoreConformance;

class RelationalKgDriverConformanceTest extends GraphStoreConformance
{
    protected function createDriver(): GraphStore
    {
        return new \App\Graph\Drivers\RelationalKgDriver(/* … */);
    }
}
```

## Seam guard

A consumer that authors boundary-crossing drivers (a Neo4j server, an in-process
reasoner) should run a seam guard over its own `App\Graph\Drivers` — the package
ships the reusable check (`Rushing\Graphine\Testing\SeamGuard`) that fails loudly
if a driver imports a copyleft/proprietary **in-process** surface instead of
crossing the network/process boundary. The package's own in-memory driver links
nothing, so it always passes.

## Layout

```
src/
├── GraphStoreManager.php       Manager-driver hub — default 'memory' + extend()
├── GraphineServiceProvider.php
├── Algorithms/                 Pure kernels — Kahn topo-sort, Tarjan SCC, components (no store)
├── Contracts/                  GraphStore + role sub-contracts, the GraphSource seam, + value-type contracts (Node/Edge/…)
├── Drivers/                    InMemoryDriver + the relational family (RelationalDriver, governed, factory)
├── Dto/                        Node, Edge (pure topology), NodeId, Path, QueryResult
├── Enums/                      Capability, QueryFormat, TraversalDirection
├── Sources/                    AdjacencyListSource — graph any relational table by config
└── Testing/                    GraphStoreConformance + SeamGuard (shipped test-kit)
tests/                          the in-memory + relational drivers certify against the kit
```

## License

MIT. © Stephen Rushing. A reusable, unopinionated graph-substrate seam — free to build
on; the specialized/boundary-crossing persistence drivers (and any composed engine
above them) are the consumer's.
