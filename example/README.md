# Laragraph example application

A small "team blog" API — organizations, their members and the posts they publish — that
exercises **every Laragraph feature**. Every feature is covered by a test in
[`tests/Feature/GraphQL`](tests/Feature/GraphQL) or [`tests/Octane`](tests/Octane), so the code here is guaranteed to work with the
Laragraph version in this repository.

The step-by-step explanation of each feature lives in the [developer guide](../docs/README.md);
this README gets the app running and tells you where to look.

## Setup

Requires PHP 8.3+ and Composer. The app installs Laragraph from the parent directory (a Composer
`path` repository), so it always runs the code in this checkout.

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

Open **http://localhost:8000/graphql/graphiql** (served because `APP_DEBUG=true`).

Seeded accounts (password `password`):

| Email | Role |
|---|---|
| `admin@example.com` | Admin of "Acme Corporation" |
| `member@example.com` | Member of "Acme Corporation" |

## Try it

Log in and keep the token:

```graphql
mutation {
  login(email: "member@example.com", password: "password") { token user { name role } }
}
```

In GraphiQL, add the header `{"Authorization": "Bearer <token>"}`, then:

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

```graphql
mutation {
  createPost(input: { title: "Hello", body: "My first post from GraphiQL.", publish: true }) {
    id status publishedAt
  }
}
```

With `curl`:

```bash
curl -s localhost:8000/graphql -H 'Content-Type: application/json' \
  -d '{"query":"{ organizations(first: 3) { edges { node { name } } } }"}'
```

Admins can also query the separate admin schema:

```bash
curl -s localhost:8000/graphql/admin -H 'Content-Type: application/json' \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -d '{"query":"{ stats { members publishedPosts draftPosts } }"}'
```

## Where each feature lives

| Feature | Code | Test |
|---|---|---|
| Object types, per-field resolvers | [`Types/UserType.php`](app/GraphQL/Types/UserType.php), [`PostType.php`](app/GraphQL/Types/PostType.php) | `TypesTest` |
| Interface / union / input types | [`NodeType`](app/GraphQL/Types/NodeType.php), [`SearchResultType`](app/GraphQL/Types/SearchResultType.php), [`PostInputType`](app/GraphQL/Types/PostInputType.php) | `TypesTest` |
| Native PHP enums | [`app/Enums`](app/Enums), registered in [`config/laragraph.php`](config/laragraph.php) | `TypesTest` |
| Scalars: DateTime, JSON, Upload | `publishedAt`, `Organization.settings`, [`UploadAvatarMutation`](app/GraphQL/Mutations/UploadAvatarMutation.php) | `TypesTest`, `FileUploadTest` |
| Field arguments & deprecation | `Post.excerpt(length:)`, `Post.summary`, [`CurrentUserQuery`](app/GraphQL/Queries/CurrentUserQuery.php) | `TypesTest`, `AuthenticationTest` |
| Auto-discovery | everything in `app/GraphQL/{Types,Queries,Mutations,Subscriptions}` | all |
| Validation rules | [`RegisterMutation`](app/GraphQL/Mutations/RegisterMutation.php), [`CreatePostMutation`](app/GraphQL/Mutations/CreatePostMutation.php), [`SearchQuery`](app/GraphQL/Queries/SearchQuery.php) | `ValidationTest` |
| `authorize()` / policies / `policy()` shortcut / guards | [`UserQuery`](app/GraphQL/Queries/UserQuery.php), [`UsersQuery`](app/GraphQL/Queries/UsersQuery.php), [`LogoutMutation`](app/GraphQL/Mutations/LogoutMutation.php), [`app/Policies`](app/Policies) | `AuthorizationTest` |
| Field middleware (rate limiting) | [`LoginMutation`](app/GraphQL/Mutations/LoginMutation.php) | `AuthenticationTest` |
| N+1-safe relations & a custom DataLoader | `batchRelation()` in the types, [`MemberCountLoader`](app/GraphQL/Loaders/MemberCountLoader.php) | `BatchingNPlusOneTest` |
| Relay cursor pagination | [`PostsQuery`](app/GraphQL/Queries/PostsQuery.php), [`UsersQuery`](app/GraphQL/Queries/UsersQuery.php) | `PaginationTest` |
| Subscriptions (queued fan-out, private channels, unsubscribe) | [`PostPublishedSubscription`](app/GraphQL/Subscriptions/PostPublishedSubscription.php), [`PublishPostMutation`](app/GraphQL/Mutations/PublishPostMutation.php) | `SubscriptionsTest` |
| Multiple schemas + per-schema middleware | [`app/GraphQL/Admin`](app/GraphQL/Admin), `schemas.admin` in the config | `SecurityTest` |
| Security limits, custom validation rule | `security` config, [`MaxRootFieldsRule`](app/GraphQL/Validation/MaxRootFieldsRule.php) | `SecurityTest` |
| GraphQL over HTTP, batching, APQ | config: `batching`, `persisted_queries` | `HttpProtocolTest` |
| Response cache + invalidation | `cache.response`, [`FlushResponseCacheAfterMutations`](app/Listeners/FlushResponseCacheAfterMutations.php) | `CachingAndObservabilityTest` |
| Events, extensions, tracing | [`LogSlowGraphQLOperations`](app/Listeners/LogSlowGraphQLOperations.php), [`ApiVersionExtension`](app/GraphQL/Extensions/ApiVersionExtension.php), [`AppServiceProvider`](app/Providers/AppServiceProvider.php) | `CachingAndObservabilityTest` |
| Deployment commands | `laragraph:validate`, `laragraph:cache`, `laragraph:schema:export` | `ToolingTest` |
| Laravel Octane (schema compiled once per worker, per-request isolation) | automatic (`laragraph.octane.warm`) | [`tests/Octane/OctaneTest`](tests/Octane/OctaneTest.php) |

## Tests

```bash
php artisan test
```

The tests talk to the API over HTTP with real JWTs, exactly as a client would.
