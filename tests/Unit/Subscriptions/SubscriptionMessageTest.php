<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Subscriptions;

use Ayimdomnic\Laragraph\Subscriptions\SubscriptionMessage;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Broadcasting\PrivateChannel;

class SubscriptionMessageTest extends TestCase
{
    public function test_broadcast_on_returns_a_private_channel_scoped_to_the_subscriber(): void
    {
        $message = new SubscriptionMessage('sub-123', ['data' => ['ping' => 'hi']]);

        $channel = $message->broadcastOn();

        $this->assertInstanceOf(PrivateChannel::class, $channel);
        $this->assertSame('private-graphql-subscriber.sub-123', $channel->name);
    }

    public function test_broadcast_on_honours_a_custom_channel_prefix(): void
    {
        $message = new SubscriptionMessage('sub-123', [], 'my-prefix');

        $this->assertSame('private-my-prefix.sub-123', $message->broadcastOn()->name);
    }

    public function test_broadcast_as_returns_the_event_name(): void
    {
        $message = new SubscriptionMessage('sub-123', []);

        $this->assertSame('GraphQLSubscriptionUpdate', $message->broadcastAs());
    }

    public function test_broadcast_with_returns_the_payload(): void
    {
        $payload = ['data' => ['ping' => 'hello'], 'errors' => []];
        $message = new SubscriptionMessage('sub-123', $payload);

        $this->assertSame($payload, $message->broadcastWith());
    }
}
