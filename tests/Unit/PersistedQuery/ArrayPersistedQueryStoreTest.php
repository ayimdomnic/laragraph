<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\PersistedQuery;

use Ayimdomnic\Laragraph\PersistedQuery\ArrayPersistedQueryStore;
use Ayimdomnic\Laragraph\Tests\TestCase;

class ArrayPersistedQueryStoreTest extends TestCase
{
    private ArrayPersistedQueryStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new ArrayPersistedQueryStore([
            'id-1' => '{ ping }',
            'id-2' => '{ users { id } }',
        ]);
    }

    public function test_get_returns_registered_query(): void
    {
        $this->assertSame('{ ping }', $this->store->get('id-1'));
        $this->assertSame('{ users { id } }', $this->store->get('id-2'));
    }

    public function test_get_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->store->get('nonexistent'));
    }

    public function test_set_registers_new_query(): void
    {
        $this->store->set('id-3', '{ me { name } }');

        $this->assertSame('{ me { name } }', $this->store->get('id-3'));
    }

    public function test_set_overwrites_existing_query(): void
    {
        $this->store->set('id-1', '{ replaced }');

        $this->assertSame('{ replaced }', $this->store->get('id-1'));
    }

    public function test_set_accepts_optional_ttl_without_error(): void
    {
        // TTL is accepted but silently ignored by the array store
        $this->store->set('id-4', '{ noop }', 300);
        $this->assertSame('{ noop }', $this->store->get('id-4'));
    }

    public function test_has_returns_true_for_registered_id(): void
    {
        $this->assertTrue($this->store->has('id-1'));
    }

    public function test_has_returns_false_for_unknown_id(): void
    {
        $this->assertFalse($this->store->has('missing'));
    }

    public function test_forget_removes_query(): void
    {
        $this->store->forget('id-1');

        $this->assertNull($this->store->get('id-1'));
        $this->assertFalse($this->store->has('id-1'));
    }

    public function test_forget_is_idempotent_for_unknown_id(): void
    {
        // Should not throw
        $this->store->forget('nonexistent');
        $this->assertFalse($this->store->has('nonexistent'));
    }

    public function test_empty_constructor_creates_empty_store(): void
    {
        $empty = new ArrayPersistedQueryStore();
        $this->assertNull($empty->get('any'));
        $this->assertFalse($empty->has('any'));
    }
}
