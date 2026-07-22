<?php

declare(strict_types=1);

namespace Rushing\Graphine\Drivers;

use Rushing\Graphine\Contracts\GovernedStore;
use Rushing\Graphine\Contracts\GraphSource;
use Rushing\Graphine\Dto\NodeId;
use Rushing\Graphine\Enums\Capability;

/**
 * THE GOVERNED MEMBER of the relational driver family (ADR-0102).
 *
 * Everything {@see RelationalDriver} is (a spine hydrated once from a
 * {@see GraphSource}, all compute delegated), PLUS
 * role 4 — GOVERNANCE-AS-GATING. It loads host-asserted gates from the source's
 * `gates()` during hydration and answers `governedRank()` with the reference
 * spine's proven `score = gate · computed` law (`gate = 0` silences a node no
 * matter how central).
 *
 * The family is factory-selected: {@see RelationalDriverFactory} instantiates
 * THIS class iff the source declares a gate source, and the plain
 * {@see RelationalDriver} otherwise, so `instanceof GovernedStore` and
 * `supports(Capability::Governance)` stay in agreement (ADR-0100 §3 / build
 * ticket 04 — capability honest by type, not a runtime flag).
 *
 * Governance rides OFF the structural spine — a gate is never a Node/Edge field
 * (the two-weights separation, ADR-0011 / build ticket 03). `reason()` remains a
 * delegation signature only: the reference spine ships no in-process reasoner, so
 * this driver never advertises `Capability::Reasoning`. A consumer with a real
 * backend overrides `reason()` and adds the capability itself.
 */
class GovernedRelationalDriver extends RelationalDriver implements GovernedStore
{
    /** @var list<Capability> */
    protected array $capabilities = [
        Capability::Declare,     // role 1
        Capability::Compute,     // role 2
        Capability::Governance,  // role 4 — gating loaded from the source
        Capability::Enumerate,   // role 5 — inherited from RelationalDriver; the snapshot dumps free
        // NOT Capability::Reasoning — no in-process reasoner is linked.
    ];

    /** Load nodes + edges (base), then the source's governance gates. */
    protected function hydrate(): void
    {
        parent::hydrate();

        foreach ($this->source->gates() as [$node, $gate]) {
            $this->spine->assertGovernance($node, $gate);
        }
    }

    public function assertGovernance(NodeId $node, float $gate): void
    {
        $this->spine()->assertGovernance($node, $gate);
    }

    /** @return array<string,float> */
    public function governedRank(): array
    {
        return $this->spine()->governedRank();
    }

    public function classify(NodeId $node, string $classIri): void
    {
        $this->spine()->classify($node, $classIri);
    }

    /** @return list<string> */
    public function reason(NodeId $node): array
    {
        // The family ships the delegation SIGNATURE only — the reference spine
        // links no in-process reasoner (the seam guard forbids it). A consumer
        // with a real backend behind a process boundary overrides this and
        // advertises Capability::Reasoning.
        return $this->spine()->reason($node);
    }
}
