<?php

declare(strict_types=1);

namespace Rushing\Graphine\Testing;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

/**
 * THE SEAM GUARD — a mechanical, AST-based boundary check (shipped test-kit).
 *
 * The seam is only real if it FAILS LOUDLY when bypassed: no driver may link a
 * store's copyleft/proprietary IN-PROCESS surface into the PHP artifact.
 * Copyleft/proprietary engines are permitted ONLY behind a network/process
 * boundary — a Bolt client calling a Neo4j server, a PHP→Python seam invoking a
 * reasoner as a subprocess. Shipping PHP that *calls* a GPL server over a socket
 * is not distributing that server's code; importing its in-process bindings is.
 *
 * This is not a substring sketch: it parses each file with nikic/php-parser and
 * inspects `use` imports, group-uses, and fully-qualified name references in the
 * AST. A `use Neo4j\Server\...;` is caught; a `Laudis\Neo4j\*` (MIT Bolt client)
 * import is not.
 *
 * The guard SHIPS in the package (autoloaded) so a consumer can run it against
 * its OWN driver directory:
 *
 *   $offenders = (new SeamGuard)->scan(app_path('Graph/Drivers'));
 *   $this->assertSame([], $offenders);
 *
 * It needs a nikic/php-parser-providing host (suggest-only dep), exactly as
 * GraphStoreConformance needs a phpunit-providing host.
 */
final class SeamGuard
{
    /**
     * The seam boundary, as concrete namespace prefixes. Each entry names an
     * IN-PROCESS surface that must instead be crossed over a network/process
     * boundary; the permitted client alternative is noted. This list IS the
     * boundary contract, not an illustration — extend it as new engines appear.
     *
     * @var list<string>
     */
    public const FORBIDDEN = [
        // Neo4j server internals. Permitted surface: the MIT Bolt client only
        // (Laudis\Neo4j\*), which talks to the server over a socket.
        'Neo4j\\Server',
        'GraphAware\\Neo4j\\Server',
        // In-process OWL / rules reasoners. Permitted surface: a reasoner behind
        // a process boundary (owlready2 subprocess, external triplestore, or a
        // Postgres rules backend) — never linked into the PHP artifact.
        'Owlready',
        'Jena',
        'Pellet',
        'Openllet',
    ];

    /** @var list<string> */
    private array $forbidden;

    /**
     * @param  list<string>|null  $forbidden  override the boundary list (defaults to FORBIDDEN)
     */
    public function __construct(?array $forbidden = null)
    {
        $this->forbidden = $forbidden ?? self::FORBIDDEN;
    }

    /**
     * Scan a driver file or directory (recursively) for in-process boundary
     * leaks. Returns a list of `"<relative-file> → <forbidden-namespace>"`
     * offenders; an empty list means the seam holds.
     *
     * @return list<string>
     */
    public function scan(string $path): array
    {
        $files = is_dir($path) ? $this->phpFilesIn($path) : [$path];
        $offenders = [];

        foreach ($files as $file) {
            foreach ($this->importsIn($file) as $imported) {
                foreach ($this->forbidden as $needle) {
                    if ($imported === $needle || str_starts_with($imported, $needle.'\\')) {
                        $offenders[] = basename($file).' → '.$imported;
                    }
                }
            }
        }

        return $offenders;
    }

    /**
     * Fully-qualified names imported or referenced in a file's AST — `use`
     * statements, group-uses, and any fully-qualified name reference.
     *
     * @return list<string>
     */
    private function importsIn(string $file): array
    {
        $code = @file_get_contents($file);
        if ($code === false) {
            return [];
        }

        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($code) ?? [];

        $visitor = new class extends NodeVisitorAbstract
        {
            /** @var list<string> */
            public array $names = [];

            public function enterNode(Node $node): null
            {
                if ($node instanceof Node\Stmt\Use_) {
                    foreach ($node->uses as $use) {
                        $this->names[] = $use->name->toString();
                    }
                } elseif ($node instanceof Node\Stmt\GroupUse) {
                    $prefix = $node->prefix->toString();
                    foreach ($node->uses as $use) {
                        $this->names[] = $prefix.'\\'.$use->name->toString();
                    }
                } elseif ($node instanceof Node\Name\FullyQualified) {
                    $this->names[] = $node->toString();
                }

                return null;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return array_values(array_unique($visitor->names));
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $dir): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );

        $files = [];
        foreach ($iterator as $entry) {
            /** @var \SplFileInfo $entry */
            if ($entry->isFile() && $entry->getExtension() === 'php') {
                $files[] = $entry->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
