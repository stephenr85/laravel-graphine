> You are in **rushing/laravel-graphine** — a pluggable Laravel graph-substrate seam: the `GraphStore` contract + role sub-contracts + value types + one in-memory reference driver + a conformance test-kit.

Ships zero persistence drivers — real drivers are the consumer's, authored over its own wheel.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
