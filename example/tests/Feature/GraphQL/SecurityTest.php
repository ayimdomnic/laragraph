<?php

declare(strict_types=1);

namespace Tests\Feature\GraphQL;

use App\Models\Post;

class SecurityTest extends GraphQLTestCase
{
    public function test_the_admin_schema_is_guarded_by_its_route_middleware(): void
    {
        Post::factory()->published()->by($this->member)->create();
        Post::factory()->by($this->member)->create();
        $query = ['query' => '{ stats { members publishedPosts draftPosts } }'];

        $this->postJson('/graphql/admin', $query)->assertUnauthorized();
        $this->postJson('/graphql/admin', $query, $this->headersFor($this->member))->assertForbidden();

        $this->postJson('/graphql/admin', $query, $this->headersFor($this->admin))
            ->assertJsonPath('data.stats', ['members' => 2, 'publishedPosts' => 1, 'draftPosts' => 1]);

        // POST only: other methods are refused rather than slipping past the middleware.
        $this->getJson('/graphql/admin?query='.urlencode('{ stats { members } }'))->assertStatus(405);
    }

    public function test_admin_only_types_are_invisible_on_the_public_schema(): void
    {
        $this->postJson('/graphql/admin', ['query' => '{ stats { members } }'], $this->headersFor($this->admin))->assertOk();

        $this->graphql('{ __type(name: "AdminStats") { name } }')->assertJsonPath('data.__type', null);
    }

    public function test_introspection_and_graphiql_are_off_in_production(): void
    {
        config(['app.debug' => false]);

        $this->graphql('{ __schema { queryType { name } } }')
            ->assertJsonPath('errors.0.message', fn (string $message): bool => str_contains(strtolower($message), 'introspection'));
    }

    public function test_query_depth_is_limited(): void
    {
        $deep = '{ me '.str_repeat('{ organization { owner ', 8).'{ id }'.str_repeat(' } }', 8).' }';

        $this->graphql($deep, as: $this->member)
            ->assertJsonPath('errors.0.message', fn (string $message): bool => str_starts_with($message, 'Max query depth should be 15'));
    }

    public function test_the_custom_max_root_fields_rule(): void
    {
        $query = '{ '.implode(' ', array_map(fn (int $i): string => "o{$i}: organizations { pageInfo { total } }", range(1, 11))).' }';

        $this->graphql($query)
            ->assertJsonPath('errors.0.message', 'An operation may select at most 10 root fields; this one selects 11.');
    }
}
