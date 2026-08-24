<?php

namespace Rushing\Graphine\Laravel;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Manager;
use InvalidArgumentException;
use Rushing\Graphine\Contracts\GraphStore;
use Rushing\Graphine\Drivers\InMemoryDriver;
use Rushing\Popcorn\Registries\Authorizer;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\Gated;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\Key;
use Rushing\Popcorn\Registries\OnDuplicate;
use Rushing\Popcorn\Registries\Optionality;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryArity;
use Rushing\Popcorn\Registries\RegistryKey;

/**
 * THE WAGON'S HUB — laravel-popcorn-style pluggable driver resolution.
 *
 * Extends Illuminate\Support\Manager: config picks the DEFAULT driver, and
 * consumers register their OWN drivers with `extend('kg', fn () => new
 * RelationalKgDriver(...))` at runtime — without touching this package. The app
 * resolves `GraphStore` from the container and never names a concrete driver;
 * that is the whole point of the seam.
 *
 * DEFAULT = 'memory'. The package ships the in-memory reference driver (the
 * default) AND a generic relational driver family — RelationalDriver /
 * GovernedRelationalDriver, factory-selected over a GraphSource — so a consumer
 * can graph any relational table out of the box. Specialized backends (AGE,
 * Neo4j, Python heavy-compute) stay the CONSUMER's, registered via extend() —
 * see examples/app-drivers/ for worked, app-side examples.
 *
 * ## It is also a `Registry`, natively (registry-kernel ticket 10 D2/D3)
 *
 * This is the estate's foreign registry — the one that was already `extends Manager` before popcorn
 * existed, and so the one that tested whether the contract generalizes past the shapes it was designed
 * against. It does, and the cost is one held field: `Manager` keeps `__call` proxying and
 * `getDefaultDriver()`, {@see BasicRegistry} keeps the keyspace.
 *
 * `register()` records the key in its own registry **before** delegating to `extend()`. That is what
 * makes `keys()` honest with no reflection: `Manager::getDrivers()` returns *resolved instances* and
 * `$customCreators` is protected with no public accessor, so ticket 01's *registered ∪ reflected
 * `create*Driver`* needed to reach inside the parent. A registry that tracks its own registrations
 * needs none of it.
 *
 * **An entry is the FACTORY, not the store.** `Manager` owns construction and memoisation, so
 * `resolve('graph.stores.kg')` hands back the closure and `driver('kg')` hands back the `GraphStore` it
 * builds. That split is deliberate: it keeps `keys()` and `matches()` non-constructive, which is what
 * ticket 10 D5 requires — enumeration that booted every driver would boot every foreign manager in the
 * estate the moment ticket 17's Operator UX rendered its tree.
 *
 * **`driver(null)` stays off the `Registry` interface** (ticket 10 D4). The defaulted read is a concept
 * `Manager` has and the kernel does not, and it is graphine's own business; it is the single point of
 * friction a genuinely foreign registry produced against the contract, recorded here rather than lifted.
 *
 * @method GraphStore driver(?string $driver = null)
 */
#[IsRegistry(
    root: 'graph.stores',
    of: 'graph-store drivers, as the factories that build them',
    arity: RegistryArity::PickOne,
    entryType: Closure::class,
    onDuplicate: OnDuplicate::Supersede,
    optionality: Optionality::Optional,
    note: 'An entry is the factory, not the store: `resolve()` returns the closure and `driver($name)` '
        .'returns the GraphStore, so enumerating this registry boots nothing. `Supersede` because '
        .'`Manager::extend()` overwrites today and consumers rely on it to repoint a driver name.',
)]
class GraphStoreManager extends Manager implements Gated, Registry
{
    private BasicRegistry $stores;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->stores = new BasicRegistry($this->declaration());

