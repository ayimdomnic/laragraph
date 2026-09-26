# 6. Pagination

Laragraph implements [Relay cursor connections](https://relay.dev/graphql/connections.htm), the
pagination format that Apollo Client, Relay, urql and most GraphQL tooling understand. A simpler
page/offset helper is available too.

## Cursor connections

Three calls in one query class:

```php
// example/app/GraphQL/Queries/PostsQuery.php
use Ayimdomnic\Laragraph\Pagination\ConnectionType;

public function type(): Type
{
    return ConnectionType::make('PostConnection', Laragraph::type('Post'));
}

public function args(): array
{
    return [
        ...ConnectionType::args(),                           // first, after, last, before
        'status' => ['type' => Laragraph::type('PostStatus')],
    ];
}

public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
{
    $query = Post::query()
        ->visibleTo($context->user())
        ->when(isset($args['status']), fn ($query) => $query->where('status', $args['status']))
        ->orderByDesc('id');

    return ConnectionType::paginate($query, $args);
}
```

This produces the schema:

```graphql
type PostConnection {
  edges: [PostConnectionEdge]
  pageInfo: PageInfo!
}

type PostConnectionEdge {
  node: Post
  cursor: String!
}

type PageInfo {
  hasNextPage: Boolean!
  hasPreviousPage: Boolean!
  startCursor: String
  endCursor: String
  total: Int!
}
```

### Why `make()` and not `new`

A schema may contain only one type per name. `ConnectionType::make()` returns the **same**
instance every time it's called with the same name and node type, so two fields can both
return `PostConnection` (for example `posts` and `Organization.postsConnection`). `PageInfo` is always
shared. `new ConnectionType(...)` still works for a connection that only one field uses.

### Paging forwards

```graphql
query ($after: String) {
  posts(first: 10, after: $after) {
    edges { cursor node { id title } }
    pageInfo { hasNextPage endCursor total }
  }
}
```

Start without `after`, then pass the previous page's `pageInfo.endCursor` until `hasNextPage` is
`false`. The example walks 25 posts ten at a time this way and checks that it sees each one exactly once
([`PaginationTest`](../example/tests/Feature/GraphQL/PaginationTest.php)).

### Paging backwards

`last: 5` returns the final five items. `last: 5, before: $startCursor` returns the five before
those:

```graphql
{ posts(last: 5) { edges { node { id } } pageInfo { hasPreviousPage startCursor } } }
```

### Exact semantics

Laragraph follows the Relay specification's algorithm:

1. `after` and `before` narrow the full, ordered result to the items between them.
2. `first: N` then keeps the leading N of those; `last: N` keeps the trailing N.
3. With neither `first` nor `last`, the page size is `pagination.per_page` (default 15).
4. Page sizes above `pagination.max_per_page` (default 100) are **silently capped**, so
   `first: 1000000` can't dump a table. Set it to `null` to remove the cap (not recommended).
5. A negative `first`/`last` is an error. An unrecognised cursor counts as "from the start".

`pageInfo.total` is the total number of items that match, ignoring the cursor window.

### What `paginate()` accepts

| Source | Paging |
|---|---|
| Eloquent builder (`Post::query()`), query builder (`DB::table('posts')`), relation (`$user->posts()`) | Exact offsets, any window |
| Any other object with Laravel's `paginate($perPage, $columns, $pageName, $page)` signature (e.g. a Scout builder) | Page-aligned windows: a constant `first`, moving forwards |

### Things to know

- **Always order the query**, and by a unique column (or add one as a tie-breaker:
  `->orderByDesc('published_at')->orderByDesc('id')`). Unordered SQL can return rows in a different
  order on every request, and pages then skip or repeat items.
- **Cursors are opaque but position-based** (they encode the item's offset). If rows are inserted
  before the cursor between two requests, the next page may repeat an item. For feeds with heavy
  writes, filter on a column instead (`where('id', '<', $lastSeenId)`) through your own arguments.
- **`total` runs a `COUNT(*)`.** On very large tables consider whether you need it.
- **Connections on type fields** (paginating each organization's posts) run one query per parent,
  because a separate window per parent can't be batched. Keep such fields to single-object
  queries, or use `batchRelation()` with a limited relation instead.

## Simple pagination

For admin tables and other page-number UIs, `ConnectionType::simplePaginate()` accepts `page` and
`per_page` arguments and returns an array:

```php
public function args(): array
{
    return [
        'page'     => ['type' => Type::int(), 'defaultValue' => 1],
        'per_page' => ['type' => Type::int()],
    ];
}

public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
{
    return ConnectionType::simplePaginate(User::query()->orderBy('name'), $args);
    // ['data' => [...], 'total' => 42, 'per_page' => 15, 'current_page' => 1,
    //  'last_page' => 3, 'has_more_pages' => true]
}
```

`per_page` is capped by `max_per_page` here too. There is no built-in type for this shape; define
an object type with the fields you need (`data: [User!]!`, `total: Int!`, …).

## Relay Node re-fetching

A `Node`-implementing type (`User`, `Post`, `Organization` in the example app — see
[Types](02-types.md)) is already refetchable by its own single-item query
(`user(id:)`, `post(id:)`, …). Relay clients specifically also expect a generic root
`node(id: ID!): Node` field that works across every type from one opaque, globally unique id —
`Apollo`/`urql` don't need this (they only require `__typename` + `id` to be unique per type for
cache normalization), but Relay's cache does.

```php
use Ayimdomnic\Laragraph\Relay\GlobalId;

// config/laragraph.php
'query' => ['node' => \Ayimdomnic\Laragraph\Relay\NodeQuery::class],
```

Make a type refetchable by overriding `resolveNode()` — it's given the *local* id already decoded
from the global id, and must apply its own visibility check (this bypasses the `Field`
`authorize()`/`policy()` pipeline entirely, since `node` is one shared field, not a
per-type query):

```php
// app/GraphQL/Types/PostType.php
public function resolveNode(string $id, mixed $context): ?object
{
    $post = Post::find($id);

    return $post !== null && Gate::allows('view', $post) ? $post : null;
}
```

A client obtains a global id with `GlobalId::encode('Post', $post->id)` — typically from a field
you add for this purpose, or (see below) from the type's own `id` field.

**Laragraph does not change any existing type's `id` field to emit a global id** — that would be a
breaking change for every current consumer receiving a raw primary key today. `id` stays whatever
each type's `fields()` already returns. If you want strict Relay compliance (a type's own `id` *is*
the global id `node()` accepts), encode it yourself:

```php
protected function resolveIdField(Post $post): string
{
    return GlobalId::encode('Post', $post->id);
}
```

Doing this on a type already in production is a breaking change for existing clients that treat
`id` as a raw primary key (e.g. resubmitting it into another query's `id` argument) — decide
per type, don't do it by default.
