> You are in **rushing/laravel-graphine** — the Laravel adapter over `rushing/php-graphine` (which owns the `GraphStore` contract, sub-contracts, value types, reference driver and conformance kit): relational/registry sources + the `GraphStoreManager`.

Ships zero persistence drivers — real drivers are the consumer's, authored over its own wheel.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
