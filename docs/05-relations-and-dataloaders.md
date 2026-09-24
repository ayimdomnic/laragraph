# 5. Relations & DataLoaders

## The N+1 problem

Consider this query:

```graphql
{
  posts(first: 50) {
    edges { node { title author { name } } }
  }
}
```

If `Post.author` simply reads `$post->author`, Eloquent lazy-loads the author **once per post**:
1 query for the posts plus 50 for the authors. Nest one more level and it multiplies. GraphQL
resolves each field of each object independently, so the resolver for one post's `author` has no
idea that 49 other posts need an author too.

**DataLoaders** fix this. Each resolver *asks* for a key and gets a promise back. Once every
field at the current depth has asked, Laragraph runs **one** batch for all the keys and settles the
promises.

## `batchRelation()`: Eloquent relations in one query

Inside a type class, resolve relations through `batchRelation()`:

```php
// example/app/GraphQL/Types/PostType.php
protected function resolveAuthorField(Post $post, array $args, mixed $context): mixed
{
    return $this->batchRelation(Post::class, 'author', $post, $context);
}
```

The arguments are the parent model class, the relation name, the parent model and the resolver's
`$context`. It works with every relation type, including `belongsTo`, `hasOne`, `hasMany`,
`belongsToMany`, the `morph*` relations and `hasManyThrough`, because it uses Eloquent's own
eager-loading code (the same path as `Model::with()`).

What happens on the query above:

1. 50 `resolveAuthorField()` calls each register their post and return a promise.
2. Laragraph calls `loadMissing('author')` on those 50 posts: **one** `select * from users where id in (…)`.
3. Each promise receives its post's author.

Details worth knowing:

- **Already-loaded relations are not queried again.** If your root query did
  `Post::with('author')`, `loadMissing()` sees that and runs no query at all.
- **The parent models are reused**, not reloaded, so soft-deleted parents and models loaded with
  extra columns work as expected.
- **Relation constraints and scopes defined on the relation apply**, because they're part of the
  relation.
- Types without a `resolve{Field}Field` method read the property directly, which lazy-loads.
  Call `Model::preventLazyLoading(! app()->isProduction())` in a service provider to catch fields
  that you forgot to batch.

The example asserts that a query for organizations → members, member counts, posts → authors
always runs **6 queries**, whether there are 2 organizations or 12
([`BatchingNPlusOneTest`](../example/tests/Feature/GraphQL/BatchingNPlusOneTest.php)).

### Post-processing a batched relation

`batchRelation()` returns a promise. Call `->then()` to transform the result once it arrives, for
example to filter by permission:

```php
// example/app/GraphQL/Types/UserType.php: drop drafts the viewer may not read
protected function resolvePostsField(User $user, array $args, mixed $context): mixed
{
    return $this->batchRelation(User::class, 'posts', $user, $context)
        ->then(fn (Collection $posts): Collection => $posts
            ->filter(fn ($post): bool => Gate::allows('view', $post))
            ->values());
}
```

Filtering in PHP is fine for small relations. For large ones, define a constrained relation on the
model (`publishedPosts()`) and batch that, so the database does the filtering.

## Custom DataLoaders

When the data isn't a plain relation, such as aggregates, computed values, other databases or HTTP
APIs, write a `BatchResolver`:

```php
// example/app/GraphQL/Loaders/MemberCountLoader.php
namespace App\GraphQL\Loaders;

use App\Models\User;
use Ayimdomnic\Laragraph\DataLoader\BatchResolver;

class MemberCountLoader extends BatchResolver
{
    /**
     * @param  array<int|string>  $keys  Organization ids.
     * @return array<int, int> One result per key, in the same order.
     */
    public function batch(array $keys): array
    {
        $counts = User::query()
            ->whereIn('organization_id', $keys)
            ->groupBy('organization_id')
            ->selectRaw('organization_id, count(*) as aggregate')
            ->pluck('aggregate', 'organization_id');

        return array_map(fn (int|string $id): int => (int) ($counts[$id] ?? 0), $keys);
    }
}
```

And use it from a resolver:

```php
// example/app/GraphQL/Types/OrganizationType.php
use Ayimdomnic\Laragraph\DataLoader\DataLoaderRegistry;

protected function resolveMemberCountField(Organization $organization, array $args, mixed $context): mixed
{
    return DataLoaderRegistry::for($context)
        ->get(MemberCountLoader::class)
        ->load($organization->id);
}
```

### The one rule: return results in key order

`batch()` must return **one value per key, in the same order as `$keys`**. The DataLoader matches
results to keys *by position*. A common mistake is:

```php
// ✗ WRONG: keyed by id, so positions don't line up with $keys
return User::whereIn('id', $keys)->get()->keyBy('id')->all();

// ✓ RIGHT: one value (or null) per key, in order
$users = User::whereIn('id', $keys)->get()->keyBy('id');

return array_map(fn ($id) => $users->get($id), $keys);
```

Returning a different number of values than keys throws an error.

### Loader API

`DataLoaderRegistry::for($context)->get(Loader::class)` returns an `Overblog\DataLoader\DataLoader`:

| Method | Use |
|---|---|
| `load($key)` | A promise for one key |
| `loadMany([$k1, $k2])` | A promise for several keys |
| `prime($key, $value)` | Seed the cache with a value you already have |
| `clear($key)` / `clearAll()` | Forget cached values, e.g. after a mutation changed them |

Keys are de-duplicated and cached **for the duration of one operation**: asking twice for
organization 1's member count runs the batch once and returns the same value.

## Lifetime and the context

- A fresh `DataLoaderRegistry` is attached to every execution. In a [batched HTTP
  request](08-http-api.md#batching) each operation gets its own.
- The registry is cleared when the execution ends, so cached rows never leak into the next
  request. That matters for long-running workers such as Octane or queue workers.
- Loaders are created through the container (`app(MemberCountLoader::class)`), so their
  constructors can use dependency injection.
- `DataLoaderRegistry::for($context)` works for every kind of context: the HTTP `GraphQLContext`,
  an array or an object you passed to `Laragraph::execute()`. For HTTP requests,
  `$context->dataLoaders` is the same registry.

## Promises in root resolvers

Root query and mutation resolvers can return promises too:

```php
public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
{
    return DataLoaderRegistry::for($context)->get(UserLoader::class)->loadMany($args['ids']);
}
```

Chaining is supported: a `then()` callback may itself return another loader's promise.
