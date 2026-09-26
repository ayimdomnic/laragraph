# 13. Testing

Test your API **over HTTP, the way clients use it**. That exercises routing, middleware,
authentication, parsing, validation, authorization and serialisation together. The example's
56 tests in [`tests/Feature/GraphQL`](../example/tests/Feature/GraphQL) are all written this way.

## A base test case

```php
// example/tests/Feature/GraphQL/GraphQLTestCase.php (abridged)
abstract class GraphQLTestCase extends TestCase
{
    use RefreshDatabase;

    protected function graphql(string $query, array $variables = [], ?User $as = null, string $endpoint = '/graphql'): TestResponse
    {
        return $this->postJson($endpoint, ['query' => $query, 'variables' => $variables], $this->headersFor($as));
    }

    protected function headersFor(?User $user): array
    {
        return $user === null ? [] : ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }
}
```

A test then reads like the request it makes. `$this->member`, `$this->admin` and `$this->acme`
(an organization) below aren't magic — `GraphQLTestCase::setUp()` (not shown above) creates them
once per test, an admin and a member in the same organization, exactly like any other Laravel
`RefreshDatabase` fixture setup:

```php
public function test_members_see_their_own_email_but_not_other_peoples(): void
{
    $this->graphql('query ($id: ID!) { user(id: $id) { email } }', ['id' => $this->member->id], as: $this->member)
        ->assertOk()
        ->assertJsonPath('data.user.email', $this->member->email);
}
```

With session or Sanctum authentication, `actingAs($user)` (or `Sanctum::actingAs($user)`) replaces
the header helper.

### Token guards between requests

Laravel's test client reuses one application for every request in a test. Guards cache the
user they resolved, and the JWT package also caches the token, so **a second request without a
token can still be authenticated as the first request's user**. That hides authorization bugs. Reset
the state before each request:

```php
public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
{
    $this->app['auth']->forgetGuards();
    JWTAuth::unsetToken();                   // the JWTAuth facade's token…
    $this->app['tymon.jwt']->unsetToken();   // …and the one the JWT guard reads

    return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
}
```

(`forgetGuards()` alone is enough for Sanctum tokens.)

## Shippable test helpers

The `graphql()` helper and the `assertJsonPath('errors.0.extensions...')` idioms below are so
common that Laragraph ships them as a trait plus a handful of `TestResponse` macros — add
`Ayimdomnic\Laragraph\Testing\MakesGraphQLRequests` to your own test base class instead of
hand-rolling the helper shown above:

```php
abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    use \Ayimdomnic\Laragraph\Testing\MakesGraphQLRequests;

    // Laragraph doesn't know which auth package you use — override this once.
    protected function graphqlAuthHeaders(mixed $as): array
    {
        return $as === null ? [] : ['Authorization' => 'Bearer ' . JWTAuth::fromUser($as)];
    }
}
```

```php
$this->graphql('query ($id: ID!) { user(id: $id) { email } }', ['id' => $this->member->id], as: $this->member)
    ->assertNoGraphQLErrors()
    ->assertGraphQLData('user.email', $this->member->email);
```

| Macro | Equivalent to |
|---|---|
| `assertGraphQLData($path, $value)` | `assertJsonPath("data.{$path}", $value)` |
| `assertGraphQLErrors()` / `assertNoGraphQLErrors()` | asserting `errors` is present-and-non-empty / absent-or-empty |
| `assertGraphQLErrorCategory($category)` | `assertJsonPath('errors.0.extensions.category', $category)` |
| `assertGraphQLErrorCode($code)` | `assertJsonPath('errors.0.extensions.code', $code)` |
| `assertGraphQLValidationError($field, $message = null)` | asserting the `validation` category, that `extensions.validation.{$field}` exists, and optionally that it contains `$message` |

This is unrelated to `tests/TestCase.php` in Laragraph's own repository, which only exists to test
the package itself and is never autoloaded into a consumer application — `MakesGraphQLRequests`
is the one meant for your app.

## What to assert

The examples below use `assertJsonPath` directly — reach for the macros above instead when the
shape matches; they're the same assertions, just named for what they mean.

### Data

```php
$response->assertJsonPath('data.post.title', 'Hello')
    ->assertJsonMissingPath('errors');
```

