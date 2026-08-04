<?php

use Rushing\Graphine\Laravel\Facades\Graph;
use Rushing\Graphine\Laravel\GraphStoreManager;

it('resolves the GraphStoreManager singleton as its facade accessor', function () {
    expect(Graph::getFacadeRoot())
        ->toBeInstanceOf(GraphStoreManager::class)
        ->toBe(app(GraphStoreManager::class));
});

it('proxies driver resolution through the manager', function () {
    expect(Graph::driver())->toBe(app(GraphStoreManager::class)->driver());
});

it('is fakeable via a container swap', function () {
    $fake = new class(app()) extends GraphStoreManager
    {
        public function getDefaultDriver(): string
        {
            return 'faked-driver';
        }
    };

    Graph::swap($fake);

    expect(Graph::getFacadeRoot())->toBe($fake)
        ->and(Graph::getDefaultDriver())->toBe('faked-driver');
});
