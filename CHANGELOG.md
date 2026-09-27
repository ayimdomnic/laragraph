# Changelog

All notable changes to `ayimdomnic/laragraph` are documented here.

## [Unreleased]

### Added

* **contracts:** introduce `src/Contracts/*` interfaces and split `Laragraph`
  and `LaragraphController` into focused, independently-testable classes
  (schema registry, query executor, type registry, HTTP request parsing,
  persisted-query resolution, subscription registration) — no public API
  changes; `Laragraph`'s facade methods, `config('laragraph.error_formatter'
  /errors_handler')`'s published array-callables, and the HTTP contract are
  all unchanged
* **phpstan:** ship a first-party PHPStan rule for unregistered type names
* **scaffold:** wire up Eloquent relations and native enum casts in
  `laragraph:scaffold`
* **validation:** reuse an existing FormRequest for `rules()`/`messages()`/`attributes()`
* **testing:** ship a `MakesGraphQLRequests` trait + `TestResponse` macros
* **console:** add `laragraph:make:loader` generator
* **subscriptions:** add a bounded SSE transport alongside Broadcasting
* **tracing:** add an OpenTelemetry driver alongside Apollo Tracing
* **extensions:** surface query cost under `extensions.queryComplexity`
* **schema:** add a CI schema-diff / breaking-change gate (`laragraph:schema:diff`)
* **relay:** add Node re-fetching (global-ID codec + root `node` field)

### Fixed

* **scaffold:** make `--register` actually write to `config/laragraph.php`
* **resolver:** warn instead of silently resolving a field to `null`
* **docs:** fix a stale badge, a missing LICENSE reference, and other doc
  accuracy bugs

### Performance

* **dataloader:** avoid an `array_values()` copy on every relation batch

### Security

* Rewrote SECURITY.md with a real supported-versions table and vulnerability
  reporting process (previously the unedited GitHub template)

## [4.1.0](https://github.com/ayimdomnic/laragraph/compare/v4.0.0...v4.1.0) (2026-09-26)

### Added

* **errors:** first-class error handling and configurable per-request localization
* **performance:** PHPBench benchmarks with a stored baseline and CI budgets
* **performance:** lazy schema building, a single document parse + validation
  cache, and cheaper Eloquent attribute reads

### Fixed

* **octane:** keep Laragraph warm across requests under Laravel Octane
* **subscriptions:** isolate subscription updates from the triggering
  request's auth context

## [4.0.0](https://github.com/ayimdomnic/laragraph/compare/v3.1.1...v4.0.0) (2026-09-24)

### Added

* **docs:** a complete developer guide (docs/01–17) and a full example
  application exercising every feature
* **subscriptions:** unsubscribing, and a full subscription lifecycle
* **console:** `laragraph:validate` as a deploy-time schema check

### Fixed

* **security:** hide internal exception messages from clients by default
* **security:** general security hardening pass across the request pipeline
* **pagination:** share one `PageInfo` type across connections
* **routes:** apply each schema's own middleware to its endpoint
* **routes:** other runtime and execution-consistency fixes

### Changed

* Modernized for PHP 8.2–8.5 and Laravel 10–13

## [3.1.1](https://github.com/ayimdomnic/laragraph/compare/v3.1.0...v3.1.1) (2026-07-30)

### Bug Fixes

* **parser:** support `application/graphql` request bodies
* **parser:** fix the parser error on version 3

## [3.1.0](https://github.com/ayimdomnic/laragraph/compare/v3.0.0...v3.1.0) (2026-07-30)

### Features

* support Laravel 13 compatibility

## [3.0.0](https://github.com/ayimdomnic/laragraph/compare/v2.0.0...v3.0.0) (2026-07-30)

### ⚠ BREAKING CHANGES

* release 3.0.0

## [2.0.0](https://github.com/ayimdomnic/laragraph/compare/v1.0.0...v2.0.0) (2026-07-30)

### ⚠ BREAKING CHANGES

* release 2.0.0

### Chores

* ignore phpunit cache and finalize pagination/type annotations

## 1.0.0 (2026-07-29)

Initial modernized release — a full rewrite of the package around a code-first GraphQL engine on top of `webonyx/graphql-php`.

### Features

* **core:** add GraphQL engine, schema builder, and HTTP layer
* **auth:** add field-level authentication with configurable guards
* **discovery:** add auto-discovery for query, mutation, and type classes
* **pagination:** add Relay-spec cursor pagination with Connection types
* **dataloader:** add DataLoader to batch and cache N+1 resolver calls
* **scalars:** add custom scalar types and database engine presets
* **cache:** add response caching for read-only GraphQL queries
* **persisted-queries:** add persisted query support with cache and array stores
* **console:** add Artisan make commands for scaffolding GraphQL classes
* **middleware:** add field middleware pipeline with throttle and logging
* **extensions:** add response extensions for request ID and query timing
* **events:** add lifecycle events for schema compilation and query execution
* **batch:** add configurable batch request processing
* **validation:** add custom query validation rules with alias-flooding protection

### Tests

* add comprehensive test suite — 437 tests, 100% statement coverage
