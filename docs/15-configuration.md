# 15. Configuration reference

Publish the file with `php artisan vendor:publish --tag=laragraph-config`. Every key is optional;
the defaults are shown. For a complete, working configuration, see
[`example/config/laragraph.php`](../example/config/laragraph.php).

## `default_schema`

```php
'default_schema' => 'default',
```

The schema served at `/graphql` and used by `Laragraph::execute()` when you don't name one. It must be a
key of `schemas`.

## `auth`

```php
'auth' => [
    'default_guard' => null,
],
```

| Key | Default | Meaning |
|---|---|---|
| `default_guard` | `null` | The guard used by `authorizeWithContext()`, `policy()`, the per-user response cache and subscriber identities, when a field doesn't declare `guards()`. `null` means Laravel's default guard. See [Authentication](04-authentication-and-authorization.md#authentication). |

## `discover`

```php
'discover' => [
    'types'         => 'app/GraphQL/Types',
    'queries'       => 'app/GraphQL/Queries',
    'mutations'     => 'app/GraphQL/Mutations',
    'subscriptions' => 'app/GraphQL/Subscriptions',
],
```

Directories (relative to the project root) that are scanned **recursively** for classes extending
the matching base class. Set a key to `''` to turn off discovery for that category. Discovered
fields and types belong to **every** schema (see [Multiple schemas](09-multiple-schemas.md#what-each-schema-contains)).
Cache the result in production with `laragraph:cache`.

## `route`

```php
'route' => [
    'prefix'     => 'graphql',
    'middleware' => [],
    'methods'    => ['GET', 'POST'],
],
```

| Key | Meaning |
|---|---|
| `prefix` | URL prefix of every Laragraph route |
| `middleware` | Route middleware for all Laragraph routes, including GraphiQL and unsubscribe (e.g. `['throttle:api']`) |
| `methods` | HTTP methods for schema endpoints that don't set their own `method` |

## `schemas`

```php
'schemas' => [
    'default' => [
        'query'        => [],   // 'fieldName' => QueryClass::class
        'mutation'     => [],
        'subscription' => [],
        'types'        => [],   // 'Alias' => TypeClass::class, only in this schema
        'middleware'   => [],   // route middleware for this schema's endpoint
        'method'       => ['GET', 'POST'],
    ],
],
```

See [Multiple schemas](09-multiple-schemas.md).

## `types`

```php
'types' => [
    'DateTime' => \Ayimdomnic\Laragraph\Scalars\DateTimeType::class,
    'Date'     => \Ayimdomnic\Laragraph\Scalars\DateType::class,
    'JSON'     => \Ayimdomnic\Laragraph\Scalars\JsonType::class,
    'Upload'   => \Ayimdomnic\Laragraph\Scalars\UploadType::class,
    'UserRole' => \App\Enums\UserRole::class,     // native PHP enums work too
],
```

Types registered for every schema. Use it for scalars, enums and any type outside the discovery
directories. See [Types → Registering types](02-types.md#registering-types).

## `error_formatter` and `errors_handler`

```php
'error_formatter' => [\Ayimdomnic\Laragraph\Laragraph::class, 'formatError'],
'errors_handler'  => [\Ayimdomnic\Laragraph\Laragraph::class, 'handleErrors'],
```

`error_formatter(Error $error): array` formats each error. `errors_handler(array $errors, callable $formatter): array`
receives the whole list. Use array callables (not closures) so `config:cache` works. See
[Custom error formatting](03-queries-and-mutations.md#custom-error-formatting).

## `errors`

```php
'errors' => [
    'negotiate_locale'  => false,
    'supported_locales' => ['en'],
    'locale_resolver'   => null,
],
```

Controls per-request error localization; disabled by default (zero overhead — one `config()` call
per request). See [Error Handling & Localization](17-error-handling-and-localization.md).

## `security`

```php
'security' => [
    'query_max_complexity'  => 500,
    'query_max_depth'       => 15,
    'disable_introspection' => null,
    'max_aliases'           => 30,
],
```

| Key | Meaning |
|---|---|
| `query_max_complexity` | Maximum total field cost per operation. `null` disables it. |
| `query_max_depth` | Maximum selection nesting. The introspection query needs 11. `null` disables it. |
| `disable_introspection` | `null` = disabled while `app.debug` is off; `true`/`false` forces it either way |
| `max_aliases` | Maximum aliases per document. `null` disables it. |

See [Security](10-security.md).

## `validation`

```php
'validation' => [
    'rules' => [],   // FQCNs of GraphQL\Validator\Rules\ValidationRule classes
],
```

Extra document validation rules, resolved from the container and run on every operation. A rule of
the same class as a built-in rule replaces it. Also available at runtime:
`Laragraph::addValidationRule()`.

## `pagination`

```php
'pagination' => [
    'per_page'     => 15,
    'max_per_page' => 100,
],
```

| Key | Meaning |
|---|---|
| `per_page` | Page size when the client sends neither `first` nor `last` (or `per_page`) |
| `max_per_page` | Upper bound for any requested page size. `null` = no cap. |

## `cache`

```php
'cache' => [
    'response' => [
        'enabled' => false,
        'store'   => 'default',
        'ttl'     => 60,
        'scope'   => 'user',
    ],
],
```

| Key | Meaning |
|---|---|
| `enabled` | Cache results of query operations |
| `store` | Cache store name; `'default'` = `cache.default` |
| `ttl` | Seconds |
| `scope` | `'user'`: one partition per user (on `auth.default_guard`) plus one shared guest partition. `'global'`: shared by everyone. |

See [Performance & caching](11-performance-and-caching.md#the-response-cache).

## `persisted_queries`

```php
'persisted_queries' => [
    'enabled' => false,
    'store'   => 'cache',
    'ttl'     => 3600,
    'map'     => [],
    'apq'     => true,
    'only'    => false,
],
```

| Key | Meaning |
|---|---|
| `enabled` | Accept `queryId` and APQ `extensions.persistedQuery` requests |
| `store` | `'cache'` (default cache store, supports runtime registration) or `'array'` (the static `map`) |
| `ttl` | Lifetime of cache-stored queries in seconds; `null` = forever |
| `map` | `id => query` pairs for the `array` store. Key them by SHA-256 hash for trusted-documents mode. |
| `apq` | Store a query when a client sends it together with its hash |
| `only` | Trusted documents: execute only query text already stored under its hash |

See [Persisted queries](08-http-api.md#persisted-queries).

## `extensions`

```php
'extensions' => [
    'request_id'       => false,
    'query_timing'     => false,
    'query_complexity' => false,
],
```

Add `extensions.requestId.id`, `extensions.timing.execution_ms`, and
`extensions.queryComplexity.{cost,maxCost}` (empty unless `security.query_max_complexity` is set)
to every response. Register custom extensions with `ExtensionRegistry::add()`. See
[Observability](12-observability.md#response-extensions).

## `middleware`

```php
'middleware' => [],
```

Field middleware applied to **every root field** (queries, mutations, subscriptions), before the
field's own `middleware()`. Entries are class names (resolved from the container) or instances of
`FieldMiddlewareInterface`. See [Field middleware](03-queries-and-mutations.md#field-middleware).

## `logging`

```php
'logging' => [
    'channel' => null,
],
```

The log channel used by `LoggingMiddleware` and by the `log` subscription driver. `null` means the
default channel.

## `database_types`

```php
'database_types' => [
    'preset' => null,   // 'postgres', 'cockroachdb', 'mssql', 'oracle'
    'custom' => [],     // 'Name' => ScalarClass::class
],
```

Register scalars for native column types:

| Preset | Scalars |
|---|---|
| `postgres` | UUID, BigInt, JSONB, Money, TSVector, Interval, Inet |
| `cockroachdb` | UUID, BigInt, JSONB, Inet |
| `mssql` | UUID, BigInt, Money |
| `oracle` | UUID, BigInt, Interval |

See [Types → Database scalar presets](02-types.md#database-scalar-presets).

## `batching`

```php
'batching' => [
    'enabled'        => false,
    'max_operations' => 10,
],
```

Accept a JSON list of operations per request. `max_operations` of `0` removes the limit. See
[Batching](08-http-api.md#batching).

## `graphiql`

```php
'graphiql' => [
    'enabled'    => null,
    'middleware' => [],
    'title'      => 'Laragraph — GraphiQL',
],
```

| Key | Meaning |
|---|---|
| `enabled` | `null` = served only while `app.debug` is on; `true` = always; `false` = never |
| `middleware` | Route middleware for the GraphiQL page, e.g. `['web', 'auth', 'can:viewGraphiql']` |
| `title` | Page title |

## `tracing`

```php
'tracing' => [
    'enabled' => false,
    'driver'  => 'apollo',  // or 'otel'
    'otel'    => ['tracer_name' => 'laragraph'],
],
```

Resolver timings, `'apollo'` (default) under `extensions.tracing`, `'otel'` exported as real
OpenTelemetry spans instead. Development only. See [Tracing](12-observability.md#tracing).

## `octane`

```php
'octane' => [
    'warm' => true,
],
```

Under Laravel Octane, add Laragraph to `octane.warm` so each worker compiles the schema and
validates each document once, instead of on every request. `false` leaves Octane's `warm` list
alone. See [Performance → Octane](11-performance-and-caching.md#octane).

## `subscriptions`

```php
'subscriptions' => [
    'enabled'           => false,
    'driver'            => 'broadcast',
    'cache_store'       => null,
    'ttl'               => 3600,
    'channel_prefix'    => 'graphql-subscriber',
    'authorize_channel' => true,
    'queue' => [
        'connection' => null,
        'queue'      => null,
    ],
],
```

| Key | Meaning |
|---|---|
| `enabled` | Accept subscription operations. When off, they're rejected with an error. |
| `driver` | `'broadcast'` (Laravel Broadcasting) or `'log'` (write updates to `logging.channel`) |
| `cache_store` | Where subscriber registrations are stored; must be shared between servers. `null` = default store. |
| `ttl` | Lifetime of a registration in seconds |
| `channel_prefix` | Private channel each subscriber listens on: `{prefix}.{subscriberId}` |
| `authorize_channel` | Register the rule that lets only the subscriber join their channel. `false` = write your own in `routes/channels.php`. |
| `queue.connection` / `queue.queue` | Where `broadcastLater()` queues its job. `null` = application defaults. |

See [Subscriptions](07-subscriptions.md).
