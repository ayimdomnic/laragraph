<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Subscriptions;

use Ayimdomnic\Laragraph\Controllers\LaragraphController;
use Ayimdomnic\Laragraph\Http\GraphQLContext;
use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\Support\Subscription;
use GraphQL\Error\DebugFlag;
use GraphQL\Executor\ExecutionResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Registers subscribers and re-executes their queries when application code
 * reports a subscription event.
 *
 * @see Subscription  for how a subscriber's
 *   channel is resolved during registration
 * @see LaragraphController  for the HTTP
 *   flow that calls register()
 *
 * @phpstan-import-type SubscriberRecord from SubscriberStoreInterface
 */
final readonly class SubscriptionManager
{
    public function __construct(
        private SubscriberStoreInterface $store,
        private Laragraph $laragraph,
        private SubscriberSandbox $sandbox,
    ) {}

    /**
     * Register a new subscriber on one or more channels.
     *
     * The identity of the user making the current request is stored with the
     * subscriber, so later updates are resolved — and authorized — as them.
     *
     * @param  mixed  $channel  A channel name, or a list of channel names —
     *   whatever {@see Subscription::subscribe()} returned.
     * @param SubscriberRecord $record
     * @return string  The generated subscriber id.
     */
    public function register(mixed $channel, array $record): string
    {
        $subscriberId       = (string) Str::uuid();
        $record['auth']   ??= $this->sandbox->currentIdentity();
        $record['channels'] = $this->normalizeChannels($channel);
        $ttl                = config('laragraph.subscriptions.ttl');
        $ttl                = $ttl !== null ? (int) $ttl : null;

        foreach ($record['channels'] as $ch) {
            $this->store->store($ch, $subscriberId, $record, $ttl);
        }

        return $subscriberId;
    }

    /**
     * Remove a subscriber from every channel it subscribed to.
     *
     * @return bool False when the subscriber is unknown (or already gone).
     */
    public function unsubscribe(string $subscriberId): bool
    {
        $record = $this->find($subscriberId);

        if ($record === null) {
            return false;
        }

        foreach ($record['channels'] ?? [] as $channel) {
            $this->store->forget($channel, $subscriberId);
        }

        return true;
    }

    /**
     * Whether the current user created the subscription: the same user (id
     * and class), or — for subscriptions created by a guest — any guest,
     * for whom the unguessable subscriber id acts as the credential.
     */
    public function ownedByCurrentUser(string $subscriberId): bool
    {
        $owner = $this->find($subscriberId)['auth'] ?? null;

        if ($owner === null) {
            return false;
        }

        $current = $this->sandbox->currentIdentity();

        return (string) $owner['id'] === (string) $current['id']
            && ($owner['type'] ?? null) === ($current['type'] ?? null);
    }

    /**
     * @return SubscriberRecord|null
     */
    private function find(string $subscriberId): ?array
    {
        return $this->store instanceof FindsSubscribers ? $this->store->find($subscriberId) : null;
    }

    /**
     * Re-execute every subscriber's original query on a channel with
     * $payload as the root value, and push each result to that subscriber's
     * private channel.
     *
     * Each query runs authenticated as its subscriber — never as whoever
     * called broadcast() — see {@see SubscriberSandbox}. Subscribers whose
     * user no longer exists are removed from the channel.
     *
     * @return int  The number of subscribers notified.
     */
    public function broadcast(string $channel, mixed $payload = null): int
    {
        $count = 0;

        foreach ($this->store->subscribers($channel) as $subscriberId => $record) {
            $result = $this->sandbox->run($record['auth'] ?? null, fn(GraphQLContext $context): ExecutionResult => $this->laragraph->executeQuery(
                query: $record['query'],
                context: $context,
                variables: $record['variables'],
                operationName: $record['operationName'],
                schemaName: $record['schemaName'],
                rootValue: $payload,
            ));

            if ($result === null) {
                $this->store->forget($channel, $subscriberId);

                continue;
            }

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
