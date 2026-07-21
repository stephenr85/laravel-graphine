<?php

declare(strict_types=1);

namespace Rushing\Graphine\Drivers;

use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Enums\Capability;
use Rushing\Graphine\Enums\QueryFormat;

/**
 * Shared plumbing for OOTB drivers. Each concrete driver declares the
 * capabilities and formats it actually reaches; `supports()`/`speaks()` are
 * driven off those declarations so the seam guard can trust them.
 */
abstract class AbstractDriver implements GraphStore
{
    /** @var list<Capability> */
    protected array $capabilities = [];

    /** @var list<QueryFormat> */
    protected array $formats = [];

    public function supports(Capability $capability): bool
    {
        return in_array($capability, $this->capabilities, strict: true);
    }

    /** @return list<QueryFormat> */
    public function speaks(): array
    {
        return $this->formats;
    }
}
