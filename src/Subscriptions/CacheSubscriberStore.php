<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Subscriptions;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
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
 *
 * @phpstan-import-type SubscriberRecord from SubscriberStoreInterface
 */
final readonly class CacheSubscriberStore implements FindsSubscribers, SubscriberStoreInterface
{
    private const CHANNEL_PREFIX = 'laragraph_sub_channel:';

    private const RECORD_PREFIX = 'laragraph_sub_record:';

    private const LOCK_PREFIX = 'laragraph_sub_lock:';

    /**
     * @param int $lockWait Seconds to wait for another process's update of the same channel.
     */
    public function __construct(
        private CacheRepository $cache,
        private ?int $ttl = 3600,
        private int $lockWait = 5,
    ) {}

    /**
     * @param SubscriberRecord $record
     */
    public function store(string $channel, string $subscriberId, array $record, ?int $ttl = null): void
    {
        $ttl ??= $this->ttl;

        $this->cache->put($this->recordKey($subscriberId), $record, $ttl);

        $this->updateChannel($channel, static function (array $ids) use ($subscriberId): array {
            if (!in_array($subscriberId, $ids, true)) {
                $ids[] = $subscriberId;
            }

            return $ids;
        }, $ttl);
    }

    /**
     * @return array<string, SubscriberRecord>
     */
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

        if ($stale !== []) {
            $this->updateChannel($channel, static fn(array $current): array => array_values(array_diff($current, $stale)));
        }

        return $subscribers;
    }

    public function forget(string $channel, string $subscriberId): void
    {
        $this->cache->forget($this->recordKey($subscriberId));

        $this->updateChannel($channel, static fn(array $ids): array => array_values(array_diff($ids, [$subscriberId])));
    }

    public function find(string $subscriberId): ?array
    {
        /** @var SubscriberRecord|null $record Written by store() with exactly this shape. */
        $record = $this->cache->get($this->recordKey($subscriberId));

        return is_array($record) ? $record : null;
    }

    /**
     * Read-modify-write a channel's subscriber list under a lock, so that
     * concurrent subscriptions to the same channel cannot overwrite each
     * other. Stores without lock support fall back to an unlocked update.
     *
     * @param \Closure(list<string>): list<string> $update
     *
     * @throws LockTimeoutException When another process holds the channel for longer than $lockWait.
     */
    private function updateChannel(string $channel, \Closure $update, ?int $ttl = null): void
    {
        $write = function () use ($channel, $update, $ttl): void {
            /** @var list<string> $ids */
            $ids = $this->cache->get($this->channelKey($channel), []);

            $this->cache->put($this->channelKey($channel), $update($ids), $ttl ?? $this->ttl);
        };

        $store = $this->cache->getStore();

        if (!$store instanceof LockProvider) {
            $write();

            return;
        }

        $store->lock(self::LOCK_PREFIX . $channel, 10)->block($this->lockWait, $write);
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
