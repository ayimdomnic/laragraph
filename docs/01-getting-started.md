# 1. Getting started

## Requirements

| | Supported |
|---|---|
| PHP | 8.2 – 8.5 |
| Laravel | 10, 11, 12, 13 (Laravel 13 needs PHP 8.3+) |

## Installation

```bash
composer require ayimdomnic/laragraph
php artisan vendor:publish --tag=laragraph-config
```

The service provider and the `Laragraph` facade are registered automatically. Publishing the
config is optional but recommended — the file is heavily commented and the
[configuration reference](15-configuration.md) explains every key.

Out of the box you get:

- `POST|GET /graphql` — the GraphQL endpoint (prefix configurable with `route.prefix`)
- `GET /graphql/graphiql` — the GraphiQL IDE, **only while `APP_DEBUG=true`**
- auto-discovery of GraphQL classes in `app/GraphQL/{Types,Queries,Mutations,Subscriptions}`

## Your first API in five minutes

### 1. A type

```bash
php artisan laragraph:make:type UserType
```

```php
// app/GraphQL/Types/UserType.php
namespace App\GraphQL\Types;

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
            'id'    => GType::nonNull(GType::id()),
            'name'  => GType::nonNull(GType::string()),
            'email' => GType::string(),
        ];
    }
}
```

Fields read the property of the same name from whatever the parent resolver returned — an
Eloquent model, an array or any object — so `name` above resolves `$user->name` with no extra code.

### 2. A query

```bash
php artisan laragraph:make:query UsersQuery
```

```php
// app/GraphQL/Queries/UsersQuery.php
namespace App\GraphQL\Queries;

use App\Models\User;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Query;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class UsersQuery extends Query
{
    public function type(): Type
    {
        return Type::listOf(Laragraph::type('User'));
    }

    public function args(): array
    {
        return ['limit' => ['type' => Type::int(), 'defaultValue' => 10]];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return User::limit($args['limit'])->get();
    }
}
```

### 3. A mutation

```bash
php artisan laragraph:make:mutation UpdateProfileMutation
```

```php
namespace App\GraphQL\Mutations;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Mutation;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class UpdateProfileMutation extends Mutation
{
    public function type(): Type
    {
        return Laragraph::type('User');
    }

    public function args(): array
    {
        return ['name' => ['type' => Type::nonNull(Type::string())]];
    }

    public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
    {
        return $context->user() !== null;   // logged-in users only
    }

    public function rules(array $args = []): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $user = $context->user();
        $user->update(['name' => $args['name']]);

        return $user;
    }
}
```

### 4. Call it

No registration is needed — the three classes are discovered. The field names are derived from
the class names: `UsersQuery` → `users`, `UpdateProfileMutation` → `updateProfile`, and the type
`UserType` is registered as `User` (see [how types are registered](02-types.md#registering-types)).

```bash
curl -s localhost:8000/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query": "{ users(limit: 5) { id name } }"}'
```

```json
{ "data": { "users": [ { "id": "1", "name": "Ada" } ] } }
```

Or open `/graphql/graphiql` in a browser while `APP_DEBUG=true`.

## How a request flows

```
HTTP request ─▶ route (+ route.middleware, + the schema's middleware)
             ─▶ LaragraphController
                  • parses JSON / GET / multipart / application/graphql
                  • validates the request shape (400 on malformed input)
                  • resolves persisted queries, registers subscriptions
             ─▶ Laragraph::execute()
                  • response cache lookup (query operations only)
                  • parse + validate the document (depth, complexity, aliases, custom rules)
                  • execute — for every root field:
                        authorize() → authorizeWithContext() → policy() → rules()
                        → field middleware → resolve()
                  • add response extensions, fire lifecycle events
             ─▶ JSON response (application/json or application/graphql-response+json)
```

Knowing this order answers most "why did X happen" questions: authorization always runs **before**
validation, validation before your resolver, and field middleware wraps only the resolver.

## Generators

| Command | Creates |
|---|---|
| `laragraph:make:type UserType` | `app/GraphQL/Types/UserType.php` |
| `laragraph:make:input CreateUserInput` | `app/GraphQL/Types/Inputs/CreateUserInput.php` |
| `laragraph:make:query UsersQuery` | `app/GraphQL/Queries/UsersQuery.php` |
| `laragraph:make:mutation CreateUserMutation` | `app/GraphQL/Mutations/CreateUserMutation.php` |
| `laragraph:make:subscription UserCreatedSubscription` | `app/GraphQL/Subscriptions/UserCreatedSubscription.php` |
| `laragraph:make:exception InvalidCredentialsException` | `app/GraphQL/Exceptions/InvalidCredentialsException.php` — see [Error handling & localization](17-error-handling-and-localization.md) |
| `laragraph:make:loader UserLoader` | `app/GraphQL/Loaders/UserLoader.php` — a custom `BatchResolver`, see [Relations & DataLoaders](05-relations-and-dataloaders.md) |
| `laragraph:scaffold User --with-crud` | a type, `user`/`users` queries and create/update/delete mutations for a model |

`laragraph:scaffold` reads the model's `$fillable` and `$casts`, leaves out attributes in `$hidden`
(passwords, tokens) and generates **deny-by-default** operations: each one calls
`Gate::allows()` for the matching policy ability (`viewAny`, `view`, `create`, `update`,
`delete`), so nothing is reachable until you write the policy. Options: `--all` (every model in
`app/Models`), `--with-crud`, `--force` (overwrite existing files). Generated classes are picked
up by auto-discovery automatically; if you've turned discovery off, `--register` writes them into
`config/laragraph.php`'s `types` and `schemas.default.query`/`mutation` arrays for you. It never
guesses at an edit it isn't sure about — a second schema, an already-registered alias, or a
config file shaped differently than the published default all fall back to printing a reminder
instead of touching the file.

## Next

- Learn the building blocks in [Types](02-types.md) and [Queries & mutations](03-queries-and-mutations.md).
- Or run the [example application](../example/README.md) and explore it in GraphiQL.
