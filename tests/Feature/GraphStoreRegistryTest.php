<?php

use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Drivers\InMemoryDriver;
use Rushing\Graphine\Laravel\GraphStoreManager;
use Rushing\Popcorn\Registries\Authorizer;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\Key;
use Rushing\Popcorn\Registries\OnKeyDuplicate;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryIndex;
use Rushing\Popcorn\Registries\RegistryKey;

it('is a Registry declaring root graph.stores', function () {
    $declaration = IsRegistry::of(GraphStoreManager::class);

    expect(app(GraphStoreManager::class))->toBeInstanceOf(Registry::class)
        ->and($declaration)->not->toBeNull()
        ->and($declaration->root)->toBe('graph.stores')
        ->and($declaration->onKeyDuplicate)->toBe(OnKeyDuplicate::Supersede);
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

/*
 * The authorizer reaches the composed store on both of the index's push edges (registry-kernel 20 D6),
 * in 74's acceptance form: a deny-all authorizer meets an ability-carrying driver, read back through the
 * manager's own public reads. The index pushes into the manager because it is `Gated`, and
 * `ForwardsToRegistryStore` hands the authorizer to the held store, where the filter runs.
 *
 * Each test routes through the index first or last on purpose: a baked root is pending until the index
 * hydrates it, and the push happens on hydration.
 */
it('is filtered by an authorizer the index installs after it holds the manager', function () {
    $manager = app(GraphStoreManager::class);
    $manager->register('closed', fn () => new InMemoryDriver, ability: 'graph.closed');

    expect(app(RegistryIndex::class)->resolve('graph.stores'))->toBe($manager)
        ->and($manager->has('closed'))->toBeTrue();

    app(RegistryIndex::class)->authorizeWith(new class implements Authorizer
    {
        public function allows(string $ability, RegistryKey $key): bool
        {
            return false;
        }
    });

    expect($manager->has('closed'))->toBeFalse()
        ->and($manager->has('memory'))->toBeTrue()
        ->and(array_map('strval', $manager->keys()))->toBe(['graph.stores.memory'])
        ->and($manager->unfiltered()->has('closed'))->toBeTrue();
});

it('is filtered by an authorizer the index installed before it held the manager', function () {
    app(RegistryIndex::class)->authorizeWith(new class implements Authorizer
    {
        public function allows(string $ability, RegistryKey $key): bool
        {
            return false;
        }
    });

    $manager = app(GraphStoreManager::class);
    $manager->register('closed', fn () => new InMemoryDriver, ability: 'graph.closed');

    expect(app(RegistryIndex::class)->resolve('graph.stores'))->toBe($manager)
        ->and($manager->has('closed'))->toBeFalse()
        ->and($manager->unfiltered()->has('closed'))->toBeTrue();
});
