<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\PersistedQuery;

use Ayimdomnic\Laragraph\Exceptions\RequestException;
use Ayimdomnic\Laragraph\PersistedQuery\ArrayPersistedQueryStore;
use Ayimdomnic\Laragraph\PersistedQuery\PersistedQueryResolver;
use Ayimdomnic\Laragraph\Tests\TestCase;

class PersistedQueryResolverTest extends TestCase
{
    public function test_resolves_a_query_by_id(): void
    {
        $store    = new ArrayPersistedQueryStore(['abc' => '{ hello }']);
        $resolver = new PersistedQueryResolver($store);

        $result = $resolver->resolve('', ['queryId' => 'abc']);

        $this->assertSame('{ hello }', $result);
    }

    public function test_throws_when_id_is_unknown(): void
    {
        $resolver = new PersistedQueryResolver(new ArrayPersistedQueryStore());

        $this->expectException(RequestException::class);
        $resolver->resolve('', ['queryId' => 'missing']);
    }

    public function test_empty_query_and_no_id_returns_empty_string(): void
    {
        $resolver = new PersistedQueryResolver(new ArrayPersistedQueryStore());

        $this->assertSame('', $resolver->resolve('', []));
    }

    public function test_apq_registration_stores_the_query_under_its_hash(): void
    {
        config(['laragraph.persisted_queries.apq' => true]);

        $store    = new ArrayPersistedQueryStore();
        $resolver = new PersistedQueryResolver($store);
        $query    = '{ hello }';
        $hash     = hash('sha256', $query);

        $result = $resolver->resolve($query, ['extensions' => ['persistedQuery' => ['sha256Hash' => $hash]]]);

        $this->assertSame($query, $result);
        $this->assertTrue($store->has($hash));
    }

    public function test_apq_registration_rejects_a_mismatched_hash(): void
    {
        $resolver = new PersistedQueryResolver(new ArrayPersistedQueryStore());

        $this->expectException(RequestException::class);
        $resolver->resolve('{ hello }', ['extensions' => ['persistedQuery' => ['sha256Hash' => 'not-the-real-hash']]]);
    }

    public function test_apq_disabled_does_not_store_the_query(): void
    {
        config(['laragraph.persisted_queries.apq' => false]);

        $store    = new ArrayPersistedQueryStore();
        $resolver = new PersistedQueryResolver($store);
        $query    = '{ hello }';
        $hash     = hash('sha256', $query);

        $resolver->resolve($query, ['extensions' => ['persistedQuery' => ['sha256Hash' => $hash]]]);

        $this->assertFalse($store->has($hash));
    }

    public function test_only_mode_rejects_a_query_not_already_stored(): void
    {
        config(['laragraph.persisted_queries.only' => true]);

        $resolver = new PersistedQueryResolver(new ArrayPersistedQueryStore());

        $this->expectException(RequestException::class);
        $resolver->resolve('{ hello }', []);
    }

    public function test_only_mode_accepts_a_query_already_stored_under_its_hash(): void
    {
        config(['laragraph.persisted_queries.only' => true]);

        $query = '{ hello }';
        $store = new ArrayPersistedQueryStore([hash('sha256', $query) => $query]);

        $result = (new PersistedQueryResolver($store))->resolve($query, []);

        $this->assertSame($query, $result);
    }

    public function test_a_plain_query_with_no_persisted_query_extension_passes_through(): void
    {
        $resolver = new PersistedQueryResolver(new ArrayPersistedQueryStore());

        $this->assertSame('{ hello }', $resolver->resolve('{ hello }', []));
    }
}
