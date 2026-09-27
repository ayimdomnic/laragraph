<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Contracts;

use Ayimdomnic\Laragraph\Subscriptions\SubscriberStoreInterface;
use Ayimdomnic\Laragraph\Subscriptions\SubscriptionManager;

/**
 * Registers subscribers and re-executes their queries when application code
 * reports a subscription event.
 *
 * @see SubscriptionManager the built-in implementation.
 *
 * @phpstan-import-type SubscriberRecord from SubscriberStoreInterface
 */
interface SubscriptionManagerInterface
{
    /**
     * Register a new subscriber on one or more channels.
     *
     * @param SubscriberRecord $record
     * @return string The generated subscriber id.
     */
    public function register(mixed $channel, array $record): string;

    /**
     * Remove a subscriber from every channel it subscribed to.
     *
     * @return bool False when the subscriber is unknown.
     */
    public function unsubscribe(string $subscriberId): bool;

    /**
     * Whether the current user created the subscription.
     */
    public function ownedByCurrentUser(string $subscriberId): bool;

    /**
     * Re-execute every subscriber's original query on a channel with
     * $payload as the root value, and push each result to that subscriber.
     *
     * @return int The number of subscribers notified.
     */
    public function broadcast(string $channel, mixed $payload = null): int;

    /**
     * Like {@see broadcast()}, but runs the fan-out on the queue so the
     * request that triggered the event does not wait for every subscriber's
     * query.
     */
    public function broadcastLater(string $channel, mixed $payload = null): void;
}
