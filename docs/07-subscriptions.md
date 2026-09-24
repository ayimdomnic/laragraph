# 7. Subscriptions

Subscriptions push updates to clients in real time: "tell me whenever a post is published in my
organization". Laragraph delivers them over **Laravel Broadcasting**, so any broadcaster works
(Reverb, Pusher, Ably, Soketi), and clients receive them with **Laravel Echo**. You don't run a
separate GraphQL WebSocket server.

## How it works

```
 client                              your app                                 broadcaster
   │  subscription { postPublished(organizationId: 1) { id title } }
   │ ───────────────────────────────▶ authorize() → subscribe() returns a channel
   │                                   stores {query, variables, user} under a subscriberId
   │ ◀─────────────────────────────── { data: { postPublished: null },
   │                                    extensions: { subscription: { channel, subscriberId } } }
   │
   │  Echo.private('graphql-subscriber.{subscriberId}')  ──────────────────────▶ (auth: owner only)
   │
   │                                   … later, a post is published …
   │                                   Laragraph::broadcastLater('organization.1.posts', $post)
   │                                     for each subscriber on that channel:
   │                                       re-run *their* query, as *them*, with $post as $root
   │ ◀───────────────────────────────────────────────────────────── .GraphQLSubscriptionUpdate
   │   { data: { postPublished: { id: "7", title: "Hello" } } }
```

The subscription request itself doesn't open a socket. It **registers** a subscriber and tells the
client where to listen. Updates are pushed by your code, with `broadcast()` or `broadcastLater()`,
whenever something happens.

## 1. Enable subscriptions

```php
// config/laragraph.php
'subscriptions' => [
    'enabled'           => true,
    'driver'            => 'broadcast',      // or 'log' during development
    'cache_store'       => null,             // where subscribers are stored (null: default store)
    'ttl'               => 3600,             // seconds a registration lives
    'channel_prefix'    => 'graphql-subscriber',
    'authorize_channel' => true,             // only the owner may listen (see below)
    'queue' => ['connection' => null, 'queue' => null],   // for broadcastLater()
],
```

Set up Laravel Broadcasting as usual (`php artisan install:broadcasting` on Laravel 11+) and make
sure `/broadcasting/auth` authenticates your API users. The example uses JWT:

```php
// example/bootstrap/app.php
->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['auth:api']])
```

Subscribers are kept in the cache, so `cache_store` must be shared by every web server and queue
worker. Use Redis, Memcached or the database store in production, never `array` (Octane also empties
the `array` store after every request), and not `file` on
more than one machine.

## 2. Write the subscription

```php
// example/app/GraphQL/Subscriptions/PostPublishedSubscription.php
namespace App\GraphQL\Subscriptions;

use App\Models\Post;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Subscription;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class PostPublishedSubscription extends Subscription
{
    public static function channelFor(int|string $organizationId): string
    {
        return "organization.{$organizationId}.posts";
    }

    public function type(): Type
    {
        return Laragraph::type('Post');          // nullable! see below
    }

    public function args(): array
    {
        return ['organizationId' => ['type' => Type::nonNull(Type::id())]];
    }

    /** Only members of the organization may subscribe to its posts. */
    public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
    {
        $user = $context->user();

        return $user !== null && (string) $user->organization_id === (string) $args['organizationId'];
    }

    /** Which channel(s) this subscriber listens to. */
    public function subscribe(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return self::channelFor($args['organizationId']);
    }

    /** $root is the payload given to broadcast()/broadcastLater(). */
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return $root instanceof Post ? $root : Post::findOrFail($root);
    }
}
```

A subscription class has three parts:

- **`subscribe()`** runs once, when the client subscribes, and returns a channel name. It may
  also return an array of channel names. Channels are *internal* topic names that you choose. They
  aren't broadcast channels and clients never see them: the example uses one topic per organization.
- **`resolve()`** runs for **every update**, with the broadcast payload as `$root`.
- **`authorize()`, `rules()`, `guards()`, `policy()` and middleware** work exactly as they do on
  queries. They run when the client subscribes **and again for every update**, so a user who
  leaves the organization stops receiving its posts.

> **The return type must be nullable.** When the client subscribes, there's no post yet, so the
> field resolves to `null`. A non-null type (`Type::nonNull(...)`) turns that into an error and
> the registration fails.

## 3. Publish updates

Call `broadcastLater()` wherever the event happens, whether in a mutation, a model observer or a job:

```php
// example/app/GraphQL/Mutations/PublishPostMutation.php
public static function notifySubscribers(Post $post): void
{
    Laragraph::broadcastLater(PostPublishedSubscription::channelFor($post->organization_id), $post);
}
```

