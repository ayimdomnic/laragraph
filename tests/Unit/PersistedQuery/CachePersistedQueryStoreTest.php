<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\PersistedQuery;

use Ayimdomnic\Laragraph\PersistedQuery\CachePersistedQueryStore;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class CachePersistedQueryStoreTest extends TestCase
{
    private CachePersistedQueryStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        // Use the array driver so no real cache backend is needed
        $this->store = new CachePersistedQueryStore(Cache::store(), 3600);
    }

    public function test_get_returns_null_before_set(): void
    {
        $this->assertNull($this->store->get('nonexistent'));
    }

    public function test_set_and_get_roundtrip(): void
    {
        $this->store->set('q1', '{ ping }');

        $this->assertSame('{ ping }', $this->store->get('q1'));
    }

    public function test_set_overwrites_existing(): void
    {
        $this->store->set('q1', '{ original }');
        $this->store->set('q1', '{ replaced }');

        $this->assertSame('{ replaced }', $this->store->get('q1'));
    }

    public function test_set_with_explicit_ttl(): void
    {
        $this->store->set('q2', '{ me }', 60);

        $this->assertSame('{ me }', $this->store->get('q2'));
    }

    public function test_has_returns_true_after_set(): void
    {
        $this->store->set('q3', '{ users }');

        $this->assertTrue($this->store->has('q3'));
    }

    public function test_has_returns_false_for_unknown(): void
    {
        $this->assertFalse($this->store->has('unknown'));
    }

    public function test_forget_removes_entry(): void
    {
        $this->store->set('q4', '{ products }');
        $this->store->forget('q4');

        $this->assertNull($this->store->get('q4'));
        $this->assertFalse($this->store->has('q4'));
    }

    public function test_forget_is_idempotent(): void
    {
        $this->store->forget('nonexistent');
        $this->assertFalse($this->store->has('nonexistent'));
    }

    public function test_null_ttl_uses_default(): void
    {
        // null ttl falls back to the constructor default (3600)
        $this->store->set('q5', '{ orders }', null);
        $this->assertSame('{ orders }', $this->store->get('q5'));
    }
}
