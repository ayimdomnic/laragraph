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
    public function type(): Type
    {
        return Type::string();
    }

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

    // -------------------------------------------------------------------------
    // Automatic Persisted Queries (registration) and trusted-documents mode
    // -------------------------------------------------------------------------

    /**
     * @return array{version: int, sha256Hash: string}
     */
    private function apq(string $query): array
    {
        return ['version' => 1, 'sha256Hash' => hash('sha256', $query)];
    }

    public function test_not_found_uses_the_apollo_message_and_code(): void
    {
        $this->postJson('/graphql', ['extensions' => ['persistedQuery' => $this->apq('{ unknown }')]])
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'PersistedQueryNotFound')
            ->assertJsonPath('errors.0.extensions.code', 'PERSISTED_QUERY_NOT_FOUND');
    }

    public function test_apq_registers_a_query_sent_with_its_hash(): void
    {
        config(['laragraph.persisted_queries.store' => 'cache']);
        $query = '{ pqPing }';

        $this->postJson('/graphql', ['extensions' => ['persistedQuery' => $this->apq($query)]])
            ->assertJsonPath('errors.0.extensions.code', 'PERSISTED_QUERY_NOT_FOUND');

        $this->postJson('/graphql', ['query' => $query, 'extensions' => ['persistedQuery' => $this->apq($query)]])
            ->assertJsonPath('data.pqPing', 'pq-pong');

        $this->postJson('/graphql', ['extensions' => ['persistedQuery' => $this->apq($query)]])
            ->assertJsonPath('data.pqPing', 'pq-pong');
    }

    public function test_apq_works_over_get_with_a_json_extensions_parameter(): void
    {
        config(['laragraph.persisted_queries.store' => 'cache']);
        $query      = '{ pqPing }';
        $extensions = urlencode((string) json_encode(['persistedQuery' => $this->apq($query)]));

        $this->getJson('/graphql?query=' . urlencode($query) . "&extensions={$extensions}")
            ->assertJsonPath('data.pqPing', 'pq-pong');

        $this->getJson("/graphql?extensions={$extensions}")
            ->assertJsonPath('data.pqPing', 'pq-pong');
    }

    public function test_apq_rejects_a_hash_that_does_not_match_the_query(): void
    {
        $this->postJson('/graphql', [
            'query'      => '{ pqPing }',
            'extensions' => ['persistedQuery' => $this->apq('{ somethingElse }')],
        ])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.extensions.code', 'PERSISTED_QUERY_HASH_MISMATCH');
    }

    public function test_apq_registration_can_be_disabled(): void
    {
        config(['laragraph.persisted_queries.store' => 'cache', 'laragraph.persisted_queries.apq' => false]);
        $query = '{ pqPing }';

        $this->postJson('/graphql', ['query' => $query, 'extensions' => ['persistedQuery' => $this->apq($query)]])
            ->assertJsonPath('data.pqPing', 'pq-pong');

        $this->assertFalse(app(PersistedQueryStoreInterface::class)->has(hash('sha256', $query)));
    }

    public function test_only_mode_executes_known_documents(): void
    {
        $query = '{ pqPing }';
        config([
            'laragraph.persisted_queries.only' => true,
            'laragraph.persisted_queries.map'  => [hash('sha256', $query) => $query],
        ]);

        $this->postJson('/graphql', ['query' => $query])->assertJsonPath('data.pqPing', 'pq-pong');
    }

    public function test_only_mode_rejects_unknown_documents_even_with_a_hash(): void
    {
        config(['laragraph.persisted_queries.only' => true]);
        $query = '{ pqPing }';

        $this->postJson('/graphql', ['query' => $query, 'extensions' => ['persistedQuery' => $this->apq($query)]])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.extensions.code', 'PERSISTED_QUERY_REQUIRED');

        $this->assertFalse(app(PersistedQueryStoreInterface::class)->has(hash('sha256', $query)));
    }

    public function test_empty_query_without_an_id_falls_through_to_normal_execution(): void
    {
        $this->postJson('/graphql', ['variables' => []])->assertJsonStructure(['errors']);
    }
}
