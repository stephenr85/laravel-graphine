<?php

namespace Rushing\Graphine\Contracts;

use Rushing\Graphine\Dto\Node;

/**
 * The interface every graph-node value type satisfies. Typed across the seam so a
 * consumer can hand the driver its own node representation — a spatie/laravel-data
 * Data object, an Eloquent-backed shape — behind the contract rather than only the
 * shipped {@see Node}.
 *
 * Read through methods so any implementation conforms. PURE TOPOLOGY — no
 * governance gate rides here; that stays behind {@see GovernedStore}.
 */
interface NodeContract
{
    public function id(): NodeIdContract;

    /** Node type / label, e.g. "Entity", "Regulation", "Component". */
    public function type(): string;

    /** @return array<string,mixed> arbitrary domain attributes */
    public function properties(): array;
}
