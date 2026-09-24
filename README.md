# Laragraph

**A modern, feature-rich GraphQL package for Laravel.**

[![Latest Version](https://img.shields.io/packagist/v/ayimdomnic/graph-ql-l5.3.svg)](https://packagist.org/packages/ayimdomnic/graph-ql-l5.3)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue)](https://www.php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-10%20|%2011%20|%2012%20|%2013-orange)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Laragraph gives Laravel developers a clean, expressive, **code-first** API for building GraphQL services — powered by [webonyx/graphql-php](https://github.com/webonyx/graphql-php).

📖 **[Read the developer guide](docs/README.md)**: a step-by-step explanation of every feature, from your first query to production.
🧪 **[Explore the example app](example/README.md)**: a complete API that uses every feature, with a test for each one.

---

## Features

| Capability | Status |
|---|---|
| Queries & Mutations | ✅ |
| Real-time Subscriptions (Laravel Broadcasting) | ✅ |
| Object / Input / Enum / Interface / Union types | ✅ |
| Native PHP enums as GraphQL enums | ✅ |
| Custom scalars (DateTime, Date, JSON, Upload) | ✅ |
| Built-in argument validation (Laravel rules) | ✅ |
| Per-field authorization | ✅ |
| N+1-safe Eloquent relation batching | ✅ |
| Relay cursor pagination + simple paginator | ✅ |
| Batched queries | ✅ |
| Automatic Persisted Queries + trusted-documents mode | ✅ |
| GraphQL-over-HTTP (`application/graphql-response+json`, no mutations over GET) | ✅ |
| Per-user response cache | ✅ |
| File uploads (multipart spec) | ✅ |
| Multiple named schemas | ✅ |
| Query complexity & depth limiting | ✅ |
| Introspection toggle | ✅ |
| Per-field tracing (Apollo Tracing format) | ✅ |
| GraphiQL browser IDE | ✅ |
| Artisan generators | ✅ |
| Auto-discovery, cacheable via `php artisan optimize` | ✅ |
| `php artisan about` integration + `laragraph:validate` deploy check | ✅ |
| PHPStan level 8, PER-CS, 100% line coverage | ✅ |

---

## Requirements

- PHP **8.2 – 8.5**
- Laravel **10 / 11 / 12 / 13** (Laravel 13 requires PHP 8.3+)

Every combination Laravel itself supports is tested in CI.

---

## Why Laragraph?

Laragraph is **code-first**: types, queries and mutations are plain PHP classes, so your IDE,
refactoring tools and PHPStan understand your whole API. On top of
[webonyx/graphql-php](https://github.com/webonyx/graphql-php) it adds the parts a Laravel team
otherwise builds by hand:

- **Laravel-native everything** — validation rules, policies and guards, Broadcasting for
  subscriptions, cache stores, `php artisan about`, `optimize`, and generators.
- **Performance by default** — N+1-safe Eloquent relation batching through the model's own eager
  loading, a per-user response cache, persisted queries and a cached discovery manifest.
- **Secure by default** — depth/complexity/alias limits, no mutations over GET, trusted-documents
  mode, and per-user cache partitioning so one user's data is never served to another.
- **Modern PHP** — native enums, readonly value objects, strict types throughout and no dynamic
  properties (deprecated since PHP 8.2).

---

## Installation

```bash
composer require ayimdomnic/laragraph
```

Laravel auto-discovers the package. Publish the config:

```bash
php artisan vendor:publish --tag=laragraph-config
```

---

## Quick Start

### 1. Create a Type

```bash
php artisan laragraph:make:type UserType
```

```php
// app/GraphQL/Types/UserType.php
use Ayimdomnic\Laragraph\Support\Type;
use GraphQL\Type\Definition\Type as GType;

class UserType extends Type
{
    protected array $attributes = [
        'name'        => 'User',
        'description' => 'A registered user.',
    ];

    public function fields(): array
    {
        return [
            'id'    => ['type' => GType::nonNull(GType::id())],
            'name'  => ['type' => GType::string()],
            'email' => ['type' => GType::string()],
        ];
    }
}
```

### 2. Create a Query

```bash
php artisan laragraph:make:query UsersQuery
```

```php
// app/GraphQL/Queries/UsersQuery.php
use Ayimdomnic\Laragraph\Support\Query;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class UsersQuery extends Query
{
    public function type(): Type
    {
        return Type::listOf(app('laragraph')->type('User'));
    }

    public function args(): array
    {
        return [
            'limit' => ['type' => Type::int(), 'defaultValue' => 10],
        ];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return \App\Models\User::limit($args['limit'])->get();
    }
}
```

### 3. Create a Mutation

```bash
php artisan laragraph:make:mutation CreateUserMutation
```

```php
// app/GraphQL/Mutations/CreateUserMutation.php
use Ayimdomnic\Laragraph\Support\Mutation;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class CreateUserMutation extends Mutation
{
    public function type(): Type
    {
        return app('laragraph')->type('User');
    }

    public function args(): array
    {
        return [
            'name'  => ['type' => Type::nonNull(Type::string())],
            'email' => ['type' => Type::nonNull(Type::string())],
        ];
    }

    public function rules(array $args = []): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
        ];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return \App\Models\User::create($args);
    }
}
```

### 4. Register in config/laragraph.php

```php
'types' => [
    'User' => \App\GraphQL\Types\UserType::class,
],

'schemas' => [
    'default' => [
        'query'    => ['users' => \App\GraphQL\Queries\UsersQuery::class],
        'mutation' => ['createUser' => \App\GraphQL\Mutations\CreateUserMutation::class],
    ],
],
```

### 5. Make requests

```
POST /graphql
Content-Type: application/json

{ "query": "{ users(limit: 5) { id name email } }" }
```

---

## Native PHP Enums

Register a backed or pure enum directly — no wrapper class needed:

```php
enum UserStatus: string
{
    case Active = 'active';

    #[\GraphQL\Type\Definition\Description('Suspended by a moderator.')]
    case Banned = 'banned';
}

// config/laragraph.php
'types' => ['UserStatus' => \App\Enums\UserStatus::class],
```

Resolvers return enum cases and receive cases for enum arguments. `#[Description]` and
`#[Deprecated]` attributes on the enum and its cases are exposed through introspection. An
`EnumType` subclass may also simply `return UserStatus::cases();` from `values()`.

Enums (and input, interface, union and scalar types) placed in `app/GraphQL/Types` are
auto-discovered — including subdirectories such as `app/GraphQL/Types/Billing/`.

---

## GraphiQL

Built-in browser IDE at `/graphql/graphiql`. By default (`'enabled' => null`) it is only served
while `app.debug` is on — never in production.

```php
'graphiql' => [
    'enabled'    => true,                           // always serve it…
    'middleware' => ['auth', 'can:viewGraphiql'],   // …but only to trusted users
],
```

---

## Artisan Generators

| Command | Creates |
|---|---|
| `laragraph:make:type UserType` | `app/GraphQL/Types/UserType.php` |
| `laragraph:make:query UsersQuery` | `app/GraphQL/Queries/UsersQuery.php` |
| `laragraph:make:mutation CreateUserMutation` | `app/GraphQL/Mutations/CreateUserMutation.php` |
| `laragraph:make:subscription UserCreatedSubscription` | `app/GraphQL/Subscriptions/UserCreatedSubscription.php` |
| `laragraph:make:input CreateUserInput` | `app/GraphQL/Types/Inputs/CreateUserInput.php` |
| `laragraph:scaffold User --with-crud` | Type, queries and CRUD mutations for a model — deny-by-default (see below) |
| `laragraph:schema:export --output=schema.graphql` | SDL for client code generation / schema diffing |

---

Scaffolded code is **deny-by-default**: every generated query and mutation calls
`Gate::allows()` for the matching policy ability (`viewAny`, `view`, `create`, `update`,
`delete`), so nothing is reachable until you write a policy, and attributes in the model's
`$hidden` list (passwords, tokens) are never added to the generated type.

---

## Deploying

```bash
php artisan optimize            # also runs laragraph:cache on Laravel 11.27+
php artisan laragraph:cache     # cache the discovery manifest (bootstrap/cache/laragraph.php)
php artisan laragraph:clear     # remove it (also part of optimize:clear)
php artisan laragraph:validate  # build every schema and run full GraphQL validation — fails the deploy on errors
php artisan about               # shows a Laragraph section: version, schemas, cache and feature status
```

Like Laravel's event and route caches, a cached manifest is not refreshed automatically — rerun
`laragraph:cache` (or `optimize`) when you add GraphQL classes.

---

## Pagination

### Relay Cursor Pagination

```php
use Ayimdomnic\Laragraph\Pagination\ConnectionType;

class UsersQuery extends Query
{
    public function type(): Type
    {
        return new ConnectionType('UserConnection', app('laragraph')->type('User'));
    }

    public function args(): array
    {
        return ConnectionType::args(); // first, after, last, before
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return ConnectionType::paginate(\App\Models\User::query(), $args);
    }
}
```

```graphql
{
  users(first: 10) {
    edges { cursor node { id name } }
    pageInfo { hasNextPage endCursor total }
  }
}
```

Pass `endCursor` as `after` to fetch the next page, or `startCursor` as `before` with `last` to
walk backwards; the page size may change between requests. Page sizes are capped by
`laragraph.pagination.max_per_page` (default `100`; `null` removes the cap). Eloquent builders,
query builders and relations are paged with exact offsets; any other object with Laravel's
`paginate()` signature supports page-aligned windows.

### Simple Offset Pagination

```php
return ConnectionType::simplePaginate(\App\Models\User::query(), $args);
// → { data, total, per_page, current_page, last_page }
```

---

## N+1-Safe Eloquent Relations

Every GraphQL request gets a fresh `DataLoaderRegistry` attached to `$context`. For hand-written batch loaders, extend `BatchResolver`:

```php
use Ayimdomnic\Laragraph\DataLoader\BatchResolver;

class UserLoader extends BatchResolver
{
    public function batch(array $keys): array
    {
        return User::whereIn('id', $keys)->get()->keyBy('id')->toArray();
    }
}

// In a resolver:
return $context->dataLoaders->get(UserLoader::class)->load($root->user_id);

// Works for any context type, including custom objects:
return DataLoaderRegistry::for($context)->get(UserLoader::class)->load($root->user_id);
```

For HTTP requests `$context` is an `Ayimdomnic\Laragraph\Http\GraphQLContext` — a regular
`Illuminate\Http\Request` (input, headers, `user()`, session all work) with declared slots for
Laragraph's per-request state, so no dynamic properties are ever added to Laravel's request.

For a plain Eloquent relation, skip the hand-written loader entirely — `Type::batchRelation()` batches it through the relation's own eager-loading machinery (the same code path `Model::with()` uses), so it works for `belongsTo`, `hasOne`, `hasMany`, `belongsToMany`, and morph relations alike:

```php
class PostType extends Type
{
    public function fields(): array
    {
        return [
            'id'       => GType::nonNull(GType::id()),
            'comments' => GType::listOf(app('laragraph')->type('Comment')),
        ];
    }

    protected function resolveCommentsField(mixed $root, array $args, mixed $context): mixed
    {
        return $this->batchRelation(Post::class, 'comments', $root, $context);
    }
}
```

Regardless of how many `Post` parents are in the result set, `comments` resolves in **one** query per request instead of one query per post. The relation is loaded onto the parent models your resolver already returned, so relations you eager-loaded yourself (`Post::with('comments')`) cost no query at all, and parents hidden by global scopes still resolve.

---

## Authorization

```php
public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
{
    return $context->user()?->isAdmin() ?? false;
}
```

`false` → `AuthorizationException` → `extensions.category = 'authorization'`.

Or delegate to a Laravel policy — return the policy class or the model it guards:

```php
public function policy(): ?string { return PostPolicy::class; } // or Post::class
public function policyAbility(): string { return 'viewAny'; }
```

Policies registered with the Gate go through it (so `Gate::before()` hooks apply); other policy
classes are called directly, honouring their own `before()`. Guests are denied unless the policy
method accepts a nullable user.

---

## Validation

```php
public function rules(array $args = []): array
{
    return ['email' => ['required', 'email']];
}
```

Errors appear in `extensions.validation`:

```json
{
  "errors": [{
    "message": "Validation failed.",
    "extensions": {
      "category": "validation",
      "validation": { "email": ["The email field is required."] }
    }
  }]
}
```

---

## Built-in Scalars

```php
'types' => [
    'DateTime' => \Ayimdomnic\Laragraph\Scalars\DateTimeType::class,
    'Date'     => \Ayimdomnic\Laragraph\Scalars\DateType::class,
    'JSON'     => \Ayimdomnic\Laragraph\Scalars\JsonType::class,
    'Upload'   => \Ayimdomnic\Laragraph\Scalars\UploadType::class,
],
```

`Date` accepts `YYYY-MM-DD` (parsed as midnight). `DateTime` accepts ISO-8601 with or without
fractional seconds (`2024-01-15T09:30:00.123Z`, as JavaScript's `toISOString()` produces), the SQL
format `2024-01-15 09:30:00`, and plain dates. Impossible values such as `2024-02-31` are rejected
rather than rolled over.

---

## Multiple Schemas

```php
'schemas' => [
    'default' => ['query' => [...], 'mutation' => [...]],
    'admin'   => ['query' => [...], 'mutation' => [...], 'middleware' => ['auth:api', 'admin']],
],
```

Endpoints: `POST /graphql` and `POST /graphql/admin`.

---

## Security

Secure by default — these are the shipped values:

```php
'security' => [
    'query_max_complexity'  => 500,
    'query_max_depth'       => 15,   // the standard introspection query needs 11
    'max_aliases'           => 30,   // blocks alias-flooding attacks
    'disable_introspection' => null, // null: disabled whenever app.debug is off
],
```

Set a limit to `null` to remove it, or `disable_introspection` to `true`/`false` to force it.

Each named schema only contains the types registered for it (globally or under its own
`types` key); an admin-only type is never visible through another schema's introspection.

See also [GraphQL over HTTP](#graphql-over-http) and [trusted documents](#persisted-queries).

---

## GraphQL over HTTP

Laragraph follows the [GraphQL-over-HTTP specification](https://graphql.github.io/graphql-over-http/):

- `GET` executes queries only; mutations over `GET` receive `405 Method Not Allowed` (they would
  otherwise be exploitable via CSRF).
- Clients sending `Accept: application/graphql-response+json` get that media type, with a `4xx`
  status when a request fails before execution (parse/validation errors). Plain
  `application/json` clients keep the traditional always-`200` behaviour.
- Request bodies may be `application/json`, `application/graphql`, form-encoded or multipart.
- Malformed requests (a non-string `query`, `variables` that are not an object, unparseable JSON,
  a non-object batch entry) get `400` with `extensions.code: BAD_REQUEST`; an unknown schema in the
  URL gets `404` with `SCHEMA_NOT_FOUND`.
- Requests rejected before execution carry a machine-readable `extensions.code`
  (`BAD_REQUEST`, `SCHEMA_NOT_FOUND`, `METHOD_NOT_ALLOWED`, `PERSISTED_QUERY_NOT_FOUND`,
  `PERSISTED_QUERY_REQUIRED`, …).

---

## Response Cache

```php
'cache' => [
    'response' => [
        'enabled' => true,
        'store'   => 'redis',
        'ttl'     => 60,
        'scope'   => 'user', // or 'global' when every caller gets identical data
    ],
],
```

Only `query` operations are cached — the operation type is read from the parsed document, so
comments or `operationName` cannot smuggle a mutation into the cache. With the default `user`
scope, entries are partitioned per authenticated user (guests share one partition). Invalidate
everything with `ResponseCache::flush()`.

---

## Persisted Queries

```php
'persisted_queries' => [
    'enabled' => true,
    'store'   => 'cache', // or 'array' with a static 'map'
    'apq'     => true,    // Automatic Persisted Queries registration
    'only'    => false,   // trusted-documents mode
],
```

- **Automatic Persisted Queries** — compatible with Apollo Client's persisted-queries link: the
  client sends a SHA-256 hash, and on `PersistedQueryNotFound` retries with the full query, which is
  then stored (hashes are verified). Works over `GET` too.
- **Trusted documents** — with `'only' => true`, only query text already stored under its hash is
  executed. Pre-populate the store at deploy time (or use the `array` store) to restrict your API
  to the operations your own clients ship.

---

## Batched Queries

```json
[
  { "query": "{ users { id } }" },
  { "query": "mutation { createUser(name: \"Alice\", email: \"a@b.com\") { id } }" }
]
```

---

## File Uploads

Follows the [GraphQL multipart request spec](https://github.com/jaydenseric/graphql-multipart-request-spec).

```php
'types' => ['Upload' => \Ayimdomnic\Laragraph\Scalars\UploadType::class],

// In mutation args:
'avatar' => ['type' => app('laragraph')->type('Upload')]

// In resolver — $args['avatar'] is \Illuminate\Http\UploadedFile
$path = $args['avatar']->store('avatars', 'public');
```

---

## Facade

```php
use Ayimdomnic\Laragraph\Facades\Laragraph;

$result = Laragraph::execute('{ users { id name } }');
$schema = Laragraph::schema('admin');
$type   = Laragraph::type('User');
```

---

## Subscriptions

webonyx/graphql-php has no subscription transport of its own, so Laragraph provides one on top of Laravel Broadcasting: the initial subscription request registers a subscriber and returns a channel; your app code later calls `Laragraph::broadcast()` to push a live update to every subscriber on that channel.

```php
'subscriptions' => ['enabled' => true],
```

```php
// app/GraphQL/Subscriptions/UserCreatedSubscription.php
use Ayimdomnic\Laragraph\Support\Subscription;

class UserCreatedSubscription extends Subscription
{
    public function type(): Type
    {
        return app('laragraph')->type('User');
    }

    public function subscribe(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'users'; // the channel clients subscribe to
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return $root; // $root is the payload passed to Laragraph::broadcast()
    }
}
```

Trigger an update from anywhere — typically at the end of a mutation:

```php
class CreateUserMutation extends Mutation
{
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $user = \App\Models\User::create($args);

        Laragraph::broadcast('users', $user);

        return $user;
    }
}
```

**Client flow:**

1. POST the subscription operation like any other query:
   ```json
   { "query": "subscription { userCreated { id name } }" }
   ```
   The response carries no data yet — instead:
   ```json
   { "data": { "userCreated": null }, "extensions": { "subscription": { "channel": "users", "subscriberId": "…" } } }
   ```
2. Listen for updates on that subscriber's private channel with [Laravel Echo](https://laravel.com/docs/broadcasting#client-side-installation):
   ```js
   Echo.private(`graphql-subscriber.${subscriberId}`)
       .listen('.GraphQLSubscriptionUpdate', (payload) => {
           console.log(payload.data.userCreated);
       });
   ```

Delivery uses whichever broadcast driver your app has configured (Reverb, Pusher, …) — Laragraph only decides the channel and payload shape.

**Queued fan-out.** `Laragraph::broadcast()` re-runs every subscriber's query before returning.
Use `Laragraph::broadcastLater('users', $user)` to do that on the queue instead
(`subscriptions.queue.connection` / `subscriptions.queue.queue`).

**Unsubscribing.** Clients cancel with `DELETE /graphql/subscriptions/{subscriberId}` (only the
subscription's owner may; anything else answers `404`), and server code can call
`Laragraph::unsubscribe($subscriberId)`. Subscriptions also expire after `subscriptions.ttl`.

**Security.** Each update is resolved *as the subscriber*: the subscriber's identity is stored when
they subscribe, and `Laragraph::broadcast()` re-runs their query authenticated as them — never as
whoever triggered the broadcast — so `authorize()`, policies and `auth()` behave exactly as on the
original request. Laragraph also registers the private-channel rule for
`graphql-subscriber.{subscriberId}`, admitting only the user who created the subscription (turn off
with `'subscriptions' => ['authorize_channel' => false]` to write your own in `routes/channels.php`).
Because updates use private channels, subscribers must be authenticated to receive them. Set `'subscriptions' => ['driver' => 'log']` to write updates to the log instead, useful for local development without a broadcast server.

---

## Tracing

Enable per-field resolver timing in the [Apollo Tracing](https://github.com/apollographql/apollo-tracing) format, understood out of the box by existing GraphQL tooling:

```php
'tracing' => ['enabled' => true],
```

```json
{
  "extensions": {
    "tracing": {
      "version": 1,
      "startTime": "2026-08-06T12:00:00.000Z",
      "endTime": "2026-08-06T12:00:00.004Z",
      "duration": 4200000,
      "execution": {
        "resolvers": [
          { "path": ["users", 0, "posts"], "parentType": "User", "fieldName": "posts", "returnType": "[Post]", "startOffset": 120000, "duration": 80000 }
        ]
      }
    }
  }
}
```

Every resolved field is recorded — root Query/Mutation/Subscription fields and nested Type fields alike. Leave this off in production unless you're actively debugging performance; it adds a small wrapping cost to every resolver call.

---

## Development

```bash
composer test       # PHPUnit
composer lint       # code style — PER-CS 2.0 via Laravel Pint (composer format to fix)
composer phpstan    # PHPStan / Larastan, level 8
composer refactor   # Rector (PHP 8.2 set, dead code, code quality, type declarations)
composer check      # all of the above, as CI runs them
```

CI runs the suite on every supported PHP × Laravel combination (including `--prefer-lowest`),
enforces 100% line coverage, and fails on any PHP deprecation.

---

## Contributing

Contributions, issues, and feature requests are welcome!

---

## License

MIT © [Odhiambo Dormnic](https://github.com/ayimdomnic)

