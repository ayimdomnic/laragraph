<?php

declare(strict_types=1);

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
    | error_message — Message returned when a field's authorizeWithContext()
    |   or policy check fails.
    |
    | Examples:
    |   'default_guard' => 'sanctum',
    |   'default_guard' => 'api',
    |
    */
    'auth' => [
        'default_guard' => null,
        'error_message' => 'Unauthorized.',
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
        'middleware' => ['web'],
        'methods' => ['GET', 'POST'],
        'input_without_namespace' => true,
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
                'currentUser' => \App\GraphQL\Queries\CurrentUserQuery::class,
                'user' => \App\GraphQL\Queries\UserQuery::class,
                'users' => \App\GraphQL\Queries\UsersQuery::class,
                'organization' => \App\GraphQL\Queries\OrganizationQuery::class,
                'organizations' => \App\GraphQL\Queries\OrganizationsQuery::class,
            ],
            'mutation' => [
                'register' => \App\GraphQL\Mutations\RegisterMutation::class,
                'createUser' => \App\GraphQL\Mutations\CreateUserMutation::class,
                'updateUser' => \App\GraphQL\Mutations\UpdateUserMutation::class,
                'login' => \App\GraphQL\Mutations\LoginMutation::class,
                'logout' => \App\GraphQL\Mutations\LogoutMutation::class,
            ],
            'subscription' => [
                // 'userCreated' => \App\GraphQL\Subscriptions\UserCreatedSubscription::class,
            ],
            'middleware' => ['web'],
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
        'DateTime' => \Ayimdomnic\Laragraph\Scalars\DateTimeType::class,
        'Date'     => \Ayimdomnic\Laragraph\Scalars\DateType::class,
        'JSON'     => \Ayimdomnic\Laragraph\Scalars\JsonType::class,
        'Upload'   => \Ayimdomnic\Laragraph\Scalars\UploadType::class,
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
    'error_formatter' => [\Ayimdomnic\Laragraph\Laragraph::class, 'formatError'],

    /*
    |--------------------------------------------------------------------------
    | Errors Handler
    |--------------------------------------------------------------------------
    |
    | Called once with the full array of errors and the error formatter. You
    | may use this to log, filter, or transform the errors array.
    |
    */
    'errors_handler' => [\Ayimdomnic\Laragraph\Laragraph::class, 'handleErrors'],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Set limits on query complexity and depth to protect your API from
    | overly expensive queries. null disables the limit.
    |
    */
    'security' => [
        'query_max_complexity'  => null,
        'query_max_depth'       => null,
        'disable_introspection' => false,
        'max_aliases'           => null,
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
        'per_page' => 15,
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
    | Cache is keyed by the query string + variables + operation name, so
    | different variable combinations produce separate entries.
    |
    | To invalidate from code:
    |   \Ayimdomnic\Laragraph\Performance\ResponseCache::forget($key)
    |
    */
    'cache' => [
        'response' => [
            'enabled' => false,
            'store'   => 'default',
            'ttl'     => 60,
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
    */
    'extensions' => [
        'request_id'   => false,
        'query_timing' => false,
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
    | Enable the built-in GraphiQL browser IDE at /graphql/graphiql.
    |
    */
    'graphiql' => [
        'enabled'    => true,
        'middleware' => [],
        'title'      => 'Laragraph — GraphiQL',
    ],

];
