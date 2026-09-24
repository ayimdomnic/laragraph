# 11. Performance & caching

In order of impact, the tools are:

1. **Batch your relations.** N+1 queries are by far the most common cause of slow GraphQL APIs
   ([Relations & DataLoaders](05-relations-and-dataloaders.md)).
2. **Cache discovery** in production (`php artisan optimize`).
3. **Cache responses** for read-heavy queries.
4. **Keep workers warm** with Octane, so schema compilation, parsing and validation are reused across requests.

## The response cache

The response cache stores the complete result of **query** operations in your Laravel cache.
Mutations and subscriptions are never cached, and neither are responses containing errors.

```php
// config/laragraph.php
'cache' => [
    'response' => [
        'enabled' => env('LARAGRAPH_RESPONSE_CACHE', false),
        'store'   => 'default',     // any cache store; 'default' = cache.default
        'ttl'     => 60,            // seconds
        'scope'   => 'user',        // 'user' (default) or 'global'
    ],
],
```

### What makes two requests "the same"

The cache key combines:

- the schema;
- the **scope**, meaning the user;
- the operation name;
- the query text (trimmed);
- the variables (key order doesn't matter).

Whether an operation is a query is decided by parsing the document, not by looking at the
text. A document with several operations is cached per `operationName`.

### Scope: per user by default

With `scope => 'user'`, every authenticated user has their own cache entries, and all guests share
one. The user comes from `auth.default_guard`. This matters: `{ me { email } }` and any field that
depends on permissions (like `User.email` in the example) give different answers to different
users, and a shared cache would serve one user's data to another.

Use `'global'` **only** when every response is identical for every caller: a public catalogue,
a CMS, reference data.

### Invalidation

Entries expire after `ttl` seconds. To drop them earlier, call `ResponseCache::flush()`:

```php
use Ayimdomnic\Laragraph\Performance\ResponseCache;

ResponseCache::flush();
```

`flush()` doesn't delete entries one by one. It advances a *generation number* that's part of every
key, so all older entries become unreachable at once, on every cache store, tagged or not, in
constant time. The orphaned entries expire through their TTL.

The example flushes after every successful mutation, so nobody reads data a mutation has just
changed:

```php
// example/app/Listeners/FlushResponseCacheAfterMutations.php
use Ayimdomnic\Laragraph\Events\QueryExecuted;
use Ayimdomnic\Laragraph\Performance\ResponseCache;
use Ayimdomnic\Laragraph\Support\Operation;

class FlushResponseCacheAfterMutations
{
    public function handle(QueryExecuted $event): void
    {
        if (! $event->hasErrors && Operation::isMutation($event->query, $event->operationName)) {
            ResponseCache::flush();
        }
    }
}

// AppServiceProvider::boot()
Event::listen(QueryExecuted::class, FlushResponseCacheAfterMutations::class);
```

Flush from anywhere else your data changes too: model observers, jobs, admin panels,
imports. If writes are frequent, a short TTL with no flushing is often the better trade-off.

### When to use it

The response cache is effective for **expensive, frequently repeated, rarely changing** queries:
dashboards, public listings, reference data. It adds little to cheap queries (the cache round-trip
costs about as much as the query) and nothing to queries that are rarely repeated with the same
variables.

Response extensions (request id, timing, your own) are computed per request and never cached. The
`QueryExecuted` event carries `cached: true` for cache hits, so you can measure the hit rate
([Observability](12-observability.md)).

## The discovery cache

Auto-discovery scans `app/GraphQL/**` for classes. In production, write the result to a manifest
once instead:

```bash
php artisan laragraph:cache     # writes bootstrap/cache/laragraph.php
php artisan laragraph:clear     # removes it
```

On Laravel 11.27+, `php artisan optimize` and `optimize:clear` run these for you.
`php artisan about` shows whether discovery is cached.

> Remember to re-run `laragraph:cache` (or `optimize`) on every deploy. A stale manifest doesn't
> know about new classes, and an old one may point at classes that were removed.

## What Laragraph does on every request

### The schema is built lazily

A schema is compiled once per PHP process, and **only as far as a request needs it**:

- A root field's class (`UsersQuery`, `CreatePostMutation`…) is instantiated the first time a
  document selects that field.
- A type class is instantiated the first time a document reaches that type.
- The complete type map is assembled only when it's really needed: introspection, a field returning an
  interface, `laragraph:validate` and `laragraph:schema:export`.

In PHP-FPM, where every request starts from scratch, a request against an API with hundreds of
operations therefore loads a handful of classes instead of all of them. On a synthetic schema of 300 types
and 300 queries, a cold request spends about 6.6 ms in Laragraph instead of about 26 ms, and
builds 3 types instead of 300. With **Octane**, RoadRunner or FrankenPHP, the work is done once per
worker and reused.

One consequence: a field class that throws while being compiled (for example a `type()` that names an
unregistered type) now fails when that field is first used, not on every request. Run
`php artisan laragraph:validate` in CI and on deploy. It compiles everything and fails on
any error.

### Documents are parsed once and validated once

Each query document is parsed once per request, and the AST is shared by everything that needs it:
detecting mutations sent over GET, subscriptions, response caching and execution. A parsed
document takes a few hundred times the memory of its text, so workers keep the most recently used
documents up to 100 entries *and* 64 KB of query text, whichever comes first. The document of the
current request is always kept.

Validation is split in two:

- The rules that depend only on the document: the GraphQL specification's rules, plus the depth,
  alias and introspection limits. They run **once per document and schema** in a worker, and later
  executions of the same document skip them. A worker remembers the last 1,000 validated documents
  (a few dozen bytes each). A changed limit, or another schema, validates the
  document again.
- Query complexity, which depends on variables (`@include(if: $flag)`), and your own
  `validation.rules`, which may depend on anything. These run on **every** execution.

Clients that send the same operations over and over, which is all of them, and especially clients
using [persisted queries](08-http-api.md#persisted-queries), pay for validation once per worker.

### Model attributes are read once

Fields without a resolver read the value from the parent. For Eloquent models Laragraph calls
`getAttribute()` once per field. webonyx's own default resolver read each attribute twice, casts
and accessors included, through `offsetExists()` and `offsetGet()`. A missing attribute under
`Model::shouldBeStrict()` still reads as `null`.

### Octane

Octane handles every request in a fresh copy of the application and discards services first
created during a request. Laragraph therefore **adds itself to Octane's `warm` list**
automatically, so each worker creates it once. The compiled schema, the parsed documents and the
validation cache then serve every request that worker handles. On the example app this makes a
request about 30% faster than rebuilding them each time, and the gain grows with the size of the
schema. Set `laragraph.octane.warm` to `false` to opt out.

Laragraph is designed for long-lived workers:

- DataLoaders are created per execution and released afterwards. Nothing leaks between requests.
- The tracing collector is reset on every execution.
- The execution context is always the current request.
- Subscription updates run in an isolated request, auth manager **and Gate**, which are all
  restored afterwards. Octane shares the Gate between requests, and that Gate resolves users through the worker's
  auth manager, so Laragraph binds a Gate to the subscriber for the duration of each update.

What *you* must watch: **type and field classes are instantiated once per worker and reused**
across requests. Never keep per-request state (the current user, request data, a cache of query
results) in their properties or inject it into their constructors. Use `$context` or local
variables instead.

The example app's [`OctaneTest`](../example/tests/Octane/OctaneTest.php) runs requests through a
real Octane worker. It checks that the schema is compiled once, that each request sees only its
own user, and that subscription updates run as the subscriber.

## Other tips

- **Select only what you need** in root resolvers when rows are wide:
  `$info->getFieldSelection()` tells you which fields were requested.
- **Index the columns you filter and order by** in paginated queries (`organization_id`,
  `status`, `published_at`). `pageInfo.total` runs a `COUNT(*)` with the same filters.
- **Queue side effects** such as broadcasts (`broadcastLater()`), mail and webhooks from mutations,
  so the response doesn't wait for them.
- **Measure** with [tracing](12-observability.md#tracing) in development and the
  `QueryExecuted` event in production.

## How Laragraph's performance is guarded

The package's test suite includes performance budgets that fail when a change makes Laragraph do
more work: more SQL queries, more classes built, more memory kept between requests. Timing
benchmarks compare a branch against a baseline. See
[benchmarks/README.md](../benchmarks/README.md).
