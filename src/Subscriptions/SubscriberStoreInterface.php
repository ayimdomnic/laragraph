<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Subscriptions;

/**
 * Contract for a subscriber store.
 *
 * A subscriber store maps a GraphQL subscription channel to the set of
 * subscribers currently registered on it — each subscriber's record carries
 * everything needed to re-execute their original subscription query later
 * (query text, variables, operation name, schema) when
 * {@see \Ayimdomnic\Laragraph\Laragraph::broadcast()} is called.
 *
 * ## Implementations provided
 *
 * - {@see CacheSubscriberStore} — backed by any Laravel cache driver
 */
interface SubscriberStoreInterface
{
    /**
     * Register a subscriber's record under a channel.
     *
     * @param  array{query: string, variables: array, operationName: ?string, schemaName: string}  $record
     */
    public function store(string $channel, string $subscriberId, array $record, ?int $ttl = null): void;

    /**
     * All subscriber records currently registered on a channel.
     *
     * @return array<string, array{query: string, variables: array, operationName: ?string, schemaName: string}>
     */
    public function subscribers(string $channel): array;

    /**
     * Remove a subscriber from a channel.
     */
    public function forget(string $channel, string $subscriberId): void;
}
