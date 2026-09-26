# Laragraph developer guide

Laragraph is a **code-first** GraphQL server for Laravel: your types, queries and mutations are
plain PHP classes, and everything else — validation, authorization, pagination, N+1 batching,
subscriptions, caching — uses the Laravel features you already know.

This guide explains every feature, why it exists and how to use it safely. Almost every code
sample comes from the [example application](../example/README.md), whose test suite runs against
this repository — so the samples are known to work.

## Contents

**Start here**

1. [Getting started](01-getting-started.md) — install, your first type, query and mutation, and how requests flow
2. [Types](02-types.md) — object, input, enum (including native PHP enums), interface and union types, scalars, and how types are registered
3. [Queries & mutations](03-queries-and-mutations.md) — fields, arguments, resolvers, the context, validation, errors, deprecation

**Building a real API**

4. [Authentication & authorization](04-authentication-and-authorization.md) — guards, `authorize()`, policies, field-level privacy
5. [Relations & DataLoaders](05-relations-and-dataloaders.md) — solving N+1 with `batchRelation()` and custom loaders
6. [Pagination](06-pagination.md) — Relay cursor connections, simple pagination, and Node re-fetching
7. [Subscriptions](07-subscriptions.md) — real-time updates over Laravel Broadcasting
8. [The HTTP API](08-http-api.md) — GraphQL over HTTP, file uploads, batching, persisted queries, error codes
9. [Multiple schemas](09-multiple-schemas.md) — separate public and admin APIs

**Running it in production**

10. [Security](10-security.md) — defaults, limits, and a hardening checklist
11. [Performance & caching](11-performance-and-caching.md) — response cache, discovery cache, Octane
12. [Observability](12-observability.md) — events, response extensions, tracing, logging
13. [Testing](13-testing.md) — testing your GraphQL API with PHPUnit
14. [Deployment](14-deployment.md) — artisan commands, CI schema-diff gate, queues, broadcasting, checklists

**Reference**

15. [Configuration reference](15-configuration.md) — every option in `config/laragraph.php`
16. [Upgrading](16-upgrading.md) — behaviour changes between releases
17. [Error handling & localization](17-error-handling-and-localization.md) — `GraphQLException`, error codes, and translating messages per request

## Conventions used in this guide

- `Laragraph::type('User')` is the facade `Ayimdomnic\Laragraph\Facades\Laragraph`;
  `app('laragraph')->type('User')` is equivalent.
- `Type` in field definitions is webonyx's `GraphQL\Type\Definition\Type`. Laragraph's own base
  class for object types is also called `Type` (`Ayimdomnic\Laragraph\Support\Type`), so type
  classes usually import webonyx's as `GType`.
- "The example" means the application in [`example/`](../example).
