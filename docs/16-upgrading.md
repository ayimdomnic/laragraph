# 16. Upgrading

## From 3.1 to the next release

This release modernises the package for PHP 8.2–8.5 and Laravel 10–13, and makes its defaults
secure. Most applications upgrade without code changes, but **some defaults change what clients
see**. Read each section and decide whether to keep the new behaviour (recommended) or restore the
old one through config.

### Requirements

- PHP **8.2** or newer (8.3 for Laravel 13).
- Laravel **10, 11, 12 or 13**.
- webonyx/graphql-php **15**.

Republish or compare your config: `php artisan vendor:publish --tag=laragraph-config --force`
(back up your changes first).

### Secure defaults

| Setting | 3.1 | Now | To restore |
|---|---|---|---|
| `graphiql.enabled` | `true` | `null`, meaning only while `APP_DEBUG=true` | `true` (and add `graphiql.middleware`) |
| `security.disable_introspection` | `false` | `null`, meaning off while `APP_DEBUG=false` | `false` |
| `security.query_max_depth` | `null` | `15` | `null` |
| `security.query_max_complexity` | `null` | `500` | `null` |
| `security.max_aliases` | `null` | `30` | `null` |
| `pagination.max_per_page` | *(none)* | `100` | `null` |

**Check:** tools that introspect production (code generators, schema registries) now need
`disable_introspection => false`, or better, a schema file from `laragraph:schema:export`.
Deeply nested or very wide client queries may now exceed the limits. Raise them, or give the
expensive fields a proper `complexity()`.

### Errors hide internal messages

Exceptions that aren't client-safe used to send their message to clients, including SQL
from database errors. They now read `"Internal server error"` (category `internal`). The real
message is in `extensions.debugMessage` while `APP_DEBUG=true`.

**Check:** if clients relied on a message from an ordinary exception, throw
`GraphQL\Error\Error` (or a `ClientAware` exception) instead.

### Response cache is per user

`cache.response` has a new `scope` key, `'user'` by default: each user gets separate entries, and
guests share one partition. Previously one cached response was served to *everyone*, including
responses that depended on the user. Set `'scope' => 'global'` only if every response really is
identical for all callers. Whether an operation is cacheable is now read from the parsed
document. The old check only looked at how the text started, so a mutation after a comment, or a
mutation picked by `operationName` from a document with several operations, could be cached.
The new `ResponseCache::flush()` invalidates every entry at once, on every cache store.

### The resolver context is a `GraphQLContext`

`$context` in resolvers is now `Ayimdomnic\Laragraph\Http\GraphQLContext`, a subclass of
`Illuminate\Http\Request`. It used to be the framework request with dynamic properties added,
which PHP 8.2 deprecates. `$context->user()`, `$context->input()` and `$context->dataLoaders`
keep working. If you type-hinted `Request $context`, it still matches.

Prefer `DataLoaderRegistry::for($context)` in new code. It works for any context type.

### HTTP behaviour follows GraphQL over HTTP

| Situation | 3.1 | Now |
|---|---|---|
| Mutation sent with GET | executed | `405 Method Not Allowed`, `Allow: POST` |
| Malformed body or parameters | errors deep inside execution | `400`, code `BAD_REQUEST` |
| Unknown schema in `/graphql/{schema}` | an uncaught exception (500) | `404`, code `SCHEMA_NOT_FOUND` |
| `Accept: application/graphql-response+json` | ignored | honoured, with `400` for requests that fail before execution |
| APQ hash not yet stored | custom message | `PersistedQueryNotFound`, as Apollo clients expect |

Clients that send `application/json` keep getting `200` for GraphQL errors.

### Per-schema middleware and methods now apply

`schemas.{name}.middleware` and `schemas.{name}.method` used to be ignored. They now guard that
schema's endpoint. **Check** that the middleware you configured there is what you want, because it now runs.

### Schemas are isolated

Types registered for one schema are no longer visible on other schemas, not even through
`__type(name:)`. If a public schema depended on a type that was registered only in another
schema's `types`, move it to the global `types` or the discovery directory.

### Pagination

Relay cursors now follow the specification: `after` works past the first page, `last`/`before`
page backwards, and `pageInfo.hasPreviousPage` is correct. Page sizes are capped at
`pagination.max_per_page`. Use `ConnectionType::make()` instead of `new ConnectionType()` when
several fields return the same connection, and `PageInfo` is now a single shared type.

### Authorization

- `policy()` now really calls the policy: through the Gate for a model or registered policy, or
  directly for other policy classes, with `before()` hooks honoured. Guests are denied unless the
  policy method accepts a nullable user.
- `guards()` uses the **first guard that authenticates** the request, not always the first one
  listed.

### Subscriptions

- Updates are resolved **as the subscriber** (their user, their permissions), no longer as
  whoever called `broadcast()`.
- Laragraph registers the channel rule for `{channel_prefix}.{subscriberId}`, so only the subscriber
  can listen. Turn it off with `subscriptions.authorize_channel => false`.
- New: `Laragraph::broadcastLater()`, `Laragraph::unsubscribe()` and
  `DELETE /graphql/subscriptions/{id}`.
- A subscription field's type must be **nullable** (it resolves to `null` when the client subscribes).

### Scalars

`Date` and `DateTime` parse strictly. Invalid dates (`2026-02-30`) and unexpected formats are
rejected instead of being silently adjusted.

### Discovery

Discovery now scans subdirectories: `app/GraphQL/Types/Inputs/PostInput.php` is found. Classes
you deliberately kept in a subfolder so they *wouldn't* be registered must move out of the
discovery directories.

### Batching

`batching.max_operations => 0` now means "no limit", as documented; it used to reject every
batch. Batched operations get the same persisted-query and subscription handling as single
requests, and each gets its own DataLoaders.

### Generators

- `laragraph:make:input` now writes to `app/GraphQL/Types/Inputs` (namespace
  `App\GraphQL\Types\Inputs`) so discovery finds the class. It used to write to
  `App\GraphQL\Inputs`, which wasn't discovered. Existing classes there keep working if you register
  them in `types`.
- `laragraph:scaffold` generates deny-by-default operations that check policies, and never
  exposes attributes listed in `$hidden`.

### Removed config keys

| Key | Why |
|---|---|
| `auth.error_message` | Never used. Authorization errors name the denied field. |
| `route.input_without_namespace` | Never used |

Remove them from your published config (leaving them in is harmless).

### Lazy schema building

Schemas are now built lazily: root field classes and types are instantiated when a request first
needs them, not when the schema is created. Two things follow:

- An error in a field or type class (such as a type name that isn't registered) surfaces when that
  field is first used rather than on every request. Run `php artisan laragraph:validate`
  in CI and on deploy. It builds and checks the whole schema.
- Keep constructors of type and field classes free of side effects. They may run later than before,
  or not at all.

### Validation

- Documents that pass the document-only rules are remembered per worker (see
  [Performance](11-performance-and-caching.md#documents-are-parsed-once-and-validated-once)).
  Query complexity and your own `validation.rules` still run on every execution.
- The protected `Laragraph::buildValidationRules()` was replaced by `partitionValidationRules()`,
  which returns the document-only rules and the per-execution rules separately.

### Default field resolver

Fields without a resolver use `Ayimdomnic\Laragraph\Support\DefaultFieldResolver`, which reads
Eloquent attributes once instead of twice. For arrays, `ArrayAccess`, plain objects and `Closure`
values it behaves exactly like webonyx's default resolver.

### Events

`QueryExecuted` and `QueryError` now fire for response-cache hits too, and `QueryExecuted` has a new
`cached` property. `errors_handler` is now honoured.
