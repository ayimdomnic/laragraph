<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Subscriptions;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * A subscriber store backed by a Laravel cache driver.
 *
 * Each channel is indexed by a list of subscriber ids stored under one cache
 * key, with each subscriber's record stored under its own key. The index is
 * read-modify-write (not atomic), which is fine for the moderate concurrency
 * a single GraphQL API's subscription registrations typically see; a
 * high-throughput deployment may want a Redis-backed set implementation
 * instead — {@see SubscriberStoreInterface} is the extension point for that.
 */
final class CacheSubscriberStore implements SubscriberStoreInterface
{
    private const CHANNEL_PREFIX = 'laragraph_sub_channel:';

    private const RECORD_PREFIX = 'laragraph_sub_record:';

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly ?int $ttl = 3600,
    ) {}

    public function store(string $channel, string $subscriberId, array $record, ?int $ttl = null): void
    {
        $ttl ??= $this->ttl;

        $this->cache->put($this->recordKey($subscriberId), $record, $ttl);

        $ids = $this->cache->get($this->channelKey($channel), []);
        if (!in_array($subscriberId, $ids, true)) {
            $ids[] = $subscriberId;
        }
        $this->cache->put($this->channelKey($channel), $ids, $ttl);
    }

    public function subscribers(string $channel): array
    {
        $ids = $this->cache->get($this->channelKey($channel), []);

        $subscribers = [];
        $stale       = [];

        foreach ($ids as $id) {
            $record = $this->cache->get($this->recordKey($id));

            if ($record === null) {
                $stale[] = $id;
                continue;
            }

            $subscribers[$id] = $record;
        }

        if (!empty($stale)) {
            $this->cache->put($this->channelKey($channel), array_values(array_diff($ids, $stale)), $this->ttl);
        }

        return $subscribers;
    }

    public function forget(string $channel, string $subscriberId): void
    {
        $this->cache->forget($this->recordKey($subscriberId));

        $ids = $this->cache->get($this->channelKey($channel), []);
        $this->cache->put($this->channelKey($channel), array_values(array_diff($ids, [$subscriberId])), $this->ttl);
    }

    private function channelKey(string $channel): string
    {
        return self::CHANNEL_PREFIX . $channel;
    }

    private function recordKey(string $id): string
    {
        return self::RECORD_PREFIX . $id;
    }
}
