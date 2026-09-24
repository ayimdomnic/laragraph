<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Http\GraphQLContext;
use Ayimdomnic\Laragraph\Subscriptions\SubscriberChannel;
use Ayimdomnic\Laragraph\Subscriptions\SubscriberSandbox;
use Ayimdomnic\Laragraph\Subscriptions\SubscriberStoreInterface;
use Ayimdomnic\Laragraph\Subscriptions\SubscriptionMessage;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Subscription;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Auth\GenericUser;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Gate;

class InMemoryUserProvider implements UserProvider
{
    /** @var array<int, GenericUser> */
    public static array $users = [];

    public static function add(int $id): GenericUser
    {
        return self::$users[$id] = new GenericUser(['id' => $id, 'remember_token' => null]);
    }

    public function retrieveById($identifier): ?Authenticatable
    {
        return self::$users[(int) $identifier] ?? null;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void {}

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        return null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return false;
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void {}
}

class OtherUser extends GenericUser {}

class ViewerSubscription extends Subscription
{
    public function type(): Type
    {
        return Type::string();
    }

    public function subscribe(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'viewer-feed';
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $contextUser = $context instanceof GraphQLContext ? $context->user()?->getAuthIdentifier() : 'n/a';

        return sprintf('%s|auth=%s|context=%s', $root, auth()->id() ?? 'guest', $contextUser ?? 'guest');
    }
}

class ViewerQuery extends Query
{
    public function type(): Type
    {
        return Type::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'unused';
    }
}

class SubscriptionIdentityTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.subscriptions.enabled', true);
        $app['config']->set('broadcasting.default', 'null');
        $app['config']->set('auth.providers.users', ['driver' => 'in-memory']);
        $app['config']->set('laragraph.schemas.default', [
            'query'        => ['viewer' => ViewerQuery::class],
            'subscription' => ['viewer' => ViewerSubscription::class],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        InMemoryUserProvider::$users = [];
        Auth::provider('in-memory', fn(): InMemoryUserProvider => new InMemoryUserProvider());
        Event::fake([SubscriptionMessage::class]);
    }

    private function subscribeAs(?Authenticatable $user): string
    {
        if ($user instanceof Authenticatable) {
            $this->actingAs($user);
        }

        $result = $this->graphql('subscription { viewer }');
        $this->assertArrayNotHasKey('errors', $result);

        return $result['extensions']['subscription']['subscriberId'];
    }

    /**
     * @return array<string, string> subscriberId => resolved `viewer` value
     */
    private function dispatchedViewers(): array
    {
        $viewers = [];

        Event::assertDispatched(SubscriptionMessage::class, function (SubscriptionMessage $message) use (&$viewers): bool {
            $viewers[$message->subscriberId] = $message->payload['data']['viewer'] ?? json_encode($message->payload);

            return true;
        });

        return $viewers;
    }

    public function test_updates_resolve_as_the_subscriber_not_the_broadcaster(): void
    {
        $subscriber = $this->subscribeAs(InMemoryUserProvider::add(1));

        $admin = InMemoryUserProvider::add(99);
        $this->actingAs($admin);

        $this->assertSame(1, Laragraph::broadcast('viewer-feed', 'hello'));
        $this->assertSame([$subscriber => 'hello|auth=1|context=1'], $this->dispatchedViewers());

        // The broadcaster's own authentication is untouched afterwards.
        $this->assertSame(99, Auth::id());
        $this->assertSame('web', config('auth.defaults.guard'));
    }

    public function test_guest_subscribers_never_inherit_the_broadcasters_identity(): void
    {
        $subscriber = $this->subscribeAs(null);

        $this->actingAs(InMemoryUserProvider::add(99));

        Laragraph::broadcast('viewer-feed', 'hi');

        $this->assertSame([$subscriber => 'hi|auth=guest|context=guest'], $this->dispatchedViewers());
    }

    public function test_the_broadcasters_session_is_not_visible_to_subscribers(): void
    {
        $subscriber = $this->subscribeAs(null);

        // A session-authenticated broadcaster: the web guard would normally
        // re-read this id from the session store.
        InMemoryUserProvider::add(99);
        $this->app['session.store']->put(Auth::guard('web')->getName(), 99);
        Auth::forgetGuards();
        $this->assertSame(99, Auth::id());

        Laragraph::broadcast('viewer-feed', 'hi');

        $this->assertSame([$subscriber => 'hi|auth=guest|context=guest'], $this->dispatchedViewers());
        $this->assertSame(99, Auth::id());
    }

    public function test_subscribers_whose_user_was_deleted_are_dropped(): void
    {
        $this->subscribeAs(InMemoryUserProvider::add(5));
        unset(InMemoryUserProvider::$users[5]);

        $this->assertSame(0, Laragraph::broadcast('viewer-feed', 'hi'));
        $this->assertSame([], app(SubscriberStoreInterface::class)->subscribers('viewer-feed'));
        Event::assertNotDispatched(SubscriptionMessage::class);
    }

    public function test_a_different_user_type_sharing_the_id_is_never_used(): void
    {
        $this->subscribeAs(new OtherUser(['id' => 7]));
        InMemoryUserProvider::add(7); // a GenericUser — not the OtherUser that subscribed

        $this->assertSame(0, Laragraph::broadcast('viewer-feed', 'hi'));
    }

    public function test_records_without_an_identity_are_treated_as_guests(): void
    {
        app(SubscriberStoreInterface::class)->store('viewer-feed', 'legacy', [
            'query'         => 'subscription { viewer }',
            'variables'     => [],
            'operationName' => null,
            'schemaName'    => 'default',
        ]);
        $this->actingAs(InMemoryUserProvider::add(99));

        Laragraph::broadcast('viewer-feed', 'old');

        $this->assertSame(['legacy' => 'old|auth=guest|context=guest'], $this->dispatchedViewers());
    }

    public function test_the_sandbox_restores_everything_when_the_callback_throws(): void
    {
        $this->actingAs(InMemoryUserProvider::add(99));
        $request = request();

        try {
            app(SubscriberSandbox::class)->run(['guard' => 'web', 'id' => 99, 'type' => null], static function (): never {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(99, Auth::id());
        $this->assertSame($request, request());
    }

    /**
     * Octane handles each request in a clone of the worker's application,
     * while the Gate was created once, in the original — and resolves users
     * through the original's auth manager.
     */
    public function test_policies_answer_for_the_subscriber_when_the_request_runs_in_a_cloned_application(): void
    {
        Gate::define('is-subscriber-7', fn(?Authenticatable $user): bool => $user?->getAuthIdentifier() === 7);
        app(GateContract::class); // resolved in the "worker" application

        $sandbox = clone $this->app;
        Container::setInstance($sandbox);
        Facade::setFacadeApplication($sandbox);

        try {
            $this->actingAs(InMemoryUserProvider::add(99)); // the broadcaster
            InMemoryUserProvider::add(7);

            $asSubscriber = app(SubscriberSandbox::class)->run(['guard' => 'web', 'id' => 7, 'type' => null], static fn(): array => [
                Gate::allows('is-subscriber-7'),
                auth()->id(),
            ]);
            $asGuest = app(SubscriberSandbox::class)->run(null, static fn(): bool => Gate::allows('is-subscriber-7'));

            $this->assertSame([true, 7], $asSubscriber);
            $this->assertFalse($asGuest);
            $this->assertFalse(Gate::allows('is-subscriber-7'), 'the original Gate is restored');
        } finally {
            Container::setInstance($this->app);
            Facade::setFacadeApplication($this->app);
        }
    }

    public function test_identity_uses_the_configured_laragraph_guard(): void
    {
        config(['laragraph.auth.default_guard' => 'web']);
        $this->actingAs(InMemoryUserProvider::add(3));

        $this->assertSame(
            ['guard' => 'web', 'id' => 3, 'type' => GenericUser::class],
            app(SubscriberSandbox::class)->currentIdentity(),
        );
    }

    // -------------------------------------------------------------------------
    // Private channel authorization
    // -------------------------------------------------------------------------

    public function test_only_the_subscription_owner_may_join_its_channel(): void
    {
        $guestSub   = $this->subscribeAs(null);
        $owner      = InMemoryUserProvider::add(1);
        $subscriber = $this->subscribeAs($owner);

        $channel = app(SubscriberChannel::class);

        $this->assertTrue($channel->join($owner, $subscriber));
        $this->assertFalse($channel->join(InMemoryUserProvider::add(2), $subscriber));
        $this->assertFalse($channel->join(new OtherUser(['id' => 1]), $subscriber));
        $this->assertFalse($channel->join($owner, $guestSub));
        $this->assertFalse($channel->join($owner, 'unknown-subscriber'));
    }

    public function test_stores_that_cannot_find_subscribers_deny_joining(): void
    {
        $store = $this->createStub(SubscriberStoreInterface::class);

        $this->assertFalse((new SubscriberChannel($store))->join(InMemoryUserProvider::add(1), 'anything'));
    }

    public function test_the_channel_rule_is_registered_with_the_broadcaster(): void
    {
        $channels = app(BroadcastManager::class)->driver()->getChannels();

        $this->assertSame(SubscriberChannel::class, $channels['graphql-subscriber.{subscriberId}'] ?? null);
    }
}
