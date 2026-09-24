<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Subscriptions;

use Ayimdomnic\Laragraph\Subscriptions\CacheSubscriberStore;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Store;

/** A cache store without lock support. */
class LocklessStore implements Store
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function get($key): mixed
    {
        return $this->items[$key] ?? null;
    }

    public function many(array $keys): array
    {
        return array_map($this->get(...), array_combine($keys, $keys));
    }

    public function put($key, $value, $seconds): bool
    {
        $this->items[$key] = $value;

        return true;
    }

    public function putMany(array $values, $seconds): bool
    {
        $this->items = [...$this->items, ...$values];

        return true;
    }

    public function increment($key, $value = 1): int|bool
    {
        return $this->items[$key] = ($this->items[$key] ?? 0) + $value;
    }

    public function decrement($key, $value = 1): int|bool
    {
        return $this->increment($key, -$value);
    }

    public function forever($key, $value): bool
    {
        return $this->put($key, $value, 0);
    }

    public function forget($key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function flush(): bool
    {
        $this->items = [];

        return true;
    }

    public function getPrefix(): string
    {
        return '';
    }

    public function touch($key, $seconds): bool
    {
        return true;
    }
}

class CacheSubscriberStoreLockingTest extends TestCase
{
    /**
     * @return array{query: string, variables: array<string, mixed>, operationName: null, schemaName: string}
     */
    private function record(): array
    {
        return ['query' => 'subscription { x }', 'variables' => [], 'operationName' => null, 'schemaName' => 'default'];
    }

    public function test_channel_updates_wait_for_a_concurrent_writer(): void
    {
        $store = new ArrayStore();
        $cache = new Repository($store);

        // Another process is mid-update on the same channel.
        $store->lock('laragraph_sub_lock:news', 10)->get();

        $this->expectException(LockTimeoutException::class);

        try {
            (new CacheSubscriberStore($cache, 3600, lockWait: 0))->store('news', 'sub-1', $this->record());
        } finally {
            // Nothing was written to the channel list while it was locked.
            $this->assertNull($cache->get('laragraph_sub_channel:news'));
        }
    }

    public function test_updates_proceed_once_the_lock_is_free(): void
    {
        $cache = new Repository(new ArrayStore());
        $store = new CacheSubscriberStore($cache, 3600, lockWait: 0);

        $store->store('news', 'sub-1', $this->record());
        $store->store('news', 'sub-2', $this->record());
        $store->forget('news', 'sub-1');

        $this->assertSame(['sub-2'], array_keys($store->subscribers('news')));
    }

    public function test_stores_without_lock_support_still_work(): void
    {
        $store = new CacheSubscriberStore(new Repository(new LocklessStore()));

        $store->store('news', 'sub-1', $this->record());
        $store->store('news', 'sub-2', $this->record());
        $store->forget('news', 'sub-2');

        $this->assertSame(['sub-1'], array_keys($store->subscribers('news')));
    }
}
