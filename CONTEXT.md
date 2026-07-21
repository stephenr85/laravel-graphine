# Graphine

A portfolio-grade, storage-agnostic **graph seam** for Laravel: a contract + value types + an in-memory
reference spine + a conformance kit, plus a generic *relational* driver family. It generalizes the shape of
a consumer's graph structures without owning the engine behind them (see the app's ADR-0100, ADR-0102).

## Language

**Graph source**:
The seam between *how graph structure is stored* and *how it is traversed*. A source yields nodes, edges,
and gates from some storage representation; a driver hydrates them into the spine and answers all reads
from there. The source is the only thing that varies per consumer — everything downstream is shared.
_Avoid_: loader, hydrator (those name the mechanism, not the seam), adapter.

**Adjacency list**:
One *source shape* — a relational encoding where an edge is an explicit `(from, to)` and structure is read
directly from rows. Two forms count: a **separate edge table** (a row per relationship) and a
**self-referential FK** (a `parent_id`-style column on the node table itself). It is a kind of graph
source, **not** the driver — "graph any model with the adjacency list" means *supply this source*, not
*use a different driver*. An **undirected** consumer (numero's `archetype_interactions`) rides the
edge-table form with `bidirectional: true` — graphine's `Edge` is directed, so an undirected row emits a
directed edge each way rather than inventing a first-class undirected edge.
_Avoid_: edge list (ambiguous with the file format), link table.

**Relational (snapshot) driver**:
The generic, storage-agnostic driver: it hydrates a graph source into the in-memory spine **once** and
delegates every read and compute to that snapshot. It is generic precisely because it is indifferent to the
source shape — an adjacency list and a triple store ride the same driver. Contrast the *reference* spine
(the in-memory driver used to certify and to back snapshots) and a *wire* driver (one that speaks a native
query language to an external engine, not a relational snapshot).
_Avoid_: "the adjacency driver" (conflates the driver with one source shape), backend driver.

**Triple store (as a source)**:
A non-adjacency graph source: an EAV/triple representation where an edge is a *typed triple* and node
properties are the other triples — the structure is *interpreted*, not stored as `(from, to)` rows. The
canonical proof that a generic driver is distinct from an adjacency-list one: it graphs a store that has no
edge rows at all.

**Governance gate**:
A host-asserted scalar in `[0, 1]` that rides *off* the structural spine (`score = gate · computed`;
`gate = 0` silences a node), never fused with an edge's structural weight. A source that yields gates
selects the governed member of the driver family; one that does not stays spine-only — the capability the
driver advertises stays honest by type.
_Avoid_: weight (that is the structural edge signal), permission, mask.
