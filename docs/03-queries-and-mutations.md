# 3. Queries & mutations

Every root field of your API is a class. Queries extend `Ayimdomnic\Laragraph\Support\Query`, mutations extend
`Ayimdomnic\Laragraph\Support\Mutation`, and both share one base class, `Support\Field`. The two
differ only in which root type they are added to, so everything in this chapter applies to both.

## Anatomy of a field

A field class needs only `type()` and `resolve()`. The other methods are optional hooks:

| Method | Default | Purpose |
|---|---|---|
| `type(): Type` | *(required)* | The GraphQL return type |
| `resolve($root, $args, $context, $info)` | *(required)* | Produces the value |
| `args(): array` | `[]` | Argument definitions |
| `description(): ?string` | `null` | Shown in introspection and GraphiQL |
| `authorize($root, $args, $context, $info): bool` | `true` | Simple yes/no access check |
| `authorizeWithContext(AuthorizationContext $ctx): bool` | `true` | Guard-aware access check |
| `guards(): array` | `[]` | Guards that may authenticate this field |
| `policy(): ?string` / `policyAbility(): string` | `null` / `'view'` | Policy shortcut |
| `rules(array $args): array` | `[]` | Laravel validation rules for `$args` |
| `messages(): array` / `attributes(): array` | `[]` | Custom validation messages and attribute names |
| `middleware(): array` | `[]` | Field middleware wrapped around `resolve()` |
| `complexity(): ?int` | `null` | Cost used by the complexity limit |
| `deprecated(): ?string` | `null` | Deprecation reason |

Authorization is covered in [chapter 4](04-authentication-and-authorization.md). This chapter covers
the rest.

### Field names

Discovered classes are named after the class, with the `Query`/`Mutation`/`Subscription` suffix
removed and the first letter lower-cased:

| Class | Field |
|---|---|
| `UsersQuery` | `users` |
| `CreatePostMutation` | `createPost` |
| `PostPublishedSubscription` | `postPublished` |

To use a different name, register the class under an explicit key in the schema config
(see [Multiple schemas](09-multiple-schemas.md)):

```php
'schemas' => [
    'default' => [
        'query' => ['viewer' => App\GraphQL\Queries\MeQuery::class],
    ],
],
```

## Arguments

`args()` returns webonyx argument definitions keyed by name:

```php
// example/app/GraphQL/Queries/SearchQuery.php
public function args(): array
{
    return [
        'term'  => ['type' => Type::nonNull(Type::string())],
        'limit' => ['type' => Type::int(), 'defaultValue' => 10],
    ];
}
```

Each definition may contain `type`, `description`, `defaultValue` and `deprecationReason`.
The resolver receives the arguments as an array after webonyx has coerced them:

- IDs arrive as strings and `Int` as integers.
- Enum arguments arrive as their internal value. For a native PHP enum that is the **enum case**
  (`App\Enums\UserRole::Admin`), not the string `"ADMIN"`. Eloquent casts accept the case directly,
  as in `UsersQuery`: `$query->where('role', $args['role'])`.
- Input objects arrive as nested arrays: `$args['input']['title']`.
- Arguments the client omitted that have no `defaultValue` are **absent** from the array.
  Check them with `isset()`/`array_key_exists()`.
