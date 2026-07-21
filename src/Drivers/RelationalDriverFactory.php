<?php

declare(strict_types=1);

namespace Rushing\Graphine\Drivers;

use Rushing\Graphine\Contracts\GraphSource;

/**
 * Picks the right member of the relational driver family from the SOURCE
 * (ADR-0102). A source that declares a gate source ({@see GraphSource::providesGates()})
 * yields the {@see GovernedRelationalDriver}; otherwise the plain
 * {@see RelationalDriver}.
 *
 * This is the whole reason the family is two classes rather than one runtime-
 * flagged one: the factory decides capability at WIRING time, so `instanceof
 * GovernedStore` narrows type-safely (ADR-0100 §3 / build ticket 04 à-la-carte
 * law) instead of every caller re-checking a nullable governance flag.
 *
 * A consumer registers a driver with one line:
 *   $manager->extend('circuits', fn () => RelationalDriverFactory::make($source, 'circuits'));
 */
final class RelationalDriverFactory
{
    public static function make(GraphSource $source, string $name = 'relational'): RelationalDriver
    {
        return $source->providesGates()
            ? new GovernedRelationalDriver($source, $name)
            : new RelationalDriver($source, $name);
    }
}
