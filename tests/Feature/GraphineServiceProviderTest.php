<?php

declare(strict_types=1);

use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Drivers\InMemoryDriver;
use Rushing\Graphine\GraphStoreManager;

it('boots the provider and resolves the manager as a singleton', function () {
    $manager = app(GraphStoreManager::class);

    expect($manager)->toBeInstanceOf(GraphStoreManager::class)
        ->and(app(GraphStoreManager::class))->toBe($manager);
});

it('resolves the bare GraphStore contract to the configured default driver', function () {
    $store = app(GraphStore::class);

    expect($store)->toBeInstanceOf(GraphStore::class)
        ->and($store)->toBeInstanceOf(InMemoryDriver::class)
        ->and($store->name())->toBe('memory');
});

it('merges package config with the in-memory default', function () {
    expect(config('graphine.default'))->toBe('memory');
});

it('lets a consumer register its own driver and repoint the default', function () {
    config()->set('graphine.default', 'fake');

    app(GraphStoreManager::class)->extend('fake', fn () => new InMemoryDriver());

    // extend() adds a driver; resolving by name returns it.
    expect(app(GraphStoreManager::class)->driver('fake'))->toBeInstanceOf(InMemoryDriver::class);
});