- Uploads arrive as `Illuminate\Http\UploadedFile` (see [The HTTP API](08-http-api.md#file-uploads)).

To add standard pagination arguments to your own, spread them:
`[...ConnectionType::args(), 'status' => [...]]` (see [Pagination](06-pagination.md)).

## Resolvers

```php
public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
```

- **`$root`** is `null` for queries and mutations. For subscriptions it is the broadcast payload
  (see [Subscriptions](07-subscriptions.md)).
- **`$args`** holds the coerced arguments, as described above.
- **`$context`** is the execution context, described below.
- **`$info`** is webonyx's `ResolveInfo`: the field name, parent type, requested selection set, path
  and so on. Use `$info->getFieldSelection()` to see which sub-fields the client asked for.

A resolver may return:

- a model, an array, an object or a scalar, which the return type's fields read from;
- a collection or an array, for list types;
- `null`, for nullable types;
- a **promise**, for example the result of `batchRelation()` or `$loader->load()`, which is
  resolved once the whole batch runs (see [Relations & DataLoaders](05-relations-and-dataloaders.md)).

## The context

For HTTP requests, `$context` is an `Ayimdomnic\Laragraph\Http\GraphQLContext`, a **subclass of
`Illuminate\Http\Request`**. Everything you know from a controller works:

```php
$context->user();              // the authenticated user (default guard)
$context->user('sanctum');     // a specific guard
$context->header('X-Tenant');
$context->ip();
$context->route();
```

It also carries Laragraph's per-request state in declared properties:

| Property | What it is |
|---|---|
| `dataLoaders` | The request's `DataLoaderRegistry` |
| `subscribing` | `true` only while a subscription request is registering |
| `subscriptionRegistrar` | Internal: captures the channel a subscription registers |

Access the registry with `DataLoaderRegistry::for($context)`. This works whatever the context is:
a request, an array or a custom object, whether you [execute from code](#executing-from-code) or not.

## Validation

`rules()` returns ordinary Laravel validation rules. They are applied to `$args` **after
authorization and before the resolver**:

```php
// example/app/GraphQL/Mutations/RegisterMutation.php
public function rules(array $args = []): array
{
    return [
        'name'             => ['required', 'string', 'max:255'],
        'email'            => ['required', 'email', 'max:255', 'unique:users,email'],
        'password'         => ['required', 'string', 'min:8', 'confirmed'],
        'organizationSlug' => ['required', 'exists:organizations,slug'],
    ];
}

public function messages(): array
{
    return ['organizationSlug.exists' => 'There is no organization with that slug.'];
}
```

Some tips:

- **Nested input** uses dot notation: `'input.title' => ['required', 'min:3']`. Use `attributes()`
  so messages read "The title field…" instead of "The input.title field…", as `CreatePostMutation` does.
- **`confirmed`** looks for `{field}_confirmation`, so declare that argument (RegisterMutation declares
  `password_confirmation`).
- **Conditional rules** can inspect `$args`, because `rules()` receives them. `OrganizationQuery` uses
  `required_without` to accept either `id` or `slug`.
- **Rule objects** work too: `Rule::unique('users', 'email')->ignore(auth()->id())`
  (see `UpdateProfileMutation`).
- **GraphQL types come first.** A non-null argument that is missing is rejected by GraphQL itself
  before any Laravel rule runs, so rules are for everything the type system can't express:
  formats, lengths, uniqueness, relationships between arguments.

A failed validation produces one error with category `validation` and the messages keyed by
argument:

```json
{
  "errors": [{
    "message": "Validation failed.",
    "path": ["register"],
    "extensions": {
      "category": "validation",
      "validation": {
        "email": ["The email has already been taken."],
        "password": ["The password field confirmation does not match."]
      }
    }
  }],
  "data": { "register": null }
}
```

Validation on the fields of an object *type*, such as a `Post.excerpt(length:)` argument, is not
automatic. Check such arguments in the field resolver, or clamp them as `PostType` does.

## Errors

Every error is formatted by `Laragraph::formatError()` (configurable, see below) and gets an
`extensions.category`:

| Category | Raised by | Message shown to clients |
|---|---|---|
| `validation` | `rules()` failing | `Validation failed.` plus `extensions.code`/`extensions.validation` |
| `authorization` | `authorize()`, `authorizeWithContext()` or `policy()` denying | `You are not authorized to access UserQuery.` / `Policy check failed for UsersQuery.` |
| `application` (or your own) | A `GraphQLException` you throw | Your translated message plus `extensions.code` |
| `graphql` | Syntax and validation errors in the document, and any `GraphQL\Error\Error` you throw | Your message |
| `internal` | Any other exception | `Internal server error` |

The last row is the important one. **Exceptions from your code are hidden by default**: a
`QueryException` exposes neither SQL nor the table name. To send a message to the client, throw a
[`GraphQLException`](17-error-handling-and-localization.md) — the recommended way to report a
domain/business error, since it's always client-safe, carries a machine-readable `extensions.code`,
and its message is resolved through Laravel's translator:

```php
// example/app/GraphQL/Exceptions/InvalidCredentialsException.php
class InvalidCredentialsException extends GraphQLException
{
    public function __construct(array $replace = [], array $extra = [])
    {
        parent::__construct(key: 'errors.invalid_credentials', errorCode: 'INVALID_CREDENTIALS', replace: $replace, extra: $extra);
    }
}
```

```php
// example/app/GraphQL/Mutations/LoginMutation.php
if (! is_string($token)) {
    throw new InvalidCredentialsException();
}
```

Generate one with `php artisan laragraph:make:exception`. Any exception implementing graphql-php's
`GraphQL\Error\ClientAware` (message shown) and `GraphQL\Error\ProvidesExtensions` (structured
`extensions`) — `GraphQLException`, `ValidationException`, `AuthorizationException`, or your own —
is picked up by `formatError()` automatically; there's no `instanceof` chain to maintain, so a
plain `GraphQL\Error\Error` throw still works exactly as before too.

While `APP_DEBUG=true`, every error also carries `extensions.debugMessage` and a short `trace`, so
you can see the real exception while developing. Never run production with debug on.

Errors don't abort the whole operation. The failing field becomes `null` (the null propagates up to
the nearest nullable parent) and the other fields still resolve. The HTTP status stays `200` for
`application/json` responses. See [The HTTP API](08-http-api.md#responses-and-status-codes) for when it isn't.

### Custom error formatting

Most error codes and extensions belong on the exception itself (see above) rather than in a custom
formatter. Reach for a formatter override when you need to react to an exception you don't control
— a third-party package's, or the framework's own:

```php
// config/laragraph.php
'error_formatter' => [App\GraphQL\ErrorFormatter::class, 'format'],
```

```php
namespace App\GraphQL;

use Ayimdomnic\Laragraph\Laragraph;
use GraphQL\Error\Error;

class ErrorFormatter
{
    public static function format(Error $error): array
    {
        $formatted = Laragraph::formatError($error);

        if ($error->getPrevious() instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            $formatted['message'] = 'Not found.';
            $formatted['extensions']['category'] = 'not_found';
        }

        return $formatted;
    }
}
```

`errors_handler` receives the whole list of errors and the formatter. Use it to report or filter
errors as a group. Use callables that can be serialised, `[Class::class, 'method']`, so that
`php artisan config:cache` keeps working.

## Field middleware

Middleware wraps a single field's resolver. It runs **after** authorization and validation have
passed, so a rate limiter never counts requests that were rejected anyway.

```php
// example/app/GraphQL/Mutations/LoginMutation.php
use Ayimdomnic\Laragraph\Middleware\ThrottleMiddleware;

public function middleware(): array
{
    return [new ThrottleMiddleware(maxAttempts: 5, decaySeconds: 60)];
}
```

Two middleware ship with the package:

- **`ThrottleMiddleware(maxAttempts, decaySeconds)`** uses Laravel's `RateLimiter`. The key combines
  the parent type, the field and the user id (or the IP for guests), so `Mutation.login` and
  `Query.user` are counted separately. The sixth login attempt within a minute fails with
  "Too many requests for field [login]. Retry after 42s."
- **`LoggingMiddleware`** logs each resolution and its duration at `debug` level to
  `logging.channel`.

Write your own by implementing `FieldMiddlewareInterface`:

```php
namespace App\GraphQL\Middleware;

use Ayimdomnic\Laragraph\Middleware\FieldMiddlewareInterface;
use GraphQL\Type\Definition\ResolveInfo;

class AuditMiddleware implements FieldMiddlewareInterface
{
    public function handle(mixed $root, array $args, mixed $context, ResolveInfo $info, callable $next): mixed
    {
        $result = $next();          // run the rest of the stack and the resolver

        activity()->log("{$info->parentType->name}.{$info->fieldName}");

        return $result;             // or return early without calling $next()
    }
}
```

Return either instances or class names from `middleware()`; class names are resolved from the
container. Middleware listed in the `middleware` config key runs on **every** root field, before the
field's own middleware. The first entry in the list is the outermost.

Field middleware is not HTTP middleware. To protect the whole endpoint, or a whole schema, use
[route middleware](09-multiple-schemas.md#per-schema-middleware-and-methods).

## Complexity

With `security.query_max_complexity` set (the default is 500), each requested field costs 1 point plus the
cost of its children, and documents over the limit are rejected before execution. Raise a
root field's own cost with `complexity()`:

```php
// example/app/GraphQL/Queries/SearchQuery.php — a LIKE search over three tables
public function complexity(): ?int
{
    return 20;
}
```

See [Security](10-security.md#query-limits).

## Deprecation

```php
// example/app/GraphQL/Queries/CurrentUserQuery.php
public function deprecated(): ?string
{
    return 'Use `me` instead.';
}
```

The field keeps working, but introspection reports `isDeprecated: true` with your reason, and GraphiQL
strikes it through. Fields of types are deprecated with `'deprecationReason' => '…'` in the field
definition (see `Post.summary` in the example and [Types](02-types.md)).

## Mutations: good habits

- **Return the changed object.** `createPost` returns the new `Post`, so clients update their cache
  from the response instead of refetching.
- **Take identity from the context, not from arguments.** `updateProfile` has no `id` argument, so
  it can only ever change the caller's own profile.
- **Use an input type** when a mutation has more than a few arguments (`createPost(input: PostInput!)`).
- **Mutations in one document run in order.** Root query fields may resolve in any order; root
  mutation fields run one after another, as the GraphQL spec requires.
- **Treat "not found" and "forbidden" the same.** `DeletePostMutation::authorize()` returns `false`
  for both, so nobody can find out which post ids exist.

## Executing from code

To run an operation without HTTP, from a job, a command or a test, call `Laragraph::execute()`:

```php
$result = Laragraph::execute(
    query: 'query ($id: ID!) { post(id: $id) { title } }',
    context: request(),             // or null: request() is used
    variables: ['id' => 1],
    operationName: null,
    schemaName: 'default',
);

$result['data']['post']['title'];
```

It returns the serialised result array and goes through everything an HTTP request does:
validation rules, response cache, extensions and events. `Laragraph::executeQuery()` returns webonyx's
raw `ExecutionResult` instead and skips the cache, extensions and events.
