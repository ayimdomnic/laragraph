<?php

declare(strict_types=1);

namespace Tests\Feature\GraphQL;

use App\Models\Post;

/**
 * GraphQL over HTTP: GET queries, content negotiation, batching, persisted
 * queries and malformed requests.
 */
class HttpProtocolTest extends GraphQLTestCase
{
    public function test_queries_work_over_get_but_mutations_do_not(): void
    {
        $this->getJson('/graphql?query='.urlencode('{ organizations { pageInfo { total } } }'))
            ->assertOk()
            ->assertJsonPath('data.organizations.pageInfo.total', 1);

        $this->getJson('/graphql?query='.urlencode('mutation { logout }'))
            ->assertStatus(405)
            ->assertHeader('Allow', 'POST');
    }

    public function test_graphql_response_media_type(): void
    {
        $response = $this->postJson('/graphql', ['query' => '{ nope }'], ['Accept' => 'application/graphql-response+json']);

        $response->assertStatus(400);
        $this->assertStringStartsWith('application/graphql-response+json', (string) $response->headers->get('Content-Type'));
    }

    public function test_batched_operations(): void
    {
        Post::factory()->published()->by($this->member)->create(['title' => 'Batched']);

        $this->postJson('/graphql', [
            ['query' => '{ posts { pageInfo { total } } }'],
            ['query' => '{ organization(slug: "acme") { name } }'],
        ])
            ->assertJsonPath('0.data.posts.pageInfo.total', 1)
            ->assertJsonPath('1.data.organization.name', 'Acme');
    }

    public function test_automatic_persisted_queries(): void
    {
        $query = '{ organization(slug: "acme") { name } }';
        $hash = ['persistedQuery' => ['version' => 1, 'sha256Hash' => hash('sha256', $query)]];

        // 1. Hash only: the server does not know it yet.
        $this->postJson('/graphql', ['extensions' => $hash])->assertJsonPath('errors.0.extensions.code', 'PERSISTED_QUERY_NOT_FOUND');
        // 2. Hash + query: stored and executed.
        $this->postJson('/graphql', ['query' => $query, 'extensions' => $hash])->assertJsonPath('data.organization.name', 'Acme');
        // 3. From now on the hash alone is enough — even over GET.
        $this->getJson('/graphql?extensions='.urlencode(json_encode($hash)))->assertJsonPath('data.organization.name', 'Acme');
    }

    public function test_malformed_requests_get_400_and_unknown_schemas_404(): void
    {
        $this->postJson('/graphql', ['query' => ['not' => 'a string']])->assertStatus(400)->assertJsonPath('errors.0.extensions.code', 'BAD_REQUEST');
        $this->postJson('/graphql/nope', ['query' => '{ me { id } }'])->assertNotFound()->assertJsonPath('errors.0.extensions.code', 'SCHEMA_NOT_FOUND');
    }
}
