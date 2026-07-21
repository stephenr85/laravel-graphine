# Publishing `rushing/laravel-graphine`

Build ticket 06. The package is release-ready: README, CI, a green conformance
kit + seam guard, and zero path-only dependencies. The steps below are **outward,
hard-to-reverse actions** deliberately left for the owner — they push code to a
public host, register a package, and rewrite the consuming app's lock.

## Done (in-repo, verified)

- **README.md** — Packagist-suitable positioning, the seam picture, how a consumer
  registers its own driver.
- **CI** — `.github/workflows/ci.yml` runs Pint + the full Pest suite
  (`InMemoryDriverConformanceTest`, the à-la-carte + governance conformance, and
  `SeamGuardTest`) on PHP 8.3/8.4 for every push/PR to `main`.
- **composer.json** — final: `illuminate/*` requires, reference-driver +
  seam-guard deps as `suggest`, `nikic/php-parser` + `orchestra/testbench` +
  `pestphp/pest` as `require-dev`, package discovery of `GraphineServiceProvider`.

## Owner steps (outward — run when ready to publish)

1. **Create the GitHub repo** and push:
   ```bash
   cd ~/Workspaces/laravel/packages/rushing/laravel-graphine
   git push -u origin main            # origin is already set to github.com/stephenr85/laravel-graphine
   ```
2. **Tag the first version** and push the tag:
   ```bash
   git tag -a v0.1.0 -m "graphine v0.1.0 — GraphStore seam, in-memory reference driver, conformance kit"
   git push origin v0.1.0
   ```
3. **Register on Packagist** — submit `https://github.com/stephenr85/laravel-graphine`
   at packagist.org (or `composer` via the API), enable the GitHub webhook so tags
   auto-sync. (The package is `"license": "proprietary"` — publish to a private
   Packagist / self-hosted Satis if it must stay closed.)
4. **Canonicalize the consuming app's lock** (`splicewire-app`) so graphine
   resolves git-first, not from the path overlay — use the `composer-canonicalize`
   workflow. In short: this package must be **pushed** first (its `dev-main` /
   `v0.1.0` resolves the *pushed* tip, so an unpushed edit silently vanishes from
   the lock), then in the app:
   ```bash
   # app composer.json already requires "rushing/laravel-graphine": "dev-main"
   # (or bump to "^0.1.0" once tagged+pushed+registered)
   # remove the path entry from composer.local.json (or drop the overlay), then:
   composer update rushing/laravel-graphine --no-interaction
   # verify the lock entry is a git ref, not a "path" source.
   ```
   Re-link the overlay afterwards only if co-dev continues.

## CI note

CI runs `composer update` (not `install`) since the package ships no committed
`composer.lock` (it is a library — `/composer.lock` is gitignored). The conformance
kit and seam guard both run under `vendor/bin/pest`.
