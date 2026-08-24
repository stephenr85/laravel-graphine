<?php

use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Drivers\InMemoryDriver;
use Rushing\Graphine\Laravel\GraphStoreManager;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\Key;
use Rushing\Popcorn\Registries\OnDuplicate;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryArity;
use Rushing\Popcorn\Registries\RegistryIndex;

it('is a Registry declaring root graph.stores', function () {
    $declaration = IsRegistry::of(GraphStoreManager::class);

    expect(app(GraphStoreManager::class))->toBeInstanceOf(Registry::class)
        ->and($declaration)->not->toBeNull()
        ->and($declaration->root)->toBe('graph.stores')
        ->and($declaration->arity)->toBe(RegistryArity::PickOne)
        ->and($declaration->onDuplicate)->toBe(OnDuplicate::Supersede);
});

it('still extends Manager, so the defaulted read and __call proxying are untouched', function () {
    expect(app(GraphStoreManager::class))->toBeInstanceOf(Illuminate\Support\Manager::class)
        ->and(app(GraphStoreManager::class)->driver())->toBeInstanceOf(InMemoryDriver::class);
});

it('reports the shipped driver in keys() with no reflection, keys absolute out', function () {
    expect(app(GraphStoreManager::class)->keys())
        ->toHaveCount(1)
        ->and((string) app(GraphStoreManager::class)->keys()[0])->toBe('graph.stores.memory');
});

it('records a driver registered through extend(), the door consumers already use', function () {
    app(GraphStoreManager::class)->extend('kg', fn () => new InMemoryDriver);

    $keys = array_map('strval', app(GraphStoreManager::class)->keys());

    expect($keys)->toBe(['graph.stores.memory', 'graph.stores.kg'])
        ->and(app(GraphStoreManager::class)->has('kg'))->toBeTrue()
        ->and(app(GraphStoreManager::class)->has(Key::parse('graph.stores.kg')))->toBeTrue()
        ->and(app(GraphStoreManager::class)->driver('kg'))->toBeInstanceOf(InMemoryDriver::class);
});

it('is honest about keys on a manager that has never been resolved through driver()', function () {
    // The point of ticket 10 D5: enumeration must not depend on anything having been constructed.
    $manager = app(GraphStoreManager::class);
    $manager->register('neo4j', fn () => new InMemoryDriver, by: 'test');

    expect(array_map('strval', $manager->keys()))->toContain('graph.stores.neo4j')
        ->and($manager->getDrivers())->toBe([]);
});

it('holds the FACTORY as the entry, so enumerating boots nothing', function () {
    $manager = app(GraphStoreManager::class);

    expect($manager->resolve('memory'))->toBeInstanceOf(Closure::class)
        ->and($manager->matches(Key::parse('graph.stores')))->toHaveCount(1)
        ->and($manager->driver('memory'))->toBeInstanceOf(GraphStore::class);
});

it('refuses a built store, because a factory is what extend() takes', function () {
    expect(fn () => app(GraphStoreManager::class)->register('eager', new InMemoryDriver))
        ->toThrow(InvalidArgumentException::class, 'must be a Closure');
});

it('refuses a key deeper than one segment under the root', function () {
    expect(fn () => app(GraphStoreManager::class)->register('kg.replica', fn () => new InMemoryDriver))
        ->toThrow(InvalidArgumentException::class, 'one segment under `graph.stores`');
});

it('is described into the shared RegistryIndex under graph.stores', function () {
    $index = app(RegistryIndex::class);

    expect($index)->toBe(app(RegistryIndex::class))
        ->and(array_map('strval', $index->keys()))->toContain('graph.stores')
        ->and($index->resolve('graph.stores'))->toBe(app(GraphStoreManager::class));
});

it('routes an entry key through the index to this registry', function () {
    app(GraphStoreManager::class)->extend('circuits', fn () => new InMemoryDriver);

    expect(app(RegistryIndex::class)->routeTo(Key::parse('graph.stores.circuits')))
        ->toBe(app(GraphStoreManager::class));
});
