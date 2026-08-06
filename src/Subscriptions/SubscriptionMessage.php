<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Subscriptions;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Broadcast to a single subscriber's private channel when
 * {@see \Ayimdomnic\Laragraph\Subscriptions\SubscriptionManager::broadcast()}
 * re-executes their subscription query.
 *
 * Dispatched via Laravel's broadcasting system (`event()`), so delivery uses
 * whichever broadcast driver the host application has configured (Reverb,
 * Pusher, etc.) — this class only decides the channel/event name/payload
 * shape, not the transport.
 *
 * Client-side, listen with Laravel Echo:
 * ```js
 * Echo.private(`graphql-subscriber.${subscriberId}`)
 *     .listen('.GraphQLSubscriptionUpdate', (payload) => { ... });
 * ```
 */
final class SubscriptionMessage implements ShouldBroadcastNow
{
    /**
     * @param  array<string, mixed>  $payload  The re-executed GraphQL result ({data, errors}).
     */
    public function __construct(
        public readonly string $subscriberId,
        public readonly array $payload,
        private readonly string $channelPrefix = 'graphql-subscriber',
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("{$this->channelPrefix}.{$this->subscriberId}");
    }

    public function broadcastAs(): string
    {
        return 'GraphQLSubscriptionUpdate';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
