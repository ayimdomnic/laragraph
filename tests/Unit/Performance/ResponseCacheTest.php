<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Performance;

use Ayimdomnic\Laragraph\Performance\ResponseCache;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Cache;

/**
 * Full test suite for ResponseCache.
 *
 * Covers: key(), enabled(), store(), ttl(), get(), put(), forget(), isCacheable()
 */
class ResponseCacheTest extends TestCase
{
    // -------------------------------------------------------------------------
    // key()
    // -------------------------------------------------------------------------

    public function test_key_returns_string_with_prefix(): void
    {
        $key = ResponseCache::key('{ users }');
        $this->assertStringStartsWith('laragraph:response:', $key);
    }

    public function test_key_is_deterministic_for_same_inputs(): void
    {
        $k1 = ResponseCache::key('{ users }', ['page' => 1], 'GetUsers');
        $k2 = ResponseCache::key('{ users }', ['page' => 1], 'GetUsers');
        $this->assertSame($k1, $k2);
    }

    public function test_key_differs_for_different_queries(): void
    {
        $this->assertNotSame(
            ResponseCache::key('{ users }'),
            ResponseCache::key('{ posts }'),
        );
    }

    public function test_key_differs_for_different_variables(): void
    {
        $this->assertNotSame(
            ResponseCache::key('{ user }', ['id' => 1]),
            ResponseCache::key('{ user }', ['id' => 2]),
        );
    }

    public function test_key_sorts_variables_before_hashing(): void
    {
        // Order of keys in variables must not affect the resulting key
        $k1 = ResponseCache::key('q', ['b' => 2, 'a' => 1]);
        $k2 = ResponseCache::key('q', ['a' => 1, 'b' => 2]);
        $this->assertSame($k1, $k2);
    }

    public function test_key_differs_when_operation_name_changes(): void
    {
        $this->assertNotSame(
            ResponseCache::key('{ user }', [], 'OpA'),
            ResponseCache::key('{ user }', [], 'OpB'),
        );
    }

    // -------------------------------------------------------------------------
    // enabled()
    // -------------------------------------------------------------------------

    public function test_enabled_returns_false_by_default(): void
    {
        config(['laragraph.cache.response.enabled' => false]);
        $this->assertFalse(ResponseCache::enabled());
    }

    public function test_enabled_returns_true_when_configured(): void
    {
        config(['laragraph.cache.response.enabled' => true]);
        $this->assertTrue(ResponseCache::enabled());
    }

    // -------------------------------------------------------------------------
    // store()
    // -------------------------------------------------------------------------

    public function test_store_returns_configured_store_name(): void
    {
        config(['laragraph.cache.response.store' => 'redis']);
        $this->assertSame('redis', ResponseCache::store());
    }

    public function test_store_defaults_to_default(): void
    {
        config(['laragraph.cache.response.store' => 'default']);
        $this->assertSame('default', ResponseCache::store());
    }

    // -------------------------------------------------------------------------
    // ttl()
    // -------------------------------------------------------------------------

    public function test_ttl_returns_configured_seconds(): void
    {
        config(['laragraph.cache.response.ttl' => 300]);
        $this->assertSame(300, ResponseCache::ttl());
    }

    // -------------------------------------------------------------------------
    // get() / put() / forget()
    // -------------------------------------------------------------------------

    public function test_get_returns_null_on_cache_miss(): void
    {
        $key = ResponseCache::key('{ miss_' . uniqid() . ' }');
        $this->assertNull(ResponseCache::get($key));
    }

    public function test_put_and_get_roundtrip(): void
    {
        $key  = ResponseCache::key('{ roundtrip_' . uniqid() . ' }');
        $data = ['data' => ['foo' => 'bar']];

        ResponseCache::put($key, $data);

        $this->assertSame($data, ResponseCache::get($key));
    }

    public function test_forget_removes_cached_entry(): void
    {
        $key  = ResponseCache::key('{ forget_' . uniqid() . ' }');
        ResponseCache::put($key, ['data' => ['x' => 1]]);

        ResponseCache::forget($key);

        $this->assertNull(ResponseCache::get($key));
    }

    // -------------------------------------------------------------------------
    // isCacheable()
    // -------------------------------------------------------------------------

    public function test_is_cacheable_returns_true_for_plain_query(): void
    {
        $this->assertTrue(ResponseCache::isCacheable('{ users { id } }'));
    }

    public function test_is_cacheable_returns_true_for_named_query(): void
    {
        $this->assertTrue(ResponseCache::isCacheable('query GetUsers { users { id } }'));
    }

    public function test_is_cacheable_returns_false_for_mutation(): void
    {
        $this->assertFalse(ResponseCache::isCacheable('mutation CreateUser { createUser { id } }'));
    }

    public function test_is_cacheable_returns_false_for_mutation_with_whitespace(): void
    {
        $this->assertFalse(ResponseCache::isCacheable('  mutation  { createUser { id } }'));
    }

    public function test_is_cacheable_returns_false_for_subscription(): void
    {
        $this->assertFalse(ResponseCache::isCacheable('subscription { onMessage { text } }'));
    }

    public function test_is_cacheable_ignores_leading_comments_before_a_mutation(): void
    {
        $this->assertFalse(ResponseCache::isCacheable("# looks like a query\nmutation { deleteAll }"));
    }

    public function test_is_cacheable_respects_the_selected_operation(): void
    {
        $document = 'query Read { a } mutation Write { b }';

        $this->assertTrue(ResponseCache::isCacheable($document, 'Read'));
        $this->assertFalse(ResponseCache::isCacheable($document, 'Write'));
    }

    public function test_is_cacheable_returns_false_for_unparseable_documents(): void
    {
        $this->assertFalse(ResponseCache::isCacheable('query {'));
    }

    // -------------------------------------------------------------------------
    // Key partitioning, scope() and flush()
    // -------------------------------------------------------------------------

    public function test_key_differs_per_schema_and_scope(): void
    {
        $base = ResponseCache::key('{ me }', [], null, 'default', 'user:1');

        $this->assertNotSame($base, ResponseCache::key('{ me }', [], null, 'admin', 'user:1'));
        $this->assertNotSame($base, ResponseCache::key('{ me }', [], null, 'default', 'user:2'));
        $this->assertNotSame($base, ResponseCache::key('{ me }', [], null, 'default', 'guest'));
    }

    public function test_scope_is_guest_without_an_authenticated_user(): void
    {
        $this->assertSame('guest', ResponseCache::scope());
    }

    public function test_scope_identifies_the_authenticated_user(): void
    {
        $user = new User();
        $user->forceFill(['id' => 7]);
        $this->actingAs($user);

        $this->assertSame('user:7', ResponseCache::scope());
    }

    public function test_scope_can_be_global(): void
    {
        config(['laragraph.cache.response.scope' => 'global']);

        $this->assertSame('global', ResponseCache::scope());
    }

    public function test_flush_invalidates_every_existing_key(): void
    {
        $key = ResponseCache::key('{ users }');
        ResponseCache::put($key, ['data' => ['users' => []]]);
        $this->assertNotNull(ResponseCache::get($key));

        ResponseCache::flush();

        $this->assertNotSame($key, ResponseCache::key('{ users }'));
        $this->assertNull(ResponseCache::get(ResponseCache::key('{ users }')));
    }

    public function test_get_ignores_non_array_entries(): void
    {
        Cache::put('laragraph:response:corrupt', 'not-an-array');

        $this->assertNull(ResponseCache::get('laragraph:response:corrupt'));
    }
}
