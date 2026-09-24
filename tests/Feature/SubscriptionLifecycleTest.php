<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Subscriptions\BroadcastSubscriptionUpdates;
use Ayimdomnic\Laragraph\Subscriptions\SubscriberStoreInterface;
use Ayimdomnic\Laragraph\Subscriptions\SubscriptionMessage;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Subscription;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class NewsSubscription extends Subscription
{
    public function type(): Type
    {
        return Type::string();
    }

    public function subscribe(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return ['news', 'alerts'];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return (string) $root;
    }
}

class LifecycleQuery extends Query
{
    public function type(): Type
    {
        return Type::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'ok';
    }
}

class SubscriptionLifecycleTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.subscriptions.enabled', true);
        $app['config']->set('broadcasting.default', 'null');
        $app['config']->set('laragraph.schemas.default', [
            'query'        => ['ok' => LifecycleQuery::class],
            'subscription' => ['news' => NewsSubscription::class],
        ]);
    }

    private function subscribe(): string
    {
        return $this->graphql('subscription { news }')['extensions']['subscription']['subscriberId'];
    }

    private function store(): SubscriberStoreInterface
    {
        return app(SubscriberStoreInterface::class);
    }

    public function test_unsubscribe_removes_the_subscriber_from_every_channel(): void
    {
        $id = $this->subscribe();
        $this->assertArrayHasKey($id, $this->store()->subscribers('news'));
        $this->assertArrayHasKey($id, $this->store()->subscribers('alerts'));

        $this->assertTrue(Laragraph::unsubscribe($id));

        $this->assertSame([], $this->store()->subscribers('news'));
        $this->assertSame([], $this->store()->subscribers('alerts'));
        $this->assertFalse(Laragraph::unsubscribe($id));
        $this->assertSame(0, Laragraph::broadcast('news', 'x'));
    }

    public function test_the_owner_can_unsubscribe_over_http(): void
    {
        $this->actingAs(new GenericUser(['id' => 1]));
        $id = $this->subscribe();

        $this->deleteJson("/graphql/subscriptions/{$id}")->assertNoContent();

        $this->assertSame([], $this->store()->subscribers('news'));
    }

    public function test_other_users_cannot_unsubscribe_someone_else(): void
    {
        $this->actingAs(new GenericUser(['id' => 1]));
        $id = $this->subscribe();

        $this->actingAs(new GenericUser(['id' => 2]));

        $this->deleteJson("/graphql/subscriptions/{$id}")
            ->assertNotFound()
            ->assertJsonPath('errors.0.extensions.code', 'SUBSCRIPTION_NOT_FOUND');

        $this->assertArrayHasKey($id, $this->store()->subscribers('news'));
    }

    public function test_guest_subscriptions_are_cancelled_with_their_id(): void
    {
        $id = $this->subscribe();

        $this->deleteJson("/graphql/subscriptions/{$id}")->assertNoContent();
    }

    public function test_a_guest_cannot_cancel_a_users_subscription(): void
    {
        $this->actingAs(new GenericUser(['id' => 1]));
        $id = $this->subscribe();

        $this->app['auth']->forgetGuards();
        $this->assertTrue(auth()->guest());

        $this->deleteJson("/graphql/subscriptions/{$id}")->assertNotFound();
        $this->assertArrayHasKey($id, $this->store()->subscribers('news'));
    }

    public function test_a_different_user_class_with_the_same_id_cannot_unsubscribe(): void
    {
        $this->actingAs(new GenericUser(['id' => 1]));
        $id = $this->subscribe();

        $this->actingAs(new class (['id' => 1]) extends GenericUser {});

        $this->deleteJson("/graphql/subscriptions/{$id}")->assertNotFound();
    }

    public function test_unknown_subscriptions_are_not_found(): void
    {
        $this->deleteJson('/graphql/subscriptions/does-not-exist')->assertNotFound();
    }

    public function test_the_endpoint_is_inert_when_subscriptions_are_disabled(): void
    {
        $id = $this->subscribe();
        config(['laragraph.subscriptions.enabled' => false]);

        $this->deleteJson("/graphql/subscriptions/{$id}")->assertNotFound();
    }

    public function test_records_without_an_identity_cannot_be_cancelled_over_http(): void
    {
        $this->store()->store('news', 'legacy', ['query' => 'subscription { news }', 'variables' => [], 'operationName' => null, 'schemaName' => 'default']);

        $this->deleteJson('/graphql/subscriptions/legacy')->assertNotFound();
    }

    public function test_broadcast_later_queues_the_fan_out(): void
    {
        Queue::fake();
        config(['laragraph.subscriptions.queue' => ['connection' => 'redis', 'queue' => 'graphql']]);

        Laragraph::broadcastLater('news', 'breaking');

        Queue::assertPushedOn('graphql', BroadcastSubscriptionUpdates::class, fn(BroadcastSubscriptionUpdates $job): bool => $job->channel === 'news'
            && $job->payload === 'breaking'
            && $job->connection === 'redis');
    }

    public function test_the_queued_job_notifies_subscribers(): void
    {
        Event::fake([SubscriptionMessage::class]);
        $id = $this->subscribe();

        dispatch_sync(new BroadcastSubscriptionUpdates('news', 'breaking'));

        Event::assertDispatched(SubscriptionMessage::class, fn(SubscriptionMessage $message): bool => $message->subscriberId === $id
            && $message->payload['data']['news'] === 'breaking');
    }
}
