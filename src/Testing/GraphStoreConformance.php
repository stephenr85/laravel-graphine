<?php

declare(strict_types=1);

namespace Rushing\Graphine\Testing;

use PHPUnit\Framework\TestCase;

/**
 * THE CONFORMANCE TEST-KIT — the seam by which any driver self-certifies.
 *
 * Ticket 02 named this a first-class package component (the "portfolio flex"):
 * the package ships the contract + value types + one reference driver + this
 * kit. A consumer's driver — authored app-side over its own wheel — extends this
 * class, returns its driver from createDriver(), and inherits the whole
 * contract-conformance suite for free. The in-memory reference driver is the
 * oracle every real driver is measured against.
 *
 * The actual assertions live in the {@see ConformsToGraphStore} trait so that a
 * consumer whose test must extend a FRAMEWORK base class (a Laravel/tenancy
 * TestCase, say) can `use ConformsToGraphStore` instead of extending this — PHP
 * being single-inheritance. This base class is the convenience entry point for
 * consumers with no such constraint.
 *
 * Ships in src/ (autoloaded, not test-only) so consumers can extend it; it needs
 * a phpunit-providing host.
 */
abstract class GraphStoreConformance extends TestCase
{
    use ConformsToGraphStore;
}
