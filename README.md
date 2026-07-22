# `rushing/laravel-graphine`

A pluggable Laravel **graph-substrate seam**: one `GraphStore` contract, four
role sub-contracts, a set of value types, **one in-memory reference driver**, and
a **conformance test-kit** any real driver certifies against. The package ships
**zero persistence drivers** — real persistence is always the consumer's adapter
over its own wheel.

> The format is the wheel; graphine builds the wagon. Adopt every graph library;
> build the seam that lets you swap them behind one contract.

## Install

```bash
composer require rushing/laravel-graphine
```

The service provider auto-registers (Laravel package discovery). Out of the box
the default driver is the in-memory reference driver.

## What the package ships (and what it deliberately does not)

| Package (OSS-clean, no host concepts) | The consumer's (never in the package) |
|---|---|
| `GraphStore` contract + 4 role sub-contracts | Every **real persistence driver** |
| Value types (`Node`, `Edge`, `NodeId`, `Path`, `QueryResult`) | A relational knowledge-graph driver |
| Enums (`Capability`, `QueryFormat`, `TraversalDirection`) | A governance-gating driver |
| **One in-memory reference driver** | AGE / Neo4j / heavy-compute backends |
| **A conformance test-kit** (`Testing\GraphStoreConformance`) | Each driver's storage, gate semantics, wire language |

## The seam in one picture

```
                 GraphStore  (contract — identity + capability introspection)
                      │   the WAGON: thin, Manager-driver pattern + drivers
   ┌──────────────────┼───────────────────────────────────────────────────────┐
   │  MANDATORY SPINE                  │  OPTIONAL, À-LA-CARTE                   │
   │  StructureStore(1) ComputeStore(2)│  QueryableStore(3)  GovernedStore(4)   │
   └──────────────────┼───────────────────────────────────────────────────────┘
                      │
  PACKAGE ships ONE driver:            CONSUMER authors its own:
  └── InMemoryDriver                   ├── a relational driver (roles 1/2)
        roles 1 + 2 + the role-4       ├── a governance-gating driver (role 4)
        gating surface, so the         ├── an AGE / Neo4j query driver (role 3)
        test-kit has an oracle         └── a heavy-compute driver over a boundary
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

## Registering your own driver

The app resolves the **contract**, never a concrete driver:

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

final class RelationalKgDriverConformanceTest extends GraphStoreConformance
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
├── Contracts/                  GraphStore + 4 role sub-contracts
├── Drivers/                    AbstractDriver + the ONE reference driver (InMemoryDriver)
├── Dto/                        Node, Edge (pure topology), NodeId, Path, QueryResult (readonly)
├── Enums/                      Capability, QueryFormat, TraversalDirection
└── Testing/                    GraphStoreConformance + SeamGuard (shipped test-kit)
tests/                          the reference driver certifies itself
```

## License

MIT. © Stephen Rushing. A reusable, unopinionated graph-substrate seam — free to build
on; the persistence drivers (and any composed engine above them) are the consumer's.