Remember that GraphQL errors come back with **HTTP 200**, so `assertOk()` alone proves nothing. Also
check that `errors` is absent, or check its content.

### Validation errors

```php
$this->graphql('mutation { register(name: "A", email: "not-an-email", password: "x",
    password_confirmation: "y", organizationSlug: "nope") { token } }')
    ->assertJsonPath('errors.0.extensions.category', 'validation')
    ->assertJsonStructure(['errors' => [['extensions' => ['validation' => ['email', 'password', 'organizationSlug']]]]]);
```

### Authorization

Test the **denied** paths at least as thoroughly as the allowed ones: guests, other users,
other organizations and missing records.

```php
$this->graphql('{ users { edges { node { id } } } }', as: $this->member)
    ->assertJsonPath('errors.0.extensions.category', 'authorization')
    ->assertJsonPath('data.users', null);
```

### N+1 regressions

Count queries at two data sizes and assert that the count doesn't grow:

```php
// example/tests/Feature/GraphQL/BatchingNPlusOneTest.php
config(['laragraph.cache.response.enabled' => false]);   // measure the database, not the cache

$this->addOrganizations(2);
$few = $this->countQueries();

$this->addOrganizations(10);
$many = $this->countQueries();

$this->assertSame($few, $many);
```

### Subscriptions

With `QUEUE_CONNECTION=sync`, `broadcastLater()` runs immediately. Fake the message event to
capture what each subscriber receives:

```php
Event::fake([SubscriptionMessage::class]);

$subscriberId = $this->graphql('subscription { postPublished(organizationId: 1) { title } }', as: $this->member)
    ->json('extensions.subscription.subscriberId');

$this->graphql('mutation { publishPost(id: 7) { id } }', as: $this->admin);

Event::assertDispatched(SubscriptionMessage::class, fn (SubscriptionMessage $m) =>
    $m->subscriberId === $subscriberId
    && $m->payload['data']['postPublished']['title'] === 'Hello');
```

To assert that the fan-out is *queued*, use `Queue::fake()` and
`Queue::assertPushed(BroadcastSubscriptionUpdates::class)`.

### File uploads

```php
Storage::fake('public');

$this->post('/graphql', [
    'operations' => json_encode([
        'query'     => 'mutation ($file: Upload!) { uploadAvatar(file: $file) { avatarUrl } }',
        'variables' => ['file' => null],
    ]),
    'map' => json_encode(['0' => ['variables.file']]),
    '0'   => UploadedFile::fake()->image('avatar.png'),
], $this->headersFor($this->member) + ['Content-Type' => 'multipart/form-data'])
    ->assertJsonMissingPath('errors');
```

### Events and logs

```php
Log::spy();

$this->postJson('/graphql', ['query' => 'query Dashboard { me { id } }', 'operationName' => 'Dashboard']);

Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $context['operation'] === 'Dashboard');
```

## Test configuration tips

- **Response cache**: every test starts with an empty cache (use the `array` store), but *within*
  one test, repeating a query may return a cached response. Disable the cache in tests that change
  data behind the API's back, or count queries.
- **Production behaviour**: GraphiQL and introspection follow `APP_DEBUG`. Test the production
  defaults with `config(['app.debug' => false])`. The GraphiQL route is registered at boot, so
  test that by booting with `APP_DEBUG=false`.
- **Rate limits** use the cache. With the `array` store they reset between tests.
- **Schema validity**: run `php artisan laragraph:validate` in CI. It fails on broken types before any
  test does.

## Testing without HTTP

For unit-style tests of a single operation, call `Laragraph::execute()` directly:

```php
$this->actingAs($user);

$result = Laragraph::execute('{ me { name } }');

$this->assertSame($user->name, $result['data']['me']['name']);
```

This goes through validation, authorization, the cache and events, but skips routes and
middleware.

## Snapshotting the schema

Catch accidental breaking changes by committing the SDL and comparing it in CI:

```php
public function test_the_schema_has_not_changed_unexpectedly(): void
{
    Artisan::call('laragraph:schema:export');

    $this->assertSame(trim(file_get_contents(base_path('schema.graphql'))), trim(Artisan::output()));
}
```

Regenerate the snapshot on purpose with
`php artisan laragraph:schema:export --output=schema.graphql` when the change is intended.