| Method | Behaviour |
|---|---|
| `Laragraph::broadcastLater($channel, $payload)` | Queues a `BroadcastSubscriptionUpdates` job on `subscriptions.queue`. The request that triggered the event doesn't wait. **Use this in HTTP requests.** |
| `Laragraph::broadcast($channel, $payload)` | Runs every subscriber's query now and returns how many were notified. Use it inside jobs, or in tests. |

The payload may be a model, an id or an array, whatever your `resolve()` expects. Queued payloads
are serialised, and Eloquent models are restored with `SerializesModels`, so pass models freely.

### Each update runs as its subscriber

Every subscriber's query runs **authenticated as the user who subscribed**, never as the user
whose action triggered the broadcast:

- `$context->user()`, `auth()->user()` and `Gate::allows()` refer to the subscriber, so field
  privacy (`User.email`) and policies give each subscriber exactly what they may see.
- Each run gets a fresh request, session and auth state, so nothing leaks between subscribers or
  back into the request that called `broadcast()`.
- If the subscriber's user has been deleted, the subscription is removed instead of running as a
  guest.

## 4. Listen on the client

The registration response tells the client where to listen:

```json
{
  "data": { "postPublished": null },
  "extensions": {
    "subscription": {
      "channel": "organization.1.posts",
      "subscriberId": "9b1d3c0e-6f0b-4f4e-8a36-0a5c3f7f2c11"
    }
  }
}
```

Listen with Echo on the **private** channel `{channel_prefix}.{subscriberId}` for the event
`.GraphQLSubscriptionUpdate` (note the leading dot: the event name is custom):

```js
import Echo from 'laravel-echo';

const { data, extensions } = await graphql(`
  subscription ($org: ID!) { postPublished(organizationId: $org) { id title author { name } } }
`, { org: 1 });

const { subscriberId } = extensions.subscription;

window.Echo.private(`graphql-subscriber.${subscriberId}`)
  .listen('.GraphQLSubscriptionUpdate', (result) => {
    // result is a normal GraphQL response: { data: { postPublished: {...} } } or { errors: [...] }
    console.log(result.data.postPublished.title);
  });
```

### Channel authorization

With `authorize_channel => true`, Laragraph registers the rule for
`{channel_prefix}.{subscriberId}` itself: **only the user who created the subscription** can join
its channel. Nothing is needed in `routes/channels.php`. Set it to `false` to write your own rule.

Guests may subscribe if your `authorize()` allows it, but private channels require an authenticated
user, so **guest subscribers can't receive updates** over the built-in channel. Require
authentication in `authorize()` for subscriptions that matter.

## 5. Unsubscribe

When the client no longer needs updates, it leaves the channel and deletes the registration:

```js
window.Echo.leave(`graphql-subscriber.${subscriberId}`);

await fetch(`/graphql/subscriptions/${subscriberId}`, {
  method: 'DELETE',
  headers: { Authorization: `Bearer ${token}` },
});
```

The endpoint answers `204 No Content`. Only the owner can delete a subscription. An unknown id and
someone else's id both answer `404` with code `SUBSCRIPTION_NOT_FOUND`, so ids can't be probed.
From PHP, call `Laragraph::unsubscribe($subscriberId)`.

Registrations also expire after `ttl` seconds, so clients that disappear without unsubscribing
clean up after themselves. Long-lived clients should re-subscribe before the TTL runs out.

## Developing without a broadcaster

Set `'driver' => 'log'` and every update is written to the log (`logging.channel`) instead of
being broadcast. You can watch the flow with `php artisan pail` while calling mutations from GraphiQL.

## Testing

Fake the `SubscriptionMessage` event to capture updates, and run `broadcastLater()` on the `sync` queue
(`QUEUE_CONNECTION=sync` in `phpunit.xml`) or call `broadcast()` directly:

```php
use Ayimdomnic\Laragraph\Subscriptions\SubscriptionMessage;

Event::fake([SubscriptionMessage::class]);

// graphql($query, $variables, $as) is the example's helper: it sends a JWT for $as.
$subscriberId = $this->graphql('subscription { postPublished(organizationId: 1) { title } }', [], $member)
    ->json('extensions.subscription.subscriberId');

$this->graphql('mutation { publishPost(id: 7) { id } }', [], $author);

Event::assertDispatched(SubscriptionMessage::class, fn ($message) =>
    $message->subscriberId === $subscriberId
    && $message->payload['data']['postPublished']['title'] === 'Hello');
```

See [`SubscriptionsTest`](../example/tests/Feature/GraphQL/SubscriptionsTest.php) for the
complete set, including authorization, visibility per subscriber and unsubscribing.
