<?php

declare(strict_types=1);

use Ayimdomnic\Laragraph\Laragraph;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Schema
    |--------------------------------------------------------------------------
    |
    | The default schema name to use when no schema is specified. This should
    | match a key in the 'schemas' array below.
    |
    */
    'default_schema' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Authentication / Authorization
    |--------------------------------------------------------------------------
    |
    | default_guard — The Laravel guard used when a field does not explicitly
    |   specify one. Set to null to use Laravel's own default guard.
    |
    | Examples:
    |   'default_guard' => 'sanctum',
    |   'default_guard' => 'api',
    |
    */
    'auth' => [
        'default_guard' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery
    |--------------------------------------------------------------------------
    |
    | When set, Laragraph scans these directories and auto-registers all
    | classes that extend the corresponding base class — no manual config
    | entries required. Explicit 'schemas' entries always take precedence
    | over discovered ones.
    |
    | Set a key to '' to disable discovery for that category.
    |
    */
    'discover' => [
        'types'         => 'app/GraphQL/Types',
        'queries'       => 'app/GraphQL/Queries',
        'mutations'     => 'app/GraphQL/Mutations',
        'subscriptions' => 'app/GraphQL/Subscriptions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the HTTP route used to serve GraphQL requests.
    |
    */
    'route' => [
        'prefix' => 'graphql',
        'middleware' => [],
        'methods' => ['GET', 'POST'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Schemas
    |--------------------------------------------------------------------------
    |
    | Each schema has its own set of queries, mutations, subscriptions, and
    | middleware. You may define multiple schemas (e.g. 'default', 'admin').
    |
    | Example:
    |   'query' => [
    |       'users'       => \App\GraphQL\Queries\UsersQuery::class,
    |       'user'        => \App\GraphQL\Queries\UserQuery::class,
    |   ],
    |   'mutation' => [
    |       'createUser'  => \App\GraphQL\Mutations\CreateUserMutation::class,
    |       'deleteUser'  => \App\GraphQL\Mutations\DeleteUserMutation::class,
    |   ],
    |   'subscription' => [
    |       'userCreated' => \App\GraphQL\Subscriptions\UserCreatedSubscription::class,
    |   ],
    |
    */
    'schemas' => [
        'default' => [
            'query' => [
                // 'users' => \App\GraphQL\Queries\UsersQuery::class,
            ],
            'mutation' => [
                // 'createUser' => \App\GraphQL\Mutations\CreateUserMutation::class,
            ],
            'subscription' => [
                // 'userCreated' => \App\GraphQL\Subscriptions\UserCreatedSubscription::class,
            ],
            'middleware' => [],
            'method' => ['GET', 'POST'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Types
    |--------------------------------------------------------------------------
    |
    | Register GraphQL types that should be available across all schemas.
    | The key is the type name used to reference it in other types; the value
    | is the FQCN of your type class.
    |
    | Example:
    |   'User'    => \App\GraphQL\Types\UserType::class,
    |   'Post'    => \App\GraphQL\Types\PostType::class,
    |
    */
    'types' => [
        // Built-in scalars (uncomment to enable globally)
        // 'DateTime' => \Ayimdomnic\Laragraph\Scalars\DateTimeType::class,
        // 'Date'     => \Ayimdomnic\Laragraph\Scalars\DateType::class,
        // 'JSON'     => \Ayimdomnic\Laragraph\Scalars\JsonType::class,
        // 'Upload'   => \Ayimdomnic\Laragraph\Scalars\UploadType::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Formatter
    |--------------------------------------------------------------------------
    |
    | Called for each error in the response. You may override this to add
    | custom extensions or transform error messages.
    |
    */
    'error_formatter' => [Laragraph::class, 'formatError'],

    /*
    |--------------------------------------------------------------------------
    | Errors Handler
    |--------------------------------------------------------------------------
    |
    | Called once with the full array of errors and the error formatter. You
    | may use this to log, filter, or transform the errors array.
    |
    */
    'errors_handler' => [Laragraph::class, 'handleErrors'],

    /*
    |--------------------------------------------------------------------------
    | Error Localization
    |--------------------------------------------------------------------------
    |
    | negotiate_locale  — When true, the app locale is switched for the
    |   duration of each request based on its Accept-Language header, so
    |   GraphQLException messages and formatError() come back translated.
    |   Off by default: leaves the app's current locale untouched.
    |
    | supported_locales — Allow-list negotiate_locale may switch to. Required
    |   whenever negotiate_locale is on: an Accept-Language value is untrusted
    |   input, and an unvalidated locale eventually becomes part of a
    |   translation file path, so it is always checked against this list
    |   (and a strict locale-tag format) before being applied.
    |
    | locale_resolver   — callable(?Request $request): ?string for full
    |   control over locale resolution (e.g. from an authenticated user's
    |   saved preference). Takes priority over negotiate_locale when set,
    |   and its return value is still checked against supported_locales.
    |
    */
    'errors' => [
        'negotiate_locale'  => false,
        'supported_locales' => ['en'],
        'locale_resolver'   => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Limits that protect the API from overly expensive or abusive queries.
    | Set a limit to null to disable it.
    |
    | query_max_depth       — deepest allowed selection nesting. The standard
    |                         introspection query (GraphiQL, codegen) needs 11.
    | query_max_complexity  — total field cost (see Field::complexity()).
    | max_aliases           — aliases per document; blocks alias flooding.
    | disable_introspection — null (default) disables introspection whenever
    |                         app.debug is off, i.e. in production. Set true or
    |                         false to force it either way.
    |
    */
    'security' => [
        'query_max_complexity'  => 500,
        'query_max_depth'       => 15,
        'disable_introspection' => null,
        'max_aliases'           => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Rules
    |--------------------------------------------------------------------------
    |
    | Register additional GraphQL document-validation rules that are applied
    | to every execution. Each entry must be a FQCN of a class that extends
    | GraphQL\Validator\Rules\ValidationRule (resolved via the container).
    |
    | Rules can also be registered at runtime via:
    |   Laragraph::addValidationRule(new MyRule());
    |
    | Example:
    |   'rules' => [
    |       \App\GraphQL\Validation\NoDirectMutationsRule::class,
    |   ],
    |
    */
    'validation' => [
        'rules' => [],
    ],

    /*
    |
    | Default items per page for paginated queries.
    |
    */
    'pagination' => [
        'per_page'     => 15,
        // Largest page a client may request via first/last/per_page (null: no cap).
        'max_per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Cache
    |--------------------------------------------------------------------------
    |
    | When enabled, Laragraph caches serialised GraphQL query responses using
    | your Laravel cache store. Mutations and subscriptions are never cached.
    |
    | store — any Laravel cache driver (redis, file, array, memcached …)
    | ttl   — time-to-live in seconds
    |
    | scope — 'user' (default) partitions entries per authenticated user (on
    |   laragraph.auth.default_guard), with guests sharing one partition, so a
    |   user's data is never served to someone else. Use 'global' only when
    |   every caller receives identical responses.
    |
    | Cache is keyed by schema + scope + query string + variables + operation
    | name, so different variable combinations produce separate entries.
    |
    | To invalidate from code:
    |   \Ayimdomnic\Laragraph\Performance\ResponseCache::flush()       // everything
    |   \Ayimdomnic\Laragraph\Performance\ResponseCache::forget($key)  // one entry
    |
    */
    'cache' => [
        'response' => [
            'enabled' => false,
            'store'   => 'default',
            'ttl'     => 60,
            'scope'   => 'user',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Persisted Queries
    |--------------------------------------------------------------------------
    |
    | Persisted queries let clients send a short query ID instead of the full
    | query text, reducing bandwidth and optionally restricting execution to
    | pre-approved operations only.
    |
    | enabled — Set to true to activate the persisted-query lookup in the
    |   request pipeline. When false (default) the feature is completely
    |   transparent — no overhead is added.
    |
    | store — Which store implementation to use:
    |   'cache' — queries stored in the Laravel cache (supports APQ-style
    |             runtime registration); recommended for production
    |   'array' — static in-memory map read from 'map' below; no persistence
    |
    | ttl — Lifetime in seconds for cache-backed entries (null = forever).
    |
    | map — Static ID → query-string pairs used by the 'array' store.
    |   'map' => [
    |       'GetAllUsers' => '{ users { id name } }',
    |   ],
    |
    | apq — Automatic Persisted Queries: when a client sends the full query
    |   together with its sha256Hash, the query is stored so later requests
    |   can send the hash alone. Mismatched hashes are rejected.
    |
    | only — Trusted-documents mode: execute query text only if it is already
    |   in the store under its SHA-256 hash; everything else is rejected with
    |   PERSISTED_QUERY_REQUIRED. Pair it with the 'array' store (or a cache
    |   store you pre-populate at deploy time) to lock the API down to the
    |   operations your own clients ship.
    |
    | Clients may send the ID via:
    |   { "queryId": "<id>", "variables": {} }
    | Or the Apollo APQ format:
    |   { "extensions": { "persistedQuery": { "sha256Hash": "<hash>", "version": 1 } } }
    |
    */
    'persisted_queries' => [
        'enabled' => false,
        'store'   => 'cache',
        'ttl'     => 3600,
        'map'     => [],
        'apq'     => true,
        'only'    => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Extensions
    |--------------------------------------------------------------------------
    |
    | Built-in extensions append metadata to the top-level `extensions` key of
    | every GraphQL response. User-defined extensions can be registered at any
    | time via ExtensionRegistry::add().
    |
    | request_id   — Adds `extensions.requestId.id`.  The value is taken from
    |                the incoming X-Request-ID header or auto-generated as UUID.
    |
    | query_timing — Adds `extensions.timing.execution_ms`; wall-clock time of
    |                Laragraph::execute() in milliseconds.
    |
    | query_complexity — Adds `extensions.queryComplexity.{cost,maxCost}`, so
    |                clients can see how close a query is to
    |                security.query_max_complexity and self-throttle. Empty
    |                when that limit isn't configured.
    |
    */
    'extensions' => [
        'request_id'       => false,
        'query_timing'     => false,
        'query_complexity' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Middleware
    |--------------------------------------------------------------------------
    |
    | Global middleware applied around every field resolver, in declared order.
    | Each entry may be a FQCN string (resolved via the container) or an
    | instance. Classes must implement FieldMiddlewareInterface.
    |
    | Middleware runs after auth and validation, immediately before the
    | field's resolve() method is called. Per-field middleware declared in
    | Field::middleware() runs after global middleware.
    |
    | Example:
    |   'middleware' => [
    |       \Ayimdomnic\Laragraph\Middleware\LoggingMiddleware::class,
    |   ],
    |
    */
    'middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Settings used by the built-in LoggingMiddleware.
    |
    | channel — Laravel log channel name; null (default) uses the application's
    |   default log channel.
    |
    */
    'logging' => [
        'channel' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Unresolved Field Warnings
    |--------------------------------------------------------------------------
    |
    | A field with no resolve{Field}Field() method and no matching model
    | attribute / array key resolves to null with zero signal — the classic
    | camelCase/snake_case mismatch. When this is true (or null, the default,
    | meaning "follow app.debug"), that case logs a warning to logging.channel
    | instead of resolving silently. Leave it null: loud in development,
    | silent in production, without a stray warning per legitimately-null
    | field on a public API.
    |
    */
    'log_unresolved_fields' => null,

    /*
    |--------------------------------------------------------------------------
    | Database Scalar Types
    |--------------------------------------------------------------------------
    |
    | Enable a database-specific preset to automatically register scalar types
    | that map to native column types of the target engine.
    |
    | preset — one of: 'postgres', 'cockroachdb', 'mssql', 'oracle', or null
    |
    | Presets:
    |   postgres    → UUID, BigInt, JSONB, Money, TSVector, Interval, Inet
    |   cockroachdb → UUID, BigInt, JSONB, Inet
    |   mssql       → UUID, BigInt, Money
    |   oracle      → UUID, BigInt, Interval
    |
    | custom — merge additional scalars on top of (or instead of) the preset:
    |   'custom' => [
    |       'EmailAddress' => \App\GraphQL\Scalars\EmailAddressType::class,
    |   ],
    |
    */
    'database_types' => [
        'preset' => null,
        'custom' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Batched Requests
    |--------------------------------------------------------------------------
    |
    | Allow clients to send multiple GraphQL operations in a single HTTP request
    | as a JSON array: [{"query":"..."}, {"query":"..."}].
    |
    | enabled         — Set to true to accept batch requests. When false (the
    |                   default) any array payload returns HTTP 400.
    |
    | max_operations  — Upper bound on the number of operations allowed per
    |                   batch. Requests that exceed this limit are rejected
    |                   with HTTP 400. Set to 0 for no limit (not recommended
    |                   in public APIs).
    |
    */
    'batching' => [
        'enabled'        => false,
        'max_operations' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | GraphiQL
    |--------------------------------------------------------------------------
    |
    | The built-in GraphiQL browser IDE at /graphql/graphiql.
    |
    | enabled — null (default) serves it only while app.debug is on, i.e. not
    |   in production. Set true to always serve it (then protect it with
    |   'middleware', e.g. ['auth', 'can:viewGraphiql']) or false to never.
    |
    */
    'graphiql' => [
        'enabled'    => null,
        'middleware' => [],
        'title'      => 'Laragraph — GraphiQL',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracing
    |--------------------------------------------------------------------------
    |
    | When enabled, every field resolution (root Query/Mutation/Subscription
    | fields and nested Type fields alike) is timed. Disabled by default —
    | enabling it adds a small wrapping cost to every resolver call, so it's
    | best turned on selectively (e.g. behind a debug/admin guard) rather
    | than left on for a public production API.
    |
    | driver — 'apollo' (default): reports timings under `extensions.tracing`
    |   in the Apollo Tracing format. Nothing to configure beyond `enabled`.
    | driver — 'otel': exports real OpenTelemetry spans (root span per
    |   operation, one child per resolver) via `open-telemetry/api`'s global
    |   tracer provider instead — the app wires up its own OTel SDK/exporter
    |   the standard way. `extensions.tracing` is NOT added to the response
    |   in this mode; span data leaves via the OTel pipeline, not the body.
    |
    */
    'tracing' => [
        'enabled' => false,
        'driver'  => 'apollo',
        'otel'    => [
            'tracer_name' => 'laragraph',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Laravel Octane
    |--------------------------------------------------------------------------
    |
    | warm — Under Octane, keep Laragraph alive across requests so each worker
    |   compiles the schema and validates each document once. Octane
    |   otherwise discards Laragraph with every request's copy of the
    |   application. Type and field classes are then shared by all requests
    |   of a worker: keep per-request state out of them (use $context).
    |
    */
    'octane' => [
        'warm' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscriptions
    |--------------------------------------------------------------------------
    |
    | webonyx/graphql-php has no built-in subscription transport, so Laragraph
    | provides one: the initial subscription request registers a subscriber
    | (query + variables + the channel returned by the field's subscribe())
    | and returns a channel/subscriberId pair instead of data. Application
    | code then calls Laragraph::broadcast($channel, $payload) — typically
    | from inside a mutation or a model event listener — to re-execute every
    | registered subscriber's original query with $payload as the root value
    | and push the result to that subscriber's own private channel via
    | Laravel Broadcasting.
    |
    | enabled       — Set to true to accept subscription operations. When
    |                 false (default) they're rejected with a client error.
    | driver        — 'broadcast' pushes via Laravel Broadcasting (Reverb,
    |                 Pusher, ...); 'log' writes updates to the log instead,
    |                 useful for local development without a broadcast server;
    |                 'sse' serves updates over a plain HTTP Server-Sent
    |                 Events connection instead — no broadcaster or Echo
    |                 needed, just a stock EventSource/graphql-sse client —
    |                 see the 'sse' key below and docs/07-subscriptions.md.
    | cache_store   — Laravel cache store used to persist subscriber
    |                 registrations; null uses the application's default.
    | ttl           — Subscriber registration lifetime in seconds.
    | channel_prefix — Prefix for the private channel each subscriber is
    |                 pushed to: "{prefix}.{subscriberId}" (driver: broadcast).
    |
    | sse — Only used when driver is 'sse'. Each open connection holds one
    |   PHP-FPM/Octane worker for up to max_duration seconds — read
    |   docs/07-subscriptions.md's capacity note before using this driver
    |   for anything beyond a modest number of concurrent subscribers.
    |   max_duration      — Seconds before a connection self-closes; the
    |                        client's EventSource reconnects transparently.
    |   poll_interval_ms  — How often the open connection checks for a new
    |                        pending message.
    |   heartbeat_seconds — How often an SSE comment is sent on an otherwise
    |                        idle connection, so proxies/load balancers don't
    |                        time it out.
    |
    */
    'subscriptions' => [
        'enabled'        => false,
        'driver'         => 'broadcast',
        'cache_store'    => null,
        'ttl'            => 3600,
        'channel_prefix' => 'graphql-subscriber',

        // Register the private-channel rule that lets only a subscription's
        // owner listen to it. Set to false to define your own rule in
        // routes/channels.php for "{channel_prefix}.{subscriberId}".
        'authorize_channel' => true,

        // Where Laragraph::broadcastLater() queues subscription fan-out
        // (null: the application's default connection / queue).
        'queue' => [
            'connection' => null,
            'queue'      => null,
        ],

        'sse' => [
            'max_duration'      => 30,
            'poll_interval_ms'  => 500,
            'heartbeat_seconds' => 15,
        ],
    ],

];