        // The reference driver is an entry like any other. Registering it here rather than leaning on
        // `createMemoryDriver()` being found by convention is what lets `keys()` report it without
        // reflecting into the parent — and it means the shipped default and a consumer's driver are
        // read out of one place.
        $this->register('memory', fn (): GraphStore => $this->createMemoryDriver(), by: self::class);
    }

    public function getDefaultDriver(): string
    {
        // The default is the in-memory reference driver; a consumer overrides this
        // in config once it has registered its own (e.g. a relational driver).
        return $this->config->get('graphine.default', 'memory');
    }

    /**
     * Register a driver factory under `$key`, then hand the same closure to `Manager::extend()`.
     *
     * The key goes in relative and comes back absolute (ticket 20 D2), so `register('kg', …)` stores
     * `graph.stores.kg` while `extend()` — which speaks `Manager`'s bare driver names — is given `kg`.
     *
     * `$entry` must be a `Closure`: it is what `extend()` accepts, and passing a built `GraphStore`
     * would silently defeat the lazy construction the whole `Manager` story rests on. Refused loudly
     * rather than wrapped, on {@see BasicRegistry::for()}'s precedent — the kernel's registrars throw
     * rather than guess.
     */
    public function register(RegistryKey|string $key, mixed $entry, ?string $by = null, ?string $ability = null): static
    {
        if (! $entry instanceof Closure) {
            throw new InvalidArgumentException(sprintf(
                'A `%s` entry must be a Closure building a GraphStore; got %s for `%s`. `Manager::extend()` '
                .'takes a factory, and registering a built store would resolve every driver at boot.',
                self::class,
                get_debug_type($entry),
                (string) $key,
            ));
        }

        $this->stores->register($key, $entry, $by, $ability);

        parent::extend($this->driverName($key), $entry);

        return $this;
    }

    public function has(RegistryKey|string $key): bool
    {
        return $this->stores->has($key);
    }

    public function resolve(RegistryKey|string $key): mixed
    {
        return $this->stores->resolve($key);
    }

    public function tryResolve(RegistryKey|string $key): mixed
    {
        return $this->stores->tryResolve($key);
    }

    public function matches(RegistryKey|string $key): array
    {
        return $this->stores->matches($key);
    }

    public function keys(): array
    {
        return $this->stores->keys();
    }

    public function unfiltered(): Registry
    {
        return $this->stores->unfiltered();
    }

    /**
     * {@see Gated} — the index pushes the host's authorizer down here on both edges (ticket 20 D6).
     *
     * Implemented even though graphine declares no `ability` on any driver: an ungated entry
     * short-circuits before the authorizer is consulted, so this costs nothing today, and a registry
     * that cannot receive the authorizer is one a host can never close (ticket 09 D7).
     */
    public function authorizeWith(?Authorizer $authorizer): static
    {
        $this->stores->authorizeWith($authorizer);

        return $this;
    }

    /**
     * `Manager::extend()`, routed through {@see register()} so the keyspace never misses a write.
     *
     * The parent's own signature is the door most consumers already use — `Graph`'s README, the
     * examples and `splicewire-app`'s provider all say `extend('kg', …)`. Overriding it here rather
     * than asking every consumer to learn a second verb is what keeps `keys()` honest for drivers this
     * package never hears about.
     */
    public function extend($driver, Closure $callback): static
    {
        return $this->register($driver, $callback, by: 'extend');
    }

    /**
     * The nearest `#[IsRegistry]` at or above the runtime class.
     *
     * `BasicRegistry::for($this)` would read `static::class` and find nothing the moment a consumer
     * subclasses the manager — PHP does not inherit attributes, and swapping in an anonymous subclass
     * is a live test idiom in this package. So the walk is here, and `driverName()` reads the same
     * declaration rather than the constant.
     *
     * **This is a local hand-roll of a decision already taken elsewhere**: registry-kernel ticket 41
     * D11 settled *walk up, nearest wins* and handed the landing to ticket 42, which is what makes both
     * conformance audits see a bound subclass. Until 42 lands, this manager RESOLVES correctly under a
     * subclass while the audits still cannot see one — delete this method when `IsRegistry::of()` walks.
     */
    private function declaration(): IsRegistry
    {
        for ($class = static::class; $class !== false; $class = get_parent_class($class)) {
            $declaration = IsRegistry::of($class);

            if ($declaration !== null) {
                return $declaration;
            }
        }

        throw new InvalidArgumentException(sprintf(
            '`%s` and none of its parents carries an #[IsRegistry] declaration.',
            static::class,
        ));
    }

    /**
     * A registry key as the bare driver name `Manager` indexes by.
     *
     * Strips the declared root where the caller spelled an absolute key, and refuses anything deeper
     * than one segment: a graph store name is a `Manager` driver name, and `graph.stores.kg.replica`
     * would register a keyspace entry `driver()` could never reach.
     */
    private function driverName(RegistryKey|string $key): string
    {
        $segments = ($key instanceof RegistryKey ? $key : Key::parse($key))->segments();
        $root = $this->declaration()->rootKey()->segments();

        if (array_slice($segments, 0, count($root)) === $root) {
            $segments = array_slice($segments, count($root));
        }

        if (count($segments) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'A graph store key is one segment under `%s` — a `Manager` driver name. Got `%s`.',
                implode('.', $root),
                (string) $key,
            ));
        }

        return $segments[0];
    }

    protected function createMemoryDriver(): GraphStore
    {
        return new InMemoryDriver;
    }
}
