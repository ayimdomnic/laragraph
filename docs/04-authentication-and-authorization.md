# 4. Authentication & authorization

Laragraph doesn't have its own auth system. It uses Laravel's guards, gates and policies, and adds
hooks that run them **before** your resolver.

## Authentication

A GraphQL request is an ordinary Laravel request, so any guard works: session cookies, Sanctum,
Passport or JWT. The only question is which guard resolves the user.

**Recommended:** make your API guard Laravel's default guard, so `$context->user()`, `auth()->user()`,
`Gate::allows()` and Laragraph all agree on the user:

```dotenv
# .env: the example uses php-open-source-saver/jwt-auth
AUTH_GUARD=api
```

```php
// config/laragraph.php
'auth' => [
    'default_guard' => 'api',   // null = Laravel's default guard
],
```

`auth.default_guard` is the guard Laragraph uses for `authorizeWithContext()`, `policy()` and the
per-user response cache. It does **not** change Laravel's default guard: `$context->user()` and
`Gate::allows()` still use `auth.defaults.guard`. If the two differ, use `$context->user('api')` and
`Gate::forUser($user)` explicitly, or route everything through the context methods described below.

### Guests

GraphQL has one endpoint, so you usually **can't** put `auth` middleware in front of it: `login`
and `register` must stay reachable for guests. Instead, leave the route open and protect fields
one by one. To require authentication for an entire schema, see
[per-schema middleware](09-multiple-schemas.md#per-schema-middleware-and-methods).

Clients send the token like any API client does:

```http
POST /graphql
Authorization: Bearer eyJ0eXAiOiJKV1Qi…
Content-Type: application/json

{"query": "{ me { name } }"}
```

With Sanctum SPA authentication (cookies), add Sanctum's `EnsureFrontendRequestsAreStateful`
through `route.middleware`, and send the CSRF token as you would to any other stateful route.

### A login mutation

```php
// example/app/GraphQL/Mutations/LoginMutation.php
public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
{
    $token = JWTAuth::attempt(['email' => $args['email'], 'password' => $args['password']]);

    if (! is_string($token)) {
        throw new InvalidCredentialsException();   // GraphQLException — client-safe and localized
    }

    return [
        'token'     => $token,
        'expiresIn' => JWTAuth::factory()->getTTL() * 60,
        'user'      => JWTAuth::user(),
    ];
}

public function middleware(): array
{
    return [new ThrottleMiddleware(maxAttempts: 5, decaySeconds: 60)];   // brute-force protection
}
```

With Sanctum, return `$user->createToken('api')->plainTextToken` instead.

## Authorization hooks

Every root field runs these checks, in this order, before validation and the resolver. The first
one that fails stops the field with an error in category `authorization`:

```
1. authorize($root, $args, $context, $info)   // plain boolean
2. authorizeWithContext(AuthorizationContext)  // guard-aware
3. policy() + policyAbility()                  // Laravel policy shortcut
4. rules()                                     // validation
5. middleware() → resolve()
```

Because authorization runs first, a guest can't use validation errors to learn anything,
such as which emails exist through a `unique` rule.

### 1. `authorize()`: the everyday choice

It receives the arguments, so it can load the record being accessed and ask its policy:

```php
// example/app/GraphQL/Queries/UserQuery.php
public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
{
    $user = User::find($args['id']);

    return $user !== null && Gate::allows('view', $user);   // UserPolicy::view()
}
```

A denied request returns:

```json
{
  "errors": [{
    "message": "You are not authorized to access UserQuery.",
    "path": ["user"],
    "extensions": { "category": "authorization" }
  }],
  "data": { "user": null }
}
```

Returning `false` both for "doesn't exist" and "not allowed" is deliberate: clients can't tell
them apart, so they can't probe which ids exist.

### 2. `authorizeWithContext()`: guard-aware checks

Use this when a field must be authenticated by a specific guard, or when your app has several
(web sessions and API tokens, for example):

```php
// example/app/GraphQL/Mutations/LogoutMutation.php
public function guards(): array
{
    return ['api'];                 // which guard(s) may authenticate this field
}

public function authorizeWithContext(AuthorizationContext $ctx): bool
{
    return $ctx->check();
}
```

`AuthorizationContext` gives you:

| Method | Returns |
|---|---|
| `check()` | Whether the request is authenticated on the chosen guard |
| `user()` | The user on that guard, or `null` |
| `can($ability, $arguments = [])` | A Gate check **for that user**; `false` for guests |
| `guardName()` / `guard()` | The chosen guard |
| `request()` | The HTTP request |

**Which guard is chosen?** If `guards()` lists several, the **first one that authenticates the
request** is used, so `['sanctum', 'api']` accepts either kind of token. If none authenticates, the
first one listed is used, and the checks run (and fail) as a guest. If `guards()` is empty,
`auth.default_guard` is used, and if that is null, Laravel's default guard.

### 3. `policy()`: the declarative shortcut

For checks that don't depend on a particular record (`viewAny`, `create`), name the model (or
policy) and the ability:

