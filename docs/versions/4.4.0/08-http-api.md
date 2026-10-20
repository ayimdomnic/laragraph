---
search: false
lastUpdated: false
---

# 8. The HTTP API

Laragraph's endpoint follows the [GraphQL over HTTP](https://graphql.github.io/graphql-over-http/)
specification, so any GraphQL client can use it: Apollo, Relay, urql, graphql-request or plain
`fetch`.

## Endpoints

| Route | Purpose |
|---|---|
| `GET\|POST /graphql` | The default schema (`default_schema`) |
| `GET\|POST /graphql/{schema}` | Another configured schema, e.g. `/graphql/admin` |
| `GET /graphql/graphiql` | GraphiQL, while `graphiql.enabled` allows it (default: only with `APP_DEBUG=true`) |
| `DELETE /graphql/subscriptions/{id}` | [Cancel a subscription](07-subscriptions.md#5-unsubscribe) |

The prefix (`route.prefix`), the global middleware (`route.middleware`) and the allowed methods
(`route.methods`) are configurable. Each schema can override its methods and add middleware
(see [Multiple schemas](09-multiple-schemas.md)).

## Request formats

**POST with JSON**, the usual choice:

```http
POST /graphql
Content-Type: application/json

{
  "query": "query Post($id: ID!) { post(id: $id) { title } }",
  "variables": { "id": "1" },
  "operationName": "Post"
}
```

**GET**, where the parameters go in the query string and `variables`/`extensions` are JSON-encoded:

```
GET /graphql?query={post(id:"1"){title}}&variables={}
```

GET is useful for HTTP caching and CDNs, but **mutations are refused over GET** with
`405 Method Not Allowed` and `Allow: POST`. Otherwise a link or an `<img>` tag could change data
on behalf of a logged-in user.

**Also accepted:**

- `Content-Type: application/graphql`, where the body is the query text;
- `application/x-www-form-urlencoded`;
- `multipart/form-data`, for [file uploads](#file-uploads).

Malformed requests are rejected with `400` before anything executes. That covers a body that isn't
a JSON object, a `query` that isn't a string, or `variables` that aren't an object.

## Responses and status codes

The response depends on the client's `Accept` header.

**`Accept: application/json`** (or no `Accept` header), the traditional behaviour most clients expect:

- the response is `200` whenever a GraphQL response was produced, including one containing errors;
- non-200 statuses are used only for the request errors listed in the table below.

**`Accept: application/graphql-response+json`**, the new standard media type:

- the response has `Content-Type: application/graphql-response+json`;
- a request that fails **before execution** (a syntax error, a validation error, an unknown field,
  or the depth or complexity limit exceeded) answers `400`, so it can't be mistaken for a partial
  success;
- once execution has started, the status is `200`, even when some fields have errors.

### Request error codes

Errors raised before GraphQL runs have a machine-readable `extensions.code`:

| Code | Status | When |
|---|---|---|
| `BAD_REQUEST` | 400 | The request body or parameters are malformed |
| `SCHEMA_NOT_FOUND` | 404 | `/graphql/{schema}` names a schema that isn't configured |
| `METHOD_NOT_ALLOWED` | 405 | A mutation was sent with GET |
| `PERSISTED_QUERY_NOT_FOUND` | 200* | The hash isn't stored yet, so the client should resend the full query |
| `PERSISTED_QUERY_HASH_MISMATCH` | 400 | The sent hash doesn't match the sent query |
| `PERSISTED_QUERY_REQUIRED` | 400 | Trusted-documents mode rejected unregistered query text |
| `BATCHING_DISABLED` | 400 | A batch (a JSON array of operations) was sent but `batching.enabled` is false |
| `BATCH_LIMIT_EXCEEDED` | 400 | The batch has more operations than `batching.max_operations` |
| `SUBSCRIPTION_NOT_FOUND` | 404 | Unsubscribing from an unknown subscription, or someone else's |

\* `400` for clients that accept `application/graphql-response+json`.

```json
{ "errors": [{ "message": "Schema [nope] does not exist.", "extensions": { "code": "SCHEMA_NOT_FOUND" } }] }
```

Errors *during* execution have an `extensions.category` instead
(`validation`, `authorization`, `graphql`, `internal`). See
[Queries & mutations → Errors](03-queries-and-mutations.md#errors).

## File uploads

Uploads follow the [GraphQL multipart request specification](https://github.com/jaydenseric/graphql-multipart-request-spec),
which `apollo-upload-client`, urql and most clients implement.

Register the `Upload` scalar and declare an argument:

```php
// config/laragraph.php
'types' => [
    'Upload' => \Ayimdomnic\Laragraph\Scalars\UploadType::class,
],
```

```php
// example/app/GraphQL/Mutations/UploadAvatarMutation.php
public function args(): array
{
    return ['file' => ['type' => Type::nonNull(Laragraph::type('Upload'))]];
}

public function rules(array $args = []): array
{
    return ['file' => ['required', 'image', 'max:2048']];   // Laravel's file rules work
}

public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
{
    /** @var \Illuminate\Http\UploadedFile $file */
    $file = $args['file'];
    $user = $context->user();
    $user->update(['avatar_path' => $file->store('avatars', 'public')]);

    return $user;
}
```

On the wire, a `multipart/form-data` request carries three parts:

```bash
curl localhost:8000/graphql \
  -H "Authorization: Bearer $TOKEN" \
  -F operations='{"query":"mutation ($file: Upload!) { uploadAvatar(file: $file) { avatarUrl } }","variables":{"file":null}}' \
  -F map='{"0":["variables.file"]}' \
  -F 0=@avatar.png
```

- `operations` is the normal JSON request, with `null` where each file goes.
- `map` says which variable each file part fills. Lists work too:
  `{"0":["variables.files.0"],"1":["variables.files.1"]}`. In a batch, prefix the operation index:
  `"0.variables.file"`.
- Map paths **must point into `variables`**. Anything else is rejected with `400`, so a client
  can't overwrite `query` or `operationName` with a file.

`Upload` is an input-only scalar: it can't appear in a response.

## Batching

Batching sends several operations in one HTTP request, as used by Apollo's `BatchHttpLink`.
It's off by default:

```php
'batching' => [
    'enabled'        => true,
    'max_operations' => 10,     // 0 = no limit (not recommended)
],
```

Send a JSON **list** of operations and receive a list of results in the same order:

```json
[
  { "query": "{ me { name } }" },
  { "query": "query ($id: ID!) { post(id: $id) { title } }", "variables": { "id": "1" } }
]
```

Each operation runs separately, with its own DataLoaders, errors and events, but they share
the request, and so the authenticated user. Batches over the limit, and batches sent while batching
is disabled, are answered with `400`. Every operation still has to pass the depth and complexity
limits on its own, which is why `max_operations` matters: it bounds the total work a single
request can cause.

## Persisted queries

Persisted queries replace the query text with a hash. Clients send less data, GET requests become
cacheable, and in trusted-documents mode unknown queries are refused outright.

```php
'persisted_queries' => [
    'enabled' => true,
    'store'   => 'cache',   // 'cache' (runtime registration) or 'array' (the static 'map')
    'ttl'     => 3600,      // cache store only; null = forever
    'map'     => [],        // id => query, for the 'array' store
    'apq'     => true,      // accept Automatic Persisted Query registration
    'only'    => false,     // trusted documents: refuse unregistered query text
],
```

### Automatic Persisted Queries (APQ)

This is Apollo's protocol, supported by Apollo Client's `createPersistedQueryLink`, urql and others:

1. The client sends only the hash:
   `{"extensions":{"persistedQuery":{"version":1,"sha256Hash":"ecf4…"}}}`
2. The server doesn't know the hash yet and answers with the `PersistedQueryNotFound` error.
3. The client resends the hash **and** the query. Laragraph checks that the hash really is
   SHA-256 of the query (otherwise `PERSISTED_QUERY_HASH_MISMATCH`), stores it and executes it.
4. From then on, every client sends only the hash.

### Trusted documents

For first-party APIs (your web app, your mobile app), you can lock the endpoint to the operations
your clients ship. Extract the operations at build time (with GraphQL Code Generator's persisted
documents or Relay's persisted queries) and deploy them as a map **keyed by SHA-256 hash**:

```php
'persisted_queries' => [
    'enabled' => true,
    'store'   => 'array',
    'map'     => require base_path('graphql/persisted-documents.php'),  // sha256 => query
    'apq'     => false,
    'only'    => true,
],
```

With `only => true`, a request carrying a query that isn't in the store under its hash is refused
with `PERSISTED_QUERY_REQUIRED`, whatever else it contains. Requests that send only the id
(`{"queryId": "<sha256>"}` or the APQ extension) run the stored query. An attacker can no longer
send arbitrary queries at all, which makes depth and complexity limits a second line of defence.

## CORS

The endpoint is a normal Laravel route, so Laravel's CORS middleware applies. Include the prefix in
`config/cors.php`:

```php
'paths' => ['api/*', 'graphql', 'graphql/*', 'broadcasting/auth'],
```

## GraphiQL

GraphiQL is served at `/graphql/graphiql` while `graphiql.enabled` allows it. With the default
`null`, that means only while `APP_DEBUG=true`. To serve it in production, for example to staff, enable
it explicitly and protect it:

```php
'graphiql' => [
    'enabled'    => true,
    'middleware' => ['web', 'auth', 'can:viewGraphiql'],
    'title'      => 'Acme API',
],
```

GraphiQL needs introspection to show the schema, and introspection is also
[disabled outside debug](10-security.md#introspection) by default.

### Automatic auth

Because the IDE is served from the same origin as the endpoint, it rides the framework's own
cookie auth: when you are logged into the application (a session cookie is present), queries are
already authenticated — no header to paste. The header editor stays enabled for when you want to
send a bearer token or override a header by hand, and the active headers are mirrored into it so
you can always see what a request will carry.

### Role / token picker (development only)

Outside `production` the IDE shows an **"Act as"** picker so you can switch identity — and thus
field-level authorization — between roles without juggling browser tabs and pasted tokens. It
defaults to *"Current session"* (labelled with the logged-in user, so you know who you're testing
as without a `me { id }` query), and lists each configured test user.

```php
'graphiql' => [
    'auth_picker' => null, // auto: on whenever APP_ENV is not production
    'test_users'  => [
        'member'    => 'member@example.test',
        'moderator' => 'moderator@example.test',
        'admin'     => 'admin@example.test',
    ],
],
```

Tokens are minted by a development-only endpoint at `/{prefix}/graphiql/dev-tokens`:

- It is registered only when the picker is on (default: `APP_ENV` not `production`).
- It returns a Sanctum token for each seeded `test_users` entry that exists. Install Sanctum and
  give your user model the `HasApiTokens` trait; without it the picker still works for
  session-cookie auth but the role entries carry no token.
- It **refuses to run under `APP_ENV=production`**, returning `404` even if a stale route somehow
  survives a cached-config deploy.

Your selection is remembered in `localStorage`, so reopening the IDE keeps the last identity.

### Production safety banner

If `graphiql.enabled` serves the IDE while `APP_ENV=production`, the page shows a red banner
naming the risk and how to close it (`enabled => false`, or leave it `null` with `APP_DEBUG=false`).
The banner is a reminder, not an access control — still guard the route with `middleware` if you
opt in.
