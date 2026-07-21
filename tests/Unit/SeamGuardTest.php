<?php

declare(strict_types=1);

use Rushing\Graphine\Testing\SeamGuard;

it('passes a clean driver that imports only the MIT Bolt client', function () {
    $offenders = (new SeamGuard)->scan(__DIR__.'/../Fixtures/guard/clean');

    expect($offenders)->toBe([]);
});

it('fails loudly on a driver that leaks a Neo4j server-internal import in-process', function () {
    $offenders = (new SeamGuard)->scan(__DIR__.'/../Fixtures/guard/leaky');

    expect($offenders)->not->toBe([])
        ->and(implode("\n", $offenders))->toContain('Neo4j\Server\Bootstrap');
});

it('passes the package reference driver (it links no boundary engine)', function () {
    $offenders = (new SeamGuard)->scan(dirname(__DIR__, 2).'/src/Drivers');

    expect($offenders)->toBe([]);
});

it('detects a group-use leak, not just a plain use', function () {
    $tmp = sys_get_temp_dir().'/graphine_seamguard_groupuse.php';
    file_put_contents($tmp, <<<'PHP'
        <?php
        namespace App\Graph\Drivers;
        use Pellet\{Reasoner, Config};
        final class Leaky {}
        PHP);

    try {
        $offenders = (new SeamGuard)->scan($tmp);
        expect(implode("\n", $offenders))->toContain('Pellet\Reasoner');
    } finally {
        @unlink($tmp);
    }
});
