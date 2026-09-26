# 10. Security

A GraphQL endpoint lets clients compose their own queries. That flexibility is the point, but it
also means one request can ask for a lot of work or a lot of data. Laragraph's defaults are
**secure out of the box**. This chapter explains each protection and when to adjust it.

## The defaults at a glance

| Protection | Default | Setting |
|---|---|---|
| GraphiQL | Only while `APP_DEBUG=true` | `graphiql.enabled` |
| Introspection | Only while `APP_DEBUG=true` | `security.disable_introspection` |
| Query depth | 15 levels | `security.query_max_depth` |
| Query complexity | 500 points | `security.query_max_complexity` |
| Aliases per document | 30 | `security.max_aliases` |
| Page size | 100 items | `pagination.max_per_page` |
| Batch size | Batching off; 10 operations when on | `batching.*` |
| Internal error messages | Hidden (`Internal server error`) | `APP_DEBUG` |
| Mutations over GET | Refused (405) | always on |
| Response cache | Per user | `cache.response.scope` |
| Subscription channels | Only the subscriber may listen | `subscriptions.authorize_channel` |
| Multipart upload paths | Only into `variables` | always on |

## Query limits

All limits are checked when the document is **validated**, before any resolver runs. A rejected
query costs almost nothing.

### Depth

```graphql
{ posts { edges { node { author { organization { members { posts { … } } } } } } } }
```

Circular relations (user → organization → members → posts → author → …) allow arbitrarily deep
queries. `query_max_depth` caps the nesting. 15 leaves room for Relay connections, which use
three levels per list: `posts → edges → node`. The standard introspection query needs 11, so
don't go below that if you use GraphiQL or code generators.

### Complexity

Depth doesn't catch *wide* queries. Complexity adds up the cost of every requested field:
1 per field by default, and more for fields that declare `complexity()`:

```php
// example/app/GraphQL/Queries/SearchQuery.php
public function complexity(): ?int
{
    return 20;      // a LIKE search over three tables
}
```

Give expensive fields a higher cost: full-text searches, external API calls, aggregates. A query
over the limit fails with "Max query complexity should be 500 but got 731."

Turn on `extensions.query_complexity` (see [Observability](12-observability.md#response-extensions))
to surface the computed cost in every response, so clients can self-throttle instead of guessing.

### Aliases

Aliases let one document run the same field many times:

```graphql
{ a1: login(email: "a@x.io", password: "1") { token }  a2: login(…) { token }  … }
```

`max_aliases` (30) stops brute-force attempts that hide in a single request, and multiplication
attacks against expensive fields.

### Page sizes

`ConnectionType::paginate()` and `simplePaginate()` cap `first`, `last` and `per_page` at
`pagination.max_per_page` (100), so `posts(first: 1000000)` returns 100 items.

### Batches

Every operation in a batch is limited on its own, so `batching.max_operations` bounds the total.
Keep batching off unless your client uses it.

## Introspection

Introspection lets anyone download your full schema. That's useful for tooling, and useful for
attackers mapping your API. By default (`null`) it's enabled only while `APP_DEBUG=true`:

```php
'security' => [
    'disable_introspection' => null,    // null: follow APP_DEBUG; true: always off; false: always on
],
```

Public APIs that publish their schema anyway can set `false`. For code generation against a
production-like environment, export the SDL at build time (`php artisan laragraph:schema:export`)
instead of enabling introspection.

## Custom validation rules

Add your own document-level rules. They're webonyx `ValidationRule` classes, and they run for every
document along with the built-in ones:

```php
// example/config/laragraph.php
'validation' => [
    'rules' => [
        App\GraphQL\Validation\MaxRootFieldsRule::class,   // resolved from the container
    ],
],
```

```php
// example/app/GraphQL/Validation/MaxRootFieldsRule.php
class MaxRootFieldsRule extends ValidationRule
{
    public function __construct(private readonly int $max = 10) {}

    public function getVisitor(ValidationContext $context): array
    {
        return [
            NodeKind::OPERATION_DEFINITION => function (OperationDefinitionNode $operation) use ($context): void {
                $count = count($operation->selectionSet->selections);

                if ($count > $this->max) {
                    $context->reportError(new Error(
                        "An operation may select at most {$this->max} root fields; this one selects {$count}.",
                        [$operation],
                    ));
                }
            },
        ];
    }
}
```

Rules can also be added at runtime with `Laragraph::addValidationRule(new MyRule())`. A custom rule of
the same class as a built-in one (such as `QueryDepth`) replaces the built-in, which is how you
configure it differently.

## Errors don't leak internals

Exceptions that aren't client-safe are reported as `"Internal server error"` with
category `internal`. SQL, file paths and exception messages stay on the server. Only while
`APP_DEBUG=true` does each error also carry `debugMessage` and `trace`. So:

- **Never run production with `APP_DEBUG=true`.** It also turns GraphiQL and introspection on.
- Throw a `GraphQLException` (or `GraphQL\Error\Error`) for messages that clients should see.
- Report internal errors through Laravel's logging, or through the `QueryError` event
  (see [Observability](12-observability.md)).

If you turn on `errors.negotiate_locale` (see
[Error Handling & Localization](17-error-handling-and-localization.md)), the request's
`Accept-Language` header is never used to build a translation-file path directly: it's always
checked against `errors.supported_locales` and a strict locale-tag format first. Set
`supported_locales` to exactly the locales you ship — an unvalidated, attacker-controlled locale
string used to build a file path would otherwise be a path-traversal surface (Laravel's own
translator doesn't sanitize it either).

## Authorization

Most GraphQL data leaks are authorization bugs, not query-cost problems. Follow the checklist in
[Authentication & authorization](04-authentication-and-authorization.md#checklist). In short:
authorize every mutation, filter every list, protect sensitive fields on the *type*, and never
trust ids from arguments for "my" data.

## Rate limiting

- **Per field:** `ThrottleMiddleware` on sensitive fields such as login, password reset and
  expensive searches (see [Queries & mutations](03-queries-and-mutations.md#field-middleware)).
- **Per endpoint:** Laravel's `throttle` middleware on the route:

```php
'route' => [
    'middleware' => ['throttle:api'],
],
```

## Trusted documents

For APIs used only by your own clients, the strongest protection is to accept **only the queries
your clients ship** (`persisted_queries.only`). Arbitrary queries are then refused before
parsing. See [persisted queries](08-http-api.md#trusted-documents).

## Hardening checklist

- [ ] `APP_DEBUG=false` in production. GraphiQL and introspection then switch off by themselves.
- [ ] Every mutation, and every query returning private data, authorizes.
- [ ] Sensitive type fields check the viewer.
- [ ] Expensive fields declare `complexity()`.
- [ ] Login and other credential endpoints are throttled.
- [ ] `route.middleware` includes a rate limiter.
- [ ] Admin-only operations live in a separate schema guarded by middleware.
- [ ] `cache.response.scope` is `user` (the default) unless every response is public.
- [ ] Subscription `cache_store` is shared and private (Redis or the database, not `file` across servers).
- [ ] CORS allows only your front-end origins.
- [ ] Consider trusted documents for first-party-only APIs.
- [ ] If `errors.negotiate_locale` is on, `errors.supported_locales` lists exactly the locales you ship.
