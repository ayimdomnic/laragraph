<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Subscriptions;

use Ayimdomnic\Laragraph\Subscriptions\CacheSubscriberStore;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class CacheSubscriberStoreTest extends TestCase
{
    private CacheSubscriberStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new CacheSubscriberStore(Cache::store(), 3600);
    }

    public function test_subscribers_returns_empty_array_for_unknown_channel(): void
    {
        $this->assertSame([], $this->store->subscribers('unknown'));
    }

    public function test_store_and_subscribers_roundtrip(): void
    {
        $record = ['query' => '{ ping }', 'variables' => [], 'operationName' => null, 'schemaName' => 'default'];

        $this->store->store('pings', 'sub-1', $record);

        $subscribers = $this->store->subscribers('pings');

        $this->assertCount(1, $subscribers);
        $this->assertSame($record, $subscribers['sub-1']);
    }

    public function test_multiple_subscribers_on_the_same_channel(): void
    {
        $this->store->store('pings', 'sub-1', ['query' => 'a', 'variables' => [], 'operationName' => null, 'schemaName' => 'default']);
        $this->store->store('pings', 'sub-2', ['query' => 'b', 'variables' => [], 'operationName' => null, 'schemaName' => 'default']);

        $subscribers = $this->store->subscribers('pings');

        $this->assertCount(2, $subscribers);
        $this->assertArrayHasKey('sub-1', $subscribers);
        $this->assertArrayHasKey('sub-2', $subscribers);
    }

    public function test_storing_the_same_subscriber_twice_does_not_duplicate_the_index(): void
    {
        $record = ['query' => '{ ping }', 'variables' => [], 'operationName' => null, 'schemaName' => 'default'];

        $this->store->store('pings', 'sub-1', $record);
        $this->store->store('pings', 'sub-1', $record);

        $this->assertCount(1, $this->store->subscribers('pings'));
    }

    public function test_forget_removes_a_subscriber(): void
    {
        $this->store->store('pings', 'sub-1', ['query' => 'a', 'variables' => [], 'operationName' => null, 'schemaName' => 'default']);
        $this->store->store('pings', 'sub-2', ['query' => 'b', 'variables' => [], 'operationName' => null, 'schemaName' => 'default']);

        $this->store->forget('pings', 'sub-1');

        $subscribers = $this->store->subscribers('pings');
        $this->assertCount(1, $subscribers);
        $this->assertArrayHasKey('sub-2', $subscribers);
    }

    public function test_subscribers_prunes_stale_index_entries(): void
    {
        $record = ['query' => 'a', 'variables' => [], 'operationName' => null, 'schemaName' => 'default'];
        $this->store->store('pings', 'sub-1', $record, 1);

        // Simulate the subscriber record itself having expired (e.g. TTL
        // elapsed) while it's still listed in the channel index.
        Cache::store()->forget('laragraph_sub_record:sub-1');

        $subscribers = $this->store->subscribers('pings');

        $this->assertSame([], $subscribers);
    }
}