```php
// example/app/GraphQL/Queries/UsersQuery.php: only admins may list users
public function policy(): ?string
{
    return User::class;             // or UserPolicy::class
}

public function policyAbility(): string
{
    return 'viewAny';               // default: 'view'
}
```

How `policy()` is resolved:

1. **A model class with a registered or auto-discovered policy**, or **a policy class registered
   with the Gate**, goes through the Gate. `Gate::before()` hooks and the policy's own `before()`
   apply, exactly as with `$user->can()`.
2. **Any other policy class** is resolved from the container and its ability method is called
   directly, honouring its `before()` method.
3. **A string that isn't a class** is passed to the Gate as the ability's argument, for abilities
   defined with `Gate::define()`.

Guests are denied unless the policy method's user parameter is nullable (`?User $user`), the same
rule the Gate applies. The denial message is "Policy check failed for UsersQuery."

`policy()` doesn't know which record the field will touch, so abilities like `update` or `delete` belong
in `authorize()`, where you can load the model (see `PublishPostMutation` and `DeletePostMutation`).

## Field-level privacy on types

The hooks above protect **root fields**. Fields of object types have no `authorize()`; protect
sensitive ones in their resolver instead. The example hides e-mail addresses from everyone except
the user themselves and their organization's admins:

```php
// example/app/GraphQL/Types/UserType.php
protected function resolveEmailField(User $user): ?string
{
    return Gate::allows('view', $user) ? $user->email : null;
}
```

Return `null` (make the field nullable) when the viewer may know that the field exists but not its
value. Throw `new GraphQL\Error\Error('…')` when the attempt itself should be reported as an error.

The same applies to relations. `User.posts` filters out drafts the viewer may not read
(see [Relations](05-relations-and-dataloaders.md#post-processing-a-batched-relation)), and `posts`
uses a query scope, `Post::visibleTo($user)`, so nothing that shouldn't be returned is ever loaded.

> **Rule of thumb:** a type is reachable from many root fields. Whatever a type exposes, it
> exposes through *every* path that reaches it, so put privacy rules on the type, not only on the
> query that you expect clients to use.

## Protecting a whole schema

Route middleware is the right tool when *every* field needs the same rule, typically for an
admin API:

```php
// example/config/laragraph.php
'admin' => [
    'query'      => ['stats' => StatsQuery::class],
    'middleware' => ['auth:api', 'can:access-admin-api'],
    'method'     => ['POST'],
],
```

Non-admins get HTTP 401/403 from Laravel before any GraphQL runs. See
[Multiple schemas](09-multiple-schemas.md).

## Generated operations are deny-by-default

`php artisan laragraph:scaffold Post --with-crud` generates operations whose `authorize()` calls
`Gate::allows('viewAny' | 'view' | 'create' | 'update' | 'delete', …)`. Until you write
`PostPolicy`, every generated operation is denied. That's intentional: nothing becomes public by
accident.

## Checklist

- [ ] Every mutation has an `authorize()`, `authorizeWithContext()` or `policy()`.
- [ ] Queries returning private data check access, or filter with a scope such as `visibleTo()`.
- [ ] Sensitive *type* fields (`email`, `phone`, tokens…) check the viewer in their resolver.
- [ ] "Not found" and "not allowed" produce the same response for id lookups.
- [ ] Login and other credential-checking mutations are throttled.
- [ ] Mutations take the acting user from `$context->user()`, never from an argument.
