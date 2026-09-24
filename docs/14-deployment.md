# 14. Deployment

## Artisan commands

| Command | Use |
|---|---|
| `laragraph:validate` | Builds every configured schema and runs webonyx's schema validation. Exits non-zero on any error. `--schema=admin` (repeatable) limits it to specific schemas. |
| `laragraph:cache` | Writes the discovery manifest to `bootstrap/cache/laragraph.php` |
| `laragraph:clear` | Removes the manifest |
| `laragraph:schema:export` | Prints the schema as SDL. `--schema=admin` picks a schema; `--output=schema.graphql` writes to a file. |
| `about` | Shows Laragraph's configuration (see [Observability](12-observability.md#php-artisan-about)) |

Generators (`laragraph:make:*`, `laragraph:scaffold`) are covered in
[Getting started](01-getting-started.md#generators).

## Deploy script

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force

php artisan optimize              # config, routes, views, events, and laragraph:cache (Laravel 11.27+)
# php artisan laragraph:cache     # on older Laravel versions

php artisan laragraph:validate    # fail the deploy if a schema is broken

php artisan queue:restart         # workers pick up the new code
php artisan octane:reload         # if you use Octane
php artisan reverb:restart        # if you use Reverb
```

Run `laragraph:validate` **after** caching, so it checks exactly what production will serve.
A type referencing a type that doesn't exist, an interface that isn't implemented, or a duplicate type
name fails here instead of on the first request.

### Config caching

`php artisan config:cache` serialises the config, so callables in `config/laragraph.php` must be
`[Class::class, 'method']` arrays, never closures. That applies to `error_formatter` and `errors_handler`.
Validation rules, middleware and types are class-name strings and are always safe.

## CI pipeline

```yaml
- run: composer install --prefer-dist --no-progress
- run: php artisan laragraph:validate
- run: php artisan test
- run: php artisan laragraph:schema:export --output=schema.graphql && git diff --exit-code schema.graphql
```

The last step fails when the schema changes without the committed SDL being updated. It makes schema
changes visible in code review, where breaking changes (removed fields, arguments that became
non-null) are easy to spot. Front-end code generators can use the committed file instead of
introspecting a server.

## Queues

Subscription fan-out (`Laragraph::broadcastLater()`) runs on the queue. Point it at a
dedicated queue so a burst of updates doesn't delay other jobs:

```php
'subscriptions' => [
    'queue' => [
        'connection' => 'redis',
        'queue'      => 'graphql-subscriptions',
    ],
],
```

```bash
php artisan queue:work redis --queue=graphql-subscriptions,default
```

## Broadcasting

For subscriptions in production:

1. **A broadcaster**: Reverb (`php artisan reverb:start`, behind a process manager), Pusher, Ably
   or a Pusher-compatible server. Set `BROADCAST_CONNECTION`.
2. **`/broadcasting/auth`** authenticates with your API guard (`withBroadcasting(…, ['middleware'
   => ['auth:api']])` or `auth:sanctum`).
3. **A shared cache store** for subscribers (`subscriptions.cache_store`), reachable by every web
   server and queue worker.
4. **`subscriptions.ttl`** sized to your clients' sessions. Clients re-subscribe after it expires.

## Production checklist

**Configuration**

- [ ] `APP_DEBUG=false`. GraphiQL and introspection then switch off, and errors don't leak.
- [ ] `auth.default_guard` matches your API guard.
- [ ] Security limits reviewed (`security.*`, `pagination.max_per_page`, `batching.*`).
- [ ] `cache.response.scope` is `user` unless every response is public.
- [ ] `route.middleware` includes rate limiting (`throttle:api`).
- [ ] CORS allows your front-end origins, for the GraphQL prefix and `broadcasting/auth`.

**Build**

- [ ] `php artisan optimize` (or `laragraph:cache`) runs on every deploy.
- [ ] `php artisan laragraph:validate` runs on every deploy and in CI.
- [ ] The SDL is exported and diffed in CI.

**Runtime**

- [ ] Queue workers are running (subscriptions, and any queued listeners).
- [ ] The broadcaster is running and `/broadcasting/auth` works with API tokens.
- [ ] Slow operations and `internal` errors are logged or reported
      ([Observability](12-observability.md)).
- [ ] Tracing is off (`tracing.enabled`).
