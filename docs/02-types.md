# 2. Types

GraphQL is typed: every field returns a type, and every argument accepts one. Laragraph gives
you a base class for each kind of named type.

| Kind | Base class | Example in the example app |
|---|---|---|
| Object | `Ayimdomnic\Laragraph\Support\Type` | [`UserType`](../example/app/GraphQL/Types/UserType.php), [`PostType`](../example/app/GraphQL/Types/PostType.php) |
| Input object | `Ayimdomnic\Laragraph\Support\InputType` | [`PostInputType`](../example/app/GraphQL/Types/PostInputType.php) |
| Enum | a native PHP `enum`, or `Ayimdomnic\Laragraph\Support\EnumType` | [`UserRole`](../example/app/Enums/UserRole.php) |
| Interface | `Ayimdomnic\Laragraph\Support\InterfaceType` | [`NodeType`](../example/app/GraphQL/Types/NodeType.php) |
| Union | `Ayimdomnic\Laragraph\Support\UnionType` | [`SearchResultType`](../example/app/GraphQL/Types/SearchResultType.php) |
| Scalar | `Ayimdomnic\Laragraph\Support\ScalarType` | built-ins: `DateTime`, `Date`, `JSON`, `Upload` |

## Object types

```php
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Type;
use GraphQL\Type\Definition\Type as GType;

class PostType extends Type
{
    protected array $attributes = [
        'name'        => 'Post',                     // the GraphQL name
        'description' => 'An article written by a user.',
    ];

    public function fields(): array
    {
        return [
            // Shorthand: just the type.
            'id'    => GType::nonNull(GType::id()),
            'title' => GType::nonNull(GType::string()),

            // Full definition: type + description + arguments + deprecation.
            'excerpt' => [
                'type'        => GType::nonNull(GType::string()),
                'description' => 'The first N characters of the body.',
                'args'        => ['length' => ['type' => GType::int(), 'defaultValue' => 120]],
            ],
            'summary' => [
                'type'              => GType::string(),
                'deprecationReason' => 'Use `excerpt` instead.',
            ],

            // Other registered types, by name.
            'author'      => GType::nonNull(Laragraph::type('User')),
            'publishedAt' => Laragraph::type('DateTime'),
        ];
    }
}
```

### Where field values come from

Without a resolver, a field reads the same-named key or property from its parent value:
`$post['title']` for arrays, `$post->title` for objects and Eloquent models. That covers most
fields.

It does **not** convert names: a GraphQL field `publishedAt` does not read the `published_at`
column. Give such fields a resolver.

### Per-field resolvers

Define a method named `resolve{FieldName}Field` and Laragraph wires it up automatically. It
receives the usual resolver arguments — the parent value, the field's arguments, the context and
the `ResolveInfo` — and you may declare only the ones you need:

```php
protected function resolvePublishedAtField(Post $post): mixed
{
    return $post->published_at;
}

protected function resolveExcerptField(Post $post, array $args): string
{
    return Str::limit($post->body, $args['length']);
}

protected function resolveEmailField(User $user): ?string
{
    // Field-level privacy: see chapter 4.
    return Gate::allows('view', $user) ? $user->email : null;
}
```

These methods may be `protected`. A `resolve` key in the field definition takes precedence over
a `resolve{Name}Field` method.

Relations need more care — resolving `$post->author` once per post causes the N+1 problem.
Use `$this->batchRelation()`, covered in [chapter 5](05-relations-and-dataloaders.md).

### Implementing interfaces

Set `interfaces` to a closure, so the interface is looked up only after every type is registered:

```php
public function __construct()
{
    $this->attributes['interfaces'] = fn(): array => [Laragraph::type('Node')];

    parent::__construct();
}
```

## Input types

Input types describe structured arguments, typically the payload of a mutation:

```php
use Ayimdomnic\Laragraph\Support\InputType;

class PostInputType extends InputType
{
    protected array $attributes = ['name' => 'PostInput'];

    public function fields(): array
    {
        return [
            'title'   => ['type' => GType::nonNull(GType::string())],
            'body'    => ['type' => GType::nonNull(GType::string())],
            'publish' => ['type' => GType::boolean(), 'defaultValue' => false],
        ];
    }
}
```

