# Laragraph

**A modern, feature-rich GraphQL package for Laravel.**

[![Latest Version](https://img.shields.io/packagist/v/ayimdomnic/laragraph.svg)](https://packagist.org/packages/ayimdomnic/laragraph)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue)](https://www.php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-10%20|%2011%20|%2012-orange)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Laragraph gives Laravel developers a clean, expressive, **code-first** API for building GraphQL services — powered by [webonyx/graphql-php](https://github.com/webonyx/graphql-php).

---

## Features

| Capability | Status |
|---|---|
| Queries & Mutations | ✅ |
| Subscriptions | ✅ |
| Object / Input / Enum / Interface / Union types | ✅ |
| Custom scalars (DateTime, Date, JSON, Upload) | ✅ |
| Built-in argument validation (Laravel rules) | ✅ |
| Per-field authorization | ✅ |
| Relay cursor pagination + simple paginator | ✅ |
| Batched queries | ✅ |
| File uploads (multipart spec) | ✅ |
| Multiple named schemas | ✅ |
| Query complexity & depth limiting | ✅ |
| Introspection toggle | ✅ |
| GraphiQL browser IDE | ✅ |
| Artisan generators | ✅ |
| Auto-discovery (no manual registration) | ✅ |

---

## Requirements

- PHP **8.2+**
- Laravel **10 / 11 / 12**

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

## GraphiQL

Built-in browser IDE at `/graphql/graphiql` (enabled by default).

```php
'graphiql' => ['enabled' => false], // disable
```

---

## Artisan Generators

| Command | Creates |
|---|---|
| `laragraph:make:type UserType` | `app/GraphQL/Types/UserType.php` |
| `laragraph:make:query UsersQuery` | `app/GraphQL/Queries/UsersQuery.php` |
| `laragraph:make:mutation CreateUserMutation` | `app/GraphQL/Mutations/CreateUserMutation.php` |
| `laragraph:make:subscription UserCreatedSubscription` | `app/GraphQL/Subscriptions/UserCreatedSubscription.php` |
| `laragraph:make:input CreateUserInput` | `app/GraphQL/Inputs/CreateUserInput.php` |

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

### Simple Offset Pagination

```php
return ConnectionType::simplePaginate(\App\Models\User::query(), $args);
// → { data, total, per_page, current_page, last_page }
```

---

## Authorization

```php
public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
{
    return $context->user()?->isAdmin() ?? false;
}
```

`false` → `AuthorizationException` → `extensions.category = 'authorization'`.

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

```php
'security' => [
    'query_max_complexity'  => 200,
    'query_max_depth'       => 10,
    'disable_introspection' => true, // recommended in production
],
```

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

## Testing

```bash
composer test
```

---

## Contributing

Contributions, issues, and feature requests are welcome!

---

## License

MIT © [Odhiambo Dormnic](https://github.com/ayimdomnic)

