# 11. Performance & caching

In order of impact, the tools are:

1. **Batch your relations.** N+1 queries are by far the most common cause of slow GraphQL APIs
   ([Relations & DataLoaders](05-relations-and-dataloaders.md)).
2. **Cache discovery** in production (`php artisan optimize`).
3. **Cache responses** for read-heavy queries.
4. **Keep workers warm** with Octane, so the schema is compiled once per worker.

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

## Schema compilation

Each schema is compiled once per PHP process, on first use: classes are instantiated, fields
collected and types resolved lazily. In PHP-FPM that happens once per request. With **Octane**,
RoadRunner or FrankenPHP workers, it happens once per worker, so later requests skip it entirely.

### Octane notes

Laragraph is designed to run in long-lived workers:

- DataLoaders are created per execution and released afterwards. Nothing leaks between requests.
- The tracing collector is reset on every execution.
- Subscription updates run in an isolated request and auth state, which is then restored.
- The execution context is always the current request.

What *you* must watch: **type and field classes are instantiated once and reused** across
requests in a worker. Never keep per-request state (the current user, request data, a cache of
query results) in their properties. Use `$context` or local variables instead.

## Other tips

- **Select only what you need** in root resolvers when rows are wide:
  `$info->getFieldSelection()` tells you which fields were requested.
- **Index the columns you filter and order by** in paginated queries (`organization_id`,
  `status`, `published_at`). `pageInfo.total` runs a `COUNT(*)` with the same filters.
- **Queue side effects** such as broadcasts (`broadcastLater()`), mail and webhooks from mutations,
  so the response doesn't wait for them.
- **Measure** with [tracing](12-observability.md#tracing) in development and the
  `QueryExecuted` event in production.
