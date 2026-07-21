<?php

declare(strict_types=1);

namespace Rushing\Graphine\Contracts;

use Rushing\Graphine\Dto\NodeId;

/**
 * ROLE 4 — GOVERNANCE-AS-GATING. Optional, à-la-carte sub-contract layered
 * OVER the mandatory StructureStore + ComputeStore spine (ticket 02 decision 3;
 * ticket 03 re-aim).
 *
 * The role-4 the census actually found (numero, ADR-0011) is NOT bitemporal
 * coherence — it is a host-asserted scalar that GATES role-2 compute output.
 * The prototype's `Coherence`/`Bitemporal`/`Locality` DTOs had zero consumers
 * and were cut; so was the `?Coherence` field on Node/Edge (it was the exact
 * anti-pattern numero's doctrine forbids — governance leaking into the
 * structural spine).
 *
 * Two load-bearing rules this contract encodes:
 *   1. The gate is a HOST-SIDE HINT. graphine guarantees only that it modulates
 *      governedRank(); its MEANING (what asserted_weight represents, how it's
 *      stored, the compute factors behind the computed score) stays consumer-
 *      side. It is never a Node/Edge schema field.
 *   2. Structural weight (Edge.weight, role 1/2) and the governance gate
 *      (role 4) are TWO DIFFERENT ROLES. One computes; the other gates the
 *      computed result. Fusing them is drift.
 *
 * Optionality is by TYPE, not by nullable field: a driver either implements
 * GovernedStore or it does not. `isGoverned()` becomes `$driver instanceof
 * GovernedStore`, decided at wiring time — not a per-node runtime null-check.
 * A consumer that never governs (e.g. Circuits) never implements this at all.
 *
 * The reference in-memory driver implements this so the conformance test-kit
 * can exercise the gating surface; real reasoning is always a consumer-side
 * backend behind a process boundary (never linked in-process — see the seam
 * guard). See research 04 for the full derivation.
 */
interface GovernedStore
{
    // --- Governance-as-gating (EVIDENCED: numero asserted_weight) --------------

    /**
     * Host asserts a governance gate on a node. Scalar in [0.0, 1.0]; 0.0
     * silences the node no matter how central it computes. The gate is a
     * host-side hint — graphine only guarantees it modulates governedRank().
     * Never a Node/Edge schema field.
     */
    public function assertGovernance(NodeId $node, float $gate): void;

    /**
     * Role-2 compute output MODULATED by the gate: `score = gate · computed`
     * (default gate 1.0 = pass-through when a node has no assertion). A node
     * gated at 0.0 drops out regardless of its structural centrality. Results
     * are ranked descending. This is the governed twin of ComputeStore::rank().
     *
     * @return array<string,float> nodeId => governed score, ranked desc
     */
    public function governedRank(): array;

    // --- Classification + reasoning (ASPIRATIONAL: kept, backend-deferred) ------

    /**
     * Optional: assert an ontology class IRI on a node — the typed input
     * reason() consumes. SEPARATE from the gate (quarantined so the evidenced
     * gate is never welded to the aspirational reasoning hook). A gating-only
     * consumer never calls this.
     */
    public function classify(NodeId $node, string $classIri): void;

    /**
     * Optional: delegate OWL/rules inference to a PLUGGABLE backend behind a
     * process boundary (owlready2 / external triplestore / Postgres rules).
     * graphine ships the delegation SIGNATURE, never an in-process reasoner —
     * advertise Capability::Reasoning only when a real backend is wired.
     *
     * @return list<string> inferred class IRIs
     */
    public function reason(NodeId $node): array;
}
