<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Subscriptions\SubscriberStoreInterface;
use Ayimdomnic\Laragraph\Subscriptions\SubscriptionMessage;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Subscription;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Facades\Event;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class PingSubscription extends Subscription
{
    public function type(): Type
    {
        return Type::string();
    }

    public function subscribe(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'pings';
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        // $root is the payload passed to Laragraph::broadcast()
        return (string) $root;
    }
}

class NoChannelSubscription extends Subscription
{
    public function type(): Type
    {
        return Type::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return (string) $root;
    }
}

class MultiChannelSubscription extends Subscription
{
    public function type(): Type
    {
        return Type::string();
    }

    public function subscribe(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return ['channel-a', 'channel-b'];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return (string) $root;
    }
}

class ProtectedSubscription extends Subscription
{
    public function type(): Type
    {
        return Type::string();
    }

    public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
    {
        return false;
    }

    public function subscribe(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'secret-channel';
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return (string) $root;
    }
}

class SubHelloQuery extends Query
{
    public function type(): Type
    {
        return Type::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'hello';
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class SubscriptionTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.subscriptions.enabled', true);

        $app['config']->set('laragraph.schemas.default', [
            'query'        => ['hello' => SubHelloQuery::class],
            'subscription' => [
                'ping'         => PingSubscription::class,
                'noChannel'    => NoChannelSubscription::class,
                'multiChannel' => MultiChannelSubscription::class,
                'protected'    => ProtectedSubscription::class,
            ],
        ]);
    }

    public function test_subscription_operation_registers_a_subscriber_and_returns_channel_info(): void
    {
        $result = $this->graphql('subscription { ping }');

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame('pings', $result['extensions']['subscription']['channel']);
        $this->assertIsString($result['extensions']['subscription']['subscriberId']);
        $this->assertNotSame('', $result['extensions']['subscription']['subscriberId']);

        // The subscribed field itself resolves to null on the registration pass.
        $this->assertNull($result['data']['ping']);
    }

    public function test_subscriber_is_persisted_in_the_store(): void
    {
        $result = $this->graphql('subscription { ping }');
        $subscriberId = $result['extensions']['subscription']['subscriberId'];

        $subscribers = app(SubscriberStoreInterface::class)->subscribers('pings');

        $this->assertArrayHasKey($subscriberId, $subscribers);
        $this->assertSame('subscription { ping }', $subscribers[$subscriberId]['query']);
    }

    public function test_normal_queries_are_unaffected(): void
    {
        $result = $this->graphql('{ hello }');

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame('hello', $result['data']['hello']);
        $this->assertArrayNotHasKey('subscription', $result['extensions'] ?? []);
    }

    public function test_subscriptions_are_rejected_when_disabled(): void
    {
        config(['laragraph.subscriptions.enabled' => false]);

        $result = $this->graphql('subscription { ping }');

        $this->assertArrayHasKey('errors', $result);
        $this->assertStringContainsString('disabled', $result['errors'][0]['message']);
    }

    public function test_subscription_field_with_no_channel_returns_an_error(): void
    {
        $result = $this->graphql('subscription { noChannel }');

        $this->assertArrayHasKey('errors', $result);
        $this->assertStringContainsString('did not resolve a channel', $result['errors'][0]['message']);
    }

    public function test_broadcast_re_executes_the_subscriber_query_and_dispatches_a_message(): void
    {
        Event::fake([SubscriptionMessage::class]);

        $result = $this->graphql('subscription { ping }');
        $subscriberId = $result['extensions']['subscription']['subscriberId'];

        $notified = Laragraph::broadcast('pings', 'hello world');

        $this->assertSame(1, $notified);

        Event::assertDispatched(SubscriptionMessage::class, function (SubscriptionMessage $message) use ($subscriberId) {
            return $message->subscriberId === $subscriberId
                && $message->payload['data']['ping'] === 'hello world';
        });
    }

    public function test_broadcast_notifies_zero_subscribers_for_an_unknown_channel(): void
    {
        Event::fake([SubscriptionMessage::class]);

        $notified = Laragraph::broadcast('nobody-listening', 'payload');

        $this->assertSame(0, $notified);
        Event::assertNotDispatched(SubscriptionMessage::class);
    }

    public function test_broadcast_notifies_every_subscriber_on_the_channel(): void
    {
        Event::fake([SubscriptionMessage::class]);

        $this->graphql('subscription { ping }');
        $this->graphql('subscription { ping }');

        $notified = Laragraph::broadcast('pings', 'update');

        $this->assertSame(2, $notified);
        Event::assertDispatchedTimes(SubscriptionMessage::class, 2);
    }

    public function test_subscribe_returning_a_list_of_channels_registers_on_all_of_them(): void
    {
        Event::fake([SubscriptionMessage::class]);

        $result = $this->graphql('subscription { multiChannel }');
        $subscriberId = $result['extensions']['subscription']['subscriberId'];

        $this->assertSame(['channel-a', 'channel-b'], $result['extensions']['subscription']['channel']);
        $this->assertArrayHasKey($subscriberId, app(SubscriberStoreInterface::class)->subscribers('channel-a'));
        $this->assertArrayHasKey($subscriberId, app(SubscriberStoreInterface::class)->subscribers('channel-b'));

        Laragraph::broadcast('channel-b', 'update');
        Event::assertDispatchedTimes(SubscriptionMessage::class, 1);
    }

    public function test_subscription_operation_that_fails_authorization_is_never_registered(): void
    {
        $result = $this->graphql('subscription { protected }');

        $this->assertArrayHasKey('errors', $result);
        $this->assertSame([], app(SubscriberStoreInterface::class)->subscribers('secret-channel'));
    }

    public function test_operation_name_that_does_not_match_falls_through_to_normal_execution(): void
    {
        // 'Missing' doesn't match the sole 'subscription { ping }' operation in
        // the document, so isSubscriptionOperation() must not treat this as a
        // subscription registration — it should fall through to Laragraph::execute(),
        // which then reports GraphQL's own "unknown operation" error.
        $result = $this->postJson('/graphql', [
            'query'         => 'subscription { ping }',
            'operationName' => 'Missing',
        ])->json();

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayNotHasKey('subscription', $result['extensions'] ?? []);
    }

    public function test_log_driver_writes_to_the_log_instead_of_broadcasting(): void
    {
        config(['laragraph.subscriptions.driver' => 'log']);

        \Illuminate\Support\Facades\Log::shouldReceive('channel')
            ->once()
            ->with(null)
            ->andReturnSelf();
        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->once()
            ->with('GraphQL subscription update', \Mockery::on(
                fn (array $context) => ($context['payload']['data']['ping'] ?? null) === 'hello world'
            ));

        $this->graphql('subscription { ping }');

        Laragraph::broadcast('pings', 'hello world');
    }
}