Use it as an argument type — `'input' => ['type' => GType::nonNull(Laragraph::type('PostInput'))]`
— and the resolver receives a plain array: `$args['input']['title']`. Validate nested values with
dot notation (`'input.title' => ['required', 'min:3']`); see
[chapter 3](03-queries-and-mutations.md#validation).

## Enums

### Native PHP enums (recommended)

Register a backed or pure enum directly — no wrapper class is needed:

```php
// app/Enums/UserRole.php
use GraphQL\Type\Definition\Description;

#[Description('What a user may do inside their organization.')]
enum UserRole: string
{
    #[Description('Manages the organization.')]
    case Admin = 'admin';

    case Member = 'member';
}
```

```php
// config/laragraph.php
'types' => [
    'UserRole' => \App\Enums\UserRole::class,
],
```

- The GraphQL values are the **case names** (`Admin`, `Member`).
- Resolvers may return enum cases, and Eloquent enum casts work unchanged: a `role` column cast to
  `UserRole::class` serializes as `Admin`.
- Enum **arguments arrive as enum cases**, not strings, so you can pass them straight to a query:
  `$query->where('role', $args['role'])`.
- `#[Description]` and `#[Deprecated]` (from `GraphQL\Type\Definition`) on the enum and its cases
  appear in introspection.

Enums placed in `app/GraphQL/Types` are discovered like any other type.

### `EnumType` classes

Use the base class when the GraphQL values should differ from any PHP enum:

```php
use Ayimdomnic\Laragraph\Support\EnumType;

class VisibilityType extends EnumType
{
    protected array $attributes = ['name' => 'Visibility'];

    public function values(): array
    {
        return [
            'PUBLIC'  => ['value' => 'public', 'description' => 'Anyone'],
            'PRIVATE' => ['value' => 'private'],
        ];
        // or: return Visibility::cases();   — a native enum's cases
    }
}
```

## Interfaces

An interface declares fields that several object types share. Clients select them directly or
with an inline fragment (`... on Node { id }`).

```php
use Ayimdomnic\Laragraph\Support\InterfaceType;
use GraphQL\Type\Definition\ResolveInfo;

class NodeType extends InterfaceType
{
    protected array $attributes = ['name' => 'Node'];

    public function fields(): array
    {
        return ['id' => ['type' => GType::nonNull(GType::id())]];
    }

    public function resolveType(mixed $value, mixed $context, ResolveInfo $info): mixed
    {
        return match (true) {
            $value instanceof User => Laragraph::type('User'),
            $value instanceof Post => Laragraph::type('Post'),
            default                => null,
        };
    }
}
```

`resolveType()` tells GraphQL which concrete type a value is. It may return a type instance or a
type name.

## Unions

A union is "one of these types", with no shared fields. The example's `search` returns users,
posts and organizations:

```php
use Ayimdomnic\Laragraph\Support\UnionType;

class SearchResultType extends UnionType
{
    protected array $attributes = ['name' => 'SearchResult'];

    public function types(): array
    {
        return [Laragraph::type('User'), Laragraph::type('Post'), Laragraph::type('Organization')];
    }

    public function resolveType(mixed $value, mixed $context, ResolveInfo $info): mixed
    {
        return match (true) {
            $value instanceof User         => Laragraph::type('User'),
            $value instanceof Post         => Laragraph::type('Post'),
            $value instanceof Organization => Laragraph::type('Organization'),
            default                        => null,
        };
    }
}
```

```graphql
{
  search(term: "Acme") {
    __typename
    ... on Post { title }
    ... on Organization { name }
  }
}
```

## Scalars

### Built-in scalars

Register the ones you use:

```php
'types' => [
    'DateTime' => \Ayimdomnic\Laragraph\Scalars\DateTimeType::class,
    'Date'     => \Ayimdomnic\Laragraph\Scalars\DateType::class,
    'JSON'     => \Ayimdomnic\Laragraph\Scalars\JsonType::class,
    'Upload'   => \Ayimdomnic\Laragraph\Scalars\UploadType::class,
],
```

| Scalar | Output | Accepted input | Resolver receives |
|---|---|---|---|
| `DateTime` | `2026-01-15T09:30:00+00:00` for `DateTimeInterface` values; valid strings unchanged | ISO-8601 with or without fractional seconds (`…T09:30:00.123Z`, as JavaScript's `toISOString()` produces), `Y-m-d H:i:s`, `Y-m-d` | `DateTimeImmutable` |
| `Date` | `2026-01-15` | `YYYY-MM-DD` (parsed as midnight) | `DateTimeImmutable` |
| `JSON` | any JSON value, unchanged | any JSON value, or a GraphQL literal | the decoded value |
| `Upload` | — (input only) | a file sent with the [multipart request spec](08-http-api.md#file-uploads) | `Illuminate\Http\UploadedFile` |

Impossible dates such as `2024-02-31` are rejected, not rolled over into the next month.

### Custom scalars

```php
use Ayimdomnic\Laragraph\Support\ScalarType;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;

class EmailType extends ScalarType
{
    public string $name = 'Email';

    public function serialize(mixed $value): string
    {
        return (string) $value;
    }

    public function parseValue(mixed $value): string
    {
        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new Error('Not a valid email address.');
        }

        return strtolower($value);
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        if (!$valueNode instanceof StringValueNode) {
            throw new Error('Email must be a string.');
        }

        return $this->parseValue($valueNode->value);
    }
}
```

`serialize()` converts outgoing values, `parseValue()` validates variables, and `parseLiteral()`
validates values written inline in the query. Throw `GraphQL\Error\Error` to reject input with a
client-visible message.

### Database scalar presets

Enable scalars matching your database's column types in one line:

```php
'database_types' => [
    'preset' => 'postgres',   // postgres | cockroachdb | mssql | oracle | null
    'custom' => [],
],
```

| Preset | Scalars |
|---|---|
| `postgres` | `UUID`, `BigInt`, `JSONB`, `Money`, `TSVector`, `Interval`, `Inet` |
| `cockroachdb` | `UUID`, `BigInt`, `JSONB`, `Inet` |
| `mssql` | `UUID`, `BigInt`, `Money` |
| `oracle` | `UUID`, `BigInt`, `Interval` |

## Registering types

Every named type must be registered before a schema is built. There are three ways.

**1. Auto-discovery (the default).** Every concrete class in `app/GraphQL/Types` — including
subdirectories — that is a GraphQL named type (object, input, enum, interface, union or scalar),
and every native PHP enum there, is registered. Its **alias** is the class name without a `Type`
suffix: `UserType` → `User`, `PostInputType` → `PostInput`,
`Types/Inputs/CreateUserInput` → `CreateUserInput`. Configure the directories under
`laragraph.discover`; set one to `''` to turn it off.

**2. The `types` config key.** `'Alias' => Class::class` pairs are registered in every schema. A
class listed here is never registered a second time by discovery, even under a different alias.

**3. A schema's own `types` key.** Types listed under `schemas.admin.types` exist only in that
schema — see [multiple schemas](09-multiple-schemas.md).

Look types up by alias with `Laragraph::type('User')`. Keep aliases equal to GraphQL names; the
naming convention above does this for you. When they differ, `Laragraph::typeByName('GraphQLName')`
finds a type by its GraphQL name.

Type instances are created once per schema build and reused, so `Laragraph::type('User')` inside
`fields()` always returns the same instance, as GraphQL requires.

## Common mistakes

| Symptom | Cause |
|---|---|
| `Type [X] is not registered` | The class is outside the discovered directories and not in `types`, or its alias differs from the name you asked for. |
| `Schema must contain unique named types but contains multiple types named "X"` | Two classes share a GraphQL name, or you built a type with `new` in two places. Build connection types with [`ConnectionType::make()`](06-pagination.md). |
| A camelCase field is always `null` | No resolver maps it to the snake_case attribute. |
| `resolveType()` signature errors | It takes three parameters: `(mixed $value, mixed $context, ResolveInfo $info)`. |

`php artisan laragraph:validate` catches most of these at deploy time — see [deployment](14-deployment.md).
The camelCase/snake_case one is different: the schema still builds and the query still succeeds,
just with a silently wrong `null`. Set `'log_unresolved_fields' => true` (or leave it `null`, which
follows `app.debug`) and Laragraph logs a warning — `logging.channel` — the moment a field resolves
to `null` only because nothing matched, rather than a real null value or an accessor. See
[Configuration reference](15-configuration.md#log_unresolved_fields).
