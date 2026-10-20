# The example application

The [`example/`](https://github.com/ayimdomnic/laragraph/tree/master/example) directory of the
repository holds a complete Laravel application — a small "team blog" API of organizations, their
members and the posts they publish. It is the reference the whole guide is built on: **almost every
code sample in these pages comes from it**, and its test suite runs against this repository's own
code (Composer installs Laragraph from the parent directory via a `path` repository), so anything
the guide shows is known to work with the version you're reading about.

## Run it

Requires PHP 8.3+ and Composer. From the repository root:

```bash
cd example
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Then open `http://localhost:8000/graphql/graphiql` in your browser (served because `APP_DEBUG=true`; see
[The HTTP API](08-http-api.md) for what the IDE adds on top — auto-auth and the dev-only
role/token picker).

Seeded accounts (password `password`):

| Email | Role |
|---|---|
| `admin@example.com` | Admin of "Acme Corporation" |
| `member@example.com` | Member of "Acme Corporation" |

Log in and keep the token, then try a query that touches several features at once — the `me`
relation chain, a Relay connection, and a union search:

```graphql
mutation {
  login(email: "member@example.com", password: "password") { token user { name role } }
}
```

```graphql
{
  me { name role organization { name memberCount } }
  posts(first: 5) {
    edges { cursor node { title status publishedAt author { name } } }
    pageInfo { hasNextPage endCursor total }
  }
  search(term: "Acme") {
    __typename
    ... on Post { title }
    ... on Organization { name }
  }
}
```

## Where each feature lives

Every row is backed by at least one test under
[`tests/Feature/GraphQL`](https://github.com/ayimdomnic/laragraph/tree/master/example/tests/Feature/GraphQL),
so the corresponding guide pages can show real code rather than sketches.

| Feature | Example source | Guide |
|---|---|---|
| Object types, per-field resolvers, `#[Fillable]` models | [`UserType`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Types/UserType.php), [`PostType`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Types/PostType.php) | [Types](02-types.md) |
| Field-level visibility — declarative `canSee` (ability + closure shapes) | [`UserType::email`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Types/UserType.php), [`OrganizationType::settings`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Types/OrganizationType.php) | [Authentication & authorization](04-authentication-and-authorization.md#field-level-privacy-on-types) |
| Input objects | [`PostInputType`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Types/PostInputType.php) | [Types](02-types.md) |
| Native PHP enums | [`UserRole`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/Enums/UserRole.php), [`PostStatus`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/Enums/PostStatus.php) | [Types](02-types.md) |
| Interfaces, unions, Relay `Node` | [`NodeType`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Types/NodeType.php), [`SearchResultType`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Types/SearchResultType.php) | [Types](02-types.md), [Pagination](06-pagination.md) |
| Custom + database scalars (`DateTime`, `JSON`, `Upload`) | [`OrganizationType`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Types/OrganizationType.php), [`UploadAvatarMutation`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Mutations/UploadAvatarMutation.php) | [Types](02-types.md), [The HTTP API](08-http-api.md) |
| Queries, arguments, validation rules, `complexity()` | [`SearchQuery`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Queries/SearchQuery.php), [`PostsQuery`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Queries/PostsQuery.php) | [Queries & mutations](03-queries-and-mutations.md) |
| Mutations, `authorize()`, custom coded errors | [`LoginMutation`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Mutations/LoginMutation.php) with [`InvalidCredentialsException`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Exceptions/InvalidCredentialsException.php) | [Queries & mutations](03-queries-and-mutations.md), [Error handling](17-error-handling-and-localization.md) |
| Guards (JWT), policies, `policy()` shortcut | [`UserQuery`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Queries/UserQuery.php), [`UsersQuery`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Queries/UsersQuery.php), [`PostPolicy`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/Policies/PostPolicy.php) | [Authentication & authorization](04-authentication-and-authorization.md) |
| `batchRelation()` and custom DataLoaders | [`MemberCountLoader`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Loaders/MemberCountLoader.php) | [Relations & DataLoaders](05-relations-and-dataloaders.md) |
| Relay cursor pagination and `node(id:)` re-fetching | [`PostsQuery`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Queries/PostsQuery.php), [`NodeType`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Types/NodeType.php) | [Pagination](06-pagination.md) |
| Subscriptions (Broadcast driver, channel authorization) | [`PostPublishedSubscription`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Subscriptions/PostPublishedSubscription.php) | [Subscriptions](07-subscriptions.md) |
| GraphQL over HTTP: GET, batching, APQ persisted queries, uploads, error codes | [`HttpProtocolTest`](https://github.com/ayimdomnic/laragraph/blob/master/example/tests/Feature/GraphQL/HttpProtocolTest.php) | [The HTTP API](08-http-api.md) |
| Multiple isolated schemas (`default` + `admin`) | [`Admin/StatsQuery`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Admin/StatsQuery.php), [`config/laragraph.php`](https://github.com/ayimdomnic/laragraph/blob/master/example/config/laragraph.php) | [Multiple schemas](09-multiple-schemas.md) |
| Security limits and a custom validation rule | [`MaxRootFieldsRule`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Validation/MaxRootFieldsRule.php) | [Security](10-security.md) |
| Response cache + invalidation, Octane | [`FlushResponseCacheAfterMutations`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/Listeners/FlushResponseCacheAfterMutations.php), [`OctaneTest`](https://github.com/ayimdomnic/laragraph/blob/master/example/tests/Octane/OctaneTest.php) | [Performance & caching](11-performance-and-caching.md) |
| Events, response extensions (`requestId`, `timing`), custom extensions | [`LogSlowGraphQLOperations`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/Listeners/LogSlowGraphQLOperations.php), [`ApiVersionExtension`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Extensions/ApiVersionExtension.php) | [Observability](12-observability.md) |
| Testing helpers (`graphql()` over HTTP with real tokens) | [`GraphQLTestCase`](https://github.com/ayimdomnic/laragraph/blob/master/example/tests/Feature/GraphQL/GraphQLTestCase.php) | [Testing](13-testing.md) |
| Artisan commands (`validate`, `cache`, `schema:export`) | [`ToolingTest`](https://github.com/ayimdomnic/laragraph/blob/master/example/tests/Feature/GraphQL/ToolingTest.php) | [Deployment](14-deployment.md) |
| Localized error messages | [`LocalizationTest`](https://github.com/ayimdomnic/laragraph/blob/master/example/tests/Feature/GraphQL/LocalizationTest.php) | [Error handling](17-error-handling-and-localization.md) |

## Run its tests

The suite is part of the repository's CI:

```bash
cd example
php artisan test                  # 75+ feature tests over real HTTP
```

Because the app installs the package from `..`, these tests always exercise the working tree — when
they pass, the code the guides show works with the version you're reading. If you fork the package,
change a feature in `src/`, and break the example, this suite is what tells you the docs have gone
stale.

## Reading order

A good way to learn Laragraph end-to-end: [Getting started](01-getting-started.md) → open
[`UserType`](https://github.com/ayimdomnic/laragraph/blob/master/example/app/GraphQL/Types/UserType.php)
and follow its links in the [Types](02-types.md) guide → [authentication](04-authentication-and-authorization.md)
(including `canSee`) → [relations](05-relations-and-dataloaders.md) → [pagination](06-pagination.md),
checking each concept off in GraphiQL as you go.
