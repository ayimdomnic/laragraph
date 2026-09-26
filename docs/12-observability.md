# 12. Observability

## Lifecycle events

Laragraph fires ordinary Laravel events, so you can listen with `Event::listen()`, queued listeners,
or tools that already consume events (Telescope, Pulse recorders, Sentry breadcrumbs).

| Event | When | Properties |
|---|---|---|
| `Events\QueryExecuting` | Before an operation runs (also before cache lookups) | `query`, `variables`, `operationName`, `schemaName` |
| `Events\QueryExecuted` | After every operation, **including response-cache hits** | the above plus `result`, `executionMs`, `hasErrors`, `cached` |
| `Events\QueryError` | After an operation whose response contains errors | the above plus `errors` (the formatted errors) |
| `Events\SchemaBuilt` | When a schema is compiled (once per process) | `schemaName`, `schema` |

All are in the `Ayimdomnic\Laragraph\Events` namespace. Every operation of a
[batch](08-http-api.md#batching) fires its own events.

`operationName` is the name **the client sent** in the request. A document such as
`query Dashboard { … }` sent without `"operationName": "Dashboard"` reports `null`. Ask your
clients to send it (Apollo and Relay do) if you group metrics by operation.

### Example: logging slow operations

```php
// example/app/Listeners/LogSlowGraphQLOperations.php
use Ayimdomnic\Laragraph\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

class LogSlowGraphQLOperations
{
    public function handle(QueryExecuted $event): void
    {
        if ($event->executionMs < (float) config('app.graphql_slow_ms', 500)) {
            return;
        }

        Log::warning('Slow GraphQL operation', [
            'operation' => $event->operationName ?? '(anonymous)',
            'schema'    => $event->schemaName,
            'ms'        => $event->executionMs,
            'cached'    => $event->cached,
        ]);
    }
}
```

```php
// AppServiceProvider::boot()
Event::listen(QueryExecuted::class, LogSlowGraphQLOperations::class);
```

Other common uses: counting operations and errors per `operationName` for your metrics
system, auditing mutations (`Operation::isMutation($event->query, $event->operationName)`),
reporting `QueryError`s with category `internal` to your error tracker, and flushing the
[response cache](11-performance-and-caching.md#invalidation).

`variables` may contain passwords and tokens (`login(password: …)`). Filter them before logging.

## Response extensions

Extensions add metadata under the response's top-level `extensions` key. Three are built in, all
off by default:

```php
'extensions' => [
    'request_id'       => true,   // extensions.requestId.id
    'query_timing'     => true,   // extensions.timing.execution_ms
    'query_complexity' => true,   // extensions.queryComplexity.{cost,maxCost}
],
```

```json
{
  "data": { "me": { "name": "Ada" } },
  "extensions": {
    "requestId": { "id": "5f0c6f9e-3b4c-4a8e-9d0f-2b1e6c7a8d90" },
    "timing": { "execution_ms": 4.12 },
    "queryComplexity": { "cost": 14, "maxCost": 500 }
  }
}
```

- **`requestId`** reuses the client's `X-Request-ID` header when it looks like an id (letters,
  digits, `.`, `_`, `-`, up to 128 characters). Otherwise it generates a UUID. It's the same for every
  operation in a batch, so you can correlate client reports with server logs.
- **`timing`** is the wall-clock time of the operation, in milliseconds.
- **`queryComplexity`** is the query's computed cost against
  [`security.query_max_complexity`](10-security.md#complexity) — empty (`{}`) when that limit isn't
  configured. Still populated when the query is rejected for exceeding the limit, so a client can
  see exactly how far over budget it was, the same idea as GitHub's or Shopify's GraphQL APIs
  exposing a rate-limit cost for clients to self-throttle against.

### Custom extensions

Implement `GraphQLExtensionInterface` and register it:

```php
// example/app/GraphQL/Extensions/ApiVersionExtension.php
use Ayimdomnic\Laragraph\Extensions\GraphQLExtensionInterface;

class ApiVersionExtension implements GraphQLExtensionInterface
{
    public function key(): string
    {
        return 'apiVersion';
    }

    /** @param array{execution_ms?: float} $context */
    public function get(array $context = []): array
    {
        return ['version' => config('app.api_version', '2026-09')];
    }
}
```

```php
// AppServiceProvider::boot()
$this->app->make(ExtensionRegistry::class)->add(new ApiVersionExtension);
```

Return `[]` from `get()` to leave the extension out of a particular response. Extensions are computed
for every response, including cache hits, and are never cached.

## Tracing

Tracing times **every resolver**, root fields and nested type fields alike, and reports the
results in the [Apollo Tracing](https://github.com/apollographql/apollo-tracing) format:

```php
'tracing' => [
    'enabled' => env('LARAGRAPH_TRACING', false),
],
```

```json
"extensions": {
  "tracing": {
    "version": 1,
    "startTime": "2026-09-24T10:15:30.123Z",
    "endTime": "2026-09-24T10:15:30.141Z",
    "duration": 18234567,
    "execution": {
      "resolvers": [
        { "path": ["posts"], "parentType": "Query", "fieldName": "posts",
          "returnType": "PostConnection", "startOffset": 51234, "duration": 9123456 }
      ]
    }
  }
}
```

Durations are in nanoseconds. Tracing wraps every resolver, so it has a cost and exposes timing
details. Enable it in development, or temporarily in staging, not on a public production API.

## Field logging

`LoggingMiddleware` logs each root field's resolution and its duration at `debug` level:

```php
'middleware' => [\Ayimdomnic\Laragraph\Middleware\LoggingMiddleware::class],   // every root field
'logging'    => ['channel' => 'graphql'],                                     // null = default channel
```

```
[debug] GraphQL: [posts] resolved in 12.4ms {"field":"posts","elapsed_ms":12.4}
```

Or add it to a single field's `middleware()`. The `logging.channel` also receives subscription
updates when `subscriptions.driver` is `log`.

## `php artisan about`

Laragraph adds a section to `php artisan about`, a quick way to check a server's configuration:

```
  Laragraph ..........................................................
  Version .................................................... 3.1.1
  Endpoint ................................................ /graphql
  Schemas ............................................ default, admin
  Discovery ................................................. CACHED
  GraphiQL ..................................................... OFF
  Introspection ................................................ OFF
  Response cache ........................................... ENABLED
  Persisted queries ........................................ ENABLED
  Subscriptions ............................................ ENABLED
  Tracing ...................................................... OFF
```
