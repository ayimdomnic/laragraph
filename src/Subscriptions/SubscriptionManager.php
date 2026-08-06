<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Subscriptions;

use Ayimdomnic\Laragraph\Laragraph;
use GraphQL\Error\DebugFlag;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Registers subscribers and re-executes their queries when application code
 * reports a subscription event.
 *
 * @see \Ayimdomnic\Laragraph\Support\Subscription  for how a subscriber's
 *   channel is resolved during registration
 * @see \Ayimdomnic\Laragraph\Controllers\LaragraphController  for the HTTP
 *   flow that calls register()
 */
final class SubscriptionManager
{
    public function __construct(
        private readonly SubscriberStoreInterface $store,
        private readonly Laragraph $laragraph,
    ) {}

    /**
     * Register a new subscriber on one or more channels.
     *
     * @param  mixed  $channel  A channel name, or a list of channel names —
     *   whatever {@see \Ayimdomnic\Laragraph\Support\Subscription::subscribe()} returned.
     * @param  array{query: string, variables: array, operationName: ?string, schemaName: string}  $record
     * @return string  The generated subscriber id.
     */
    public function register(mixed $channel, array $record): string
    {
        $subscriberId = (string) Str::uuid();
        $ttl          = config('laragraph.subscriptions.ttl');
        $ttl          = $ttl !== null ? (int) $ttl : null;

        foreach ($this->normalizeChannels($channel) as $ch) {
            $this->store->store($ch, $subscriberId, $record, $ttl);
        }

        return $subscriberId;
    }

    /**
     * Re-execute every subscriber's original query on a channel with
     * $payload as the root value, and push each result to that subscriber's
     * private channel.
     *
     * @return int  The number of subscribers notified.
     */
    public function broadcast(string $channel, mixed $payload = null): int
    {
        $count = 0;

        foreach ($this->store->subscribers($channel) as $subscriberId => $record) {
            $context = (object) ['subscribing' => false];

            $result = $this->laragraph->executeQuery(
                query:         $record['query'],
                context:       $context,
                variables:     $record['variables'],
                operationName: $record['operationName'],
                schemaName:    $record['schemaName'],
                rootValue:     $payload,
            );

            $debug = config('app.debug')
                ? DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE
                : DebugFlag::NONE;

            $this->dispatch($subscriberId, $result->toArray($debug));
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatch(string $subscriberId, array $payload): void
    {
        if (config('laragraph.subscriptions.driver', 'broadcast') === 'log') {
            Log::channel(config('laragraph.logging.channel'))->info('GraphQL subscription update', [
                'subscriber_id' => $subscriberId,
                'payload'       => $payload,
            ]);

            return;
        }

        event(new SubscriptionMessage(
            $subscriberId,
            $payload,
            (string) config('laragraph.subscriptions.channel_prefix', 'graphql-subscriber'),
        ));
    }

    /**
     * @return list<string>
     */
    private function normalizeChannels(mixed $channel): array
    {
        if (is_array($channel)) {
            return array_values(array_map(strval(...), $channel));
        }

        return [(string) $channel];
    }
}
