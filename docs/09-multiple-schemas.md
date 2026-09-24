# 9. Multiple schemas

One application can serve several independent GraphQL schemas, each with its own endpoint,
root fields, types, HTTP middleware and allowed methods. The usual reason is to separate a public API from an
internal or admin API, so that admin-only fields don't show up in the public schema at all.

## Defining schemas

```php
// example/config/laragraph.php
use App\GraphQL\Admin\AdminStatsType;
use App\GraphQL\Admin\StatsQuery;

'default_schema' => 'default',

'schemas' => [
    // Served at /graphql. Its fields come from auto-discovery.
    'default' => [],

    // Served at /graphql/admin.
    'admin' => [
        'query' => [
            'stats' => StatsQuery::class,
        ],
        'types' => [
            'AdminStats' => AdminStatsType::class,
        ],
        'middleware' => ['auth:api', 'can:access-admin-api'],
        'method'     => ['POST'],
    ],
],
```

Every schema accepts these keys:

| Key | Meaning |
|---|---|
| `query`, `mutation`, `subscription` | `fieldName => class` maps of root fields |
| `types` | `Alias => class` types that belong only to this schema |
| `middleware` | Route middleware for this schema's endpoint, added after `route.middleware` |
| `method` | Allowed HTTP methods for this endpoint (default: `route.methods`) |

The default schema is served at `/graphql`. Every other schema is served at `/graphql/{name}`. Names may
contain letters, digits, `-` and `_`.

## What each schema contains

A schema's root fields are **the discovered classes plus its own `query`/`mutation`/`subscription`
entries**. An explicit entry with the same name as a discovered field replaces it.

> **Discovery applies to every schema.** Everything under `app/GraphQL/Queries`,
> `Mutations`, `Subscriptions` and `Types` becomes part of *every* schema, the admin schema
> included. That's usually what you want: admins can also use `me` and `posts`. It also means
> **schema-only classes must live outside the discovered directories**. The example keeps them in
> `app/GraphQL/Admin`, which discovery doesn't scan, and lists them in `schemas.admin`.

A schema's types are the global `types` from the config, the discovered types and the schema's own `types`,
plus every type reachable from its fields.

### Isolation

A type that's registered only for the admin schema is **invisible** on every other schema:
`__type(name: "AdminStats")` returns `null` on `/graphql`, and a query naming it fails
validation. Laragraph resolves type names against each schema's own type map, not against the
shared registry. The example asserts this in
[`SecurityTest`](../example/tests/Feature/GraphQL/SecurityTest.php).

Reference schema-only types only from that schema's own fields. `Laragraph::type('AdminStats')`
inside a *public* type would pull it into the public schema through that field.

## Per-schema middleware and methods

`middleware` and `method` apply to the schema's own route, so the admin API above:

- rejects guests with **401** (`auth:api`) and non-admins with **403** (`can:access-admin-api`) before
  any GraphQL work happens;
- accepts only POST, so `GET /graphql/admin?query=…` gets **405**.

```php
// example/app/Providers/AppServiceProvider.php
Gate::define('access-admin-api', fn (User $user): bool => $user->isAdmin());
```

Middleware errors are rendered by Laravel's exception handler. The example renders them as JSON for
`graphql/*` (in `bootstrap/app.php`), so API clients get `{"message": "Unauthenticated."}` instead of
a redirect to a login page:

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*', 'graphql', 'graphql/*'),
    );
})
```

Middleware also applies to the default schema: `schemas.default.middleware` guards `/graphql`
itself.

## Using a schema from code

```php
Laragraph::execute('{ stats { members } }', schemaName: 'admin');
Laragraph::schema('admin');        // the compiled GraphQL\Type\Schema
```

The artisan tools take the schema name as an option:

```bash
php artisan laragraph:validate --schema=admin
php artisan laragraph:schema:export --schema=admin --output=storage/admin.graphql
```

## Things to know

- Unknown schema names get **404** with `SCHEMA_NOT_FOUND`.
- A schema named `graphiql` would clash with the GraphiQL route. While GraphiQL is served, a schema
  with that name isn't routed, so choose another name.
- GraphiQL talks to the default schema.
- Each schema is compiled once per process, on first use. Run `laragraph:validate` in CI so a
  broken schema fails the build, not the first request.
