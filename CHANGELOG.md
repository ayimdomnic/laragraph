# Changelog

All notable changes to `ayimdomnic/laragraph` are documented here.


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
