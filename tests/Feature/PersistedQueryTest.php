<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\PersistedQuery\PersistedQueryStoreInterface;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class PqPingQuery extends Query
{
    public function type(): Type { return Type::string(); }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'pq-pong';
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class PersistedQueryTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.persisted_queries.enabled', true);
        $app['config']->set('laragraph.persisted_queries.store', 'array');
        $app['config']->set('laragraph.persisted_queries.map', [
            'ping-id' => '{ pqPing }',
        ]);
        $app['config']->set('laragraph.schemas.default', [
            'query' => ['pqPing' => PqPingQuery::class],
        ]);
        $app['config']->set('cache.default', 'array');
    }

    // -------------------------------------------------------------------------
    // Array store lookups
    // -------------------------------------------------------------------------

    public function test_query_resolved_by_query_id_field(): void
    {
        $response = $this->postJson('/graphql', ['queryId' => 'ping-id']);

        $this->assertSame('pq-pong', $response->json('data.pqPing'));
    }

    public function test_unknown_query_id_returns_persisted_query_not_found(): void
    {
        $response = $this->postJson('/graphql', ['queryId' => 'no-such-id']);

        $this->assertNotEmpty($response->json('errors'));
        $this->assertStringContainsString('PersistedQueryNotFound', $response->json('errors.0.message'));
    }

    public function test_apollo_apq_sha256_hash_lookup(): void
    {
        $response = $this->postJson('/graphql', [
            'extensions' => [
                'persistedQuery' => [
                    'version'    => 1,
                    'sha256Hash' => 'ping-id',   // using the same map key
                ],
            ],
        ]);

        $this->assertSame('pq-pong', $response->json('data.pqPing'));
    }

    // -------------------------------------------------------------------------
    // Cache store lookups
    // -------------------------------------------------------------------------

    public function test_cache_store_lookup_via_interface(): void
    {
        // Switch to the cache store and seed it manually via the bound interface
        config(['laragraph.persisted_queries.store' => 'cache']);

        /** @var PersistedQueryStoreInterface $store */
        $store = app(PersistedQueryStoreInterface::class);
        $store->set('cache-ping', '{ pqPing }');

        $response = $this->postJson('/graphql', ['queryId' => 'cache-ping']);

        $this->assertSame('pq-pong', $response->json('data.pqPing'));
    }

    // -------------------------------------------------------------------------
    // Disabled — feature must be transparent
    // -------------------------------------------------------------------------

    public function test_persisted_query_bypassed_when_disabled(): void
    {
        config(['laragraph.persisted_queries.enabled' => false]);

        // Even though queryId is sent, it must be ignored and the empty query
        // should result in a validation error rather than a lookup
        $response = $this->postJson('/graphql', ['queryId' => 'ping-id']);

        // No data.pqPing — the empty query string causes a validation error
        $this->assertNull($response->json('data.pqPing'));
    }
}
