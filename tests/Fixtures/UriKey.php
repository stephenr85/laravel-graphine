<?php

namespace Rushing\Graphine\Tests\Fixtures;

use Rushing\Popcorn\Registries\RegistryKey;

/**
 * A FOREIGN registry key — one a consumer owns, whose rendering is a URI and cannot be
 * rebuilt by parsing.
 *
 * Present because a green suite against `Key` proves nothing about the key seam: `Key` is the
 * one implementation that round-trips, and three defects of exactly that shape have already
 * shipped into the kernel behind a green suite (registry-kernel ticket 11, ticket 16 D6).
 * Anything that stores or renders a key needs a case like this before it is believed.
 *
 * Modelled on `schemastud/laravel-json-ns`'s live `NamespaceUriKey`.
 */
class UriKey implements RegistryKey
{
    /** @param  list<string>  $segments */
    public function __construct(
        private array $segments,
        private string $uri,
    ) {}

    /** @return list<string> */
    public function segments(): array
    {
        return $this->segments;
    }

    public function equals(RegistryKey $other): bool
    {
        return $this->segments === $other->segments();
    }

    public function __toString(): string
    {
        return $this->uri;
    }
}
