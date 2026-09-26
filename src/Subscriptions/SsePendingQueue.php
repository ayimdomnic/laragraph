<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Subscriptions;

use Ayimdomnic\Laragraph\Controllers\LaragraphController;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * A per-subscriber queue of pending SSE payloads, backed by a Laravel cache
 * driver — deliberately its own small interface, not folded into
 * {@see SubscriberStoreInterface}, so existing custom store implementations
 * aren't forced to support it.
 *
 * {@see SubscriptionManager::dispatch()} pushes a payload here when
 * `subscriptions.driver` is `'sse'`; the `stream()` endpoint
 * ({@see LaragraphController::stream()})
 * polls and pops from it for the subscriber whose connection is open.
 *
 * Same read-modify-write locking as {@see CacheSubscriberStore}: fine for
 * the moderate concurrency a single subscriber's own queue sees, falling
 * back to an unlocked update on a cache store without lock support.
 */
final readonly class SsePendingQueue
{
    private const PREFIX = 'laragraph_sse_pending:';

    /** @param int $lockWait Seconds to wait for another process's update of the same subscriber's queue. */
    public function __construct(
        private CacheRepository $cache,
        private int $ttl = 60,
        private int $lockWait = 5,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function push(string $subscriberId, array $payload): void
    {
        $this->update($subscriberId, function (array $queue) use ($payload): array {
            $queue[] = $payload;

            return $queue;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pop(string $subscriberId): ?array
    {
        $popped = null;

        $this->update($subscriberId, function (array $queue) use (&$popped): array {
            $popped = array_shift($queue);

            return $queue;
        });

        return $popped;
    }

    /**
     * @param \Closure(list<array<string, mixed>>): list<array<string, mixed>> $update
     */
    private function update(string $subscriberId, \Closure $update): void
    {
        $key = self::PREFIX . $subscriberId;

        $write = function () use ($key, $update): void {
            /** @var list<array<string, mixed>> $queue */
            $queue = $this->cache->get($key, []);
            $queue = $update($queue);

            if ($queue === []) {
                $this->cache->forget($key);
            } else {
                $this->cache->put($key, $queue, $this->ttl);
            }
        };

        $store = $this->cache->getStore();

        if (!$store instanceof LockProvider) {
            $write();

            return;
        }

        $store->lock('laragraph_sse_lock:' . $subscriberId, 10)->block($this->lockWait, $write);
    }
}
