<?php

declare(strict_types=1);

namespace Tests\Feature\GraphQL;

use App\Enums\PostStatus;
use App\Models\Post;

class TypesTest extends GraphQLTestCase
{
    public function test_native_enums_serialize_parse_and_carry_descriptions(): void
    {
        Post::factory()->published()->by($this->member)->create();
        Post::factory()->by($this->member)->create();

        // Enum values serialize as their case names; enum arguments arrive as enum cases.
        $this->graphql('{ posts(status: Published) { edges { node { status } } } }', as: $this->member)
            ->assertJsonPath('data.posts.edges.*.node.status', ['Published']);

        $enum = $this->graphql('{ __type(name: "UserRole") { description enumValues { name description } } }')->json('data.__type');

        $this->assertSame('What a user may do inside their organization.', $enum['description']);
        $this->assertSame(['Admin', 'Member'], array_column($enum['enumValues'], 'name'));
    }

    public function test_date_time_and_json_scalars(): void
    {
        $post = Post::factory()->published()->by($this->member)->create(['published_at' => '2026-01-15 09:30:00']);
        $this->acme->update(['settings' => ['timezone' => 'UTC', 'features' => ['blog' => true]]]);

        $this->graphql('query ($id: ID!) { post(id: $id) { publishedAt organization { settings } } }', ['id' => $post->id])
            ->assertJsonPath('data.post.publishedAt', '2026-01-15T09:30:00+00:00')
            ->assertJsonPath('data.post.organization.settings', ['timezone' => 'UTC', 'features' => ['blog' => true]]);
    }

    public function test_interface_union_and_inline_fragments(): void
    {
        Post::factory()->published()->by($this->member)->create(['title' => 'Acme launches a rocket']);

        $results = $this->graphql('{
            search(term: "Acme") {
                __typename
                ... on Node { id }
                ... on Organization { name }
                ... on Post { title author { name } }
            }
        }', as: $this->member)->json('data.search');

        $this->assertEqualsCanonicalizing(['Organization', 'Post'], array_column($results, '__typename'));
        $this->assertSame('Acme launches a rocket', collect($results)->firstWhere('__typename', 'Post')['title']);

        $interfaces = $this->graphql('{ __type(name: "Node") { possibleTypes { name } } }')->json('data.__type.possibleTypes.*.name');
        $this->assertEqualsCanonicalizing(['User', 'Post', 'Organization'], $interfaces);
    }

    public function test_field_arguments_and_deprecated_fields(): void
    {
        $post = Post::factory()->published()->by($this->member)->create(['body' => str_repeat('word ', 100)]);

        $data = $this->graphql('query ($id: ID!) { post(id: $id) { excerpt(length: 20) summary } }', ['id' => $post->id])->json('data.post');

        $this->assertLessThanOrEqual(23, mb_strlen($data['excerpt'])); // at most 20 characters + "..."
        $this->assertStringEndsWith('...', $data['excerpt']);
        $this->assertNotNull($data['summary']);

        $fields = collect($this->graphql('{ __type(name: "Post") { fields(includeDeprecated: true) { name deprecationReason } } }')->json('data.__type.fields'));
        $this->assertSame('Use `excerpt` instead.', $fields->firstWhere('name', 'summary')['deprecationReason']);
    }

    public function test_private_fields_resolve_per_viewer(): void
    {
        $query = 'query ($id: ID!) { user(id: $id) { name email } }';

        // Members may see themselves…
        $this->graphql($query, ['id' => $this->member->id], $this->member)->assertJsonPath('data.user.email', $this->member->email);
        // …and admins may see members of their organization (email included).
        $this->graphql($query, ['id' => $this->member->id], $this->admin)->assertJsonPath('data.user.email', $this->member->email);

        // Through a relation, other members' emails stay hidden.
        $post = Post::factory()->published()->by($this->admin)->create();
        $this->graphql('query ($id: ID!) { post(id: $id) { author { name email } } }', ['id' => $post->id], $this->member)
            ->assertJsonPath('data.post.author', ['name' => 'Ada Admin', 'email' => null]);
    }

    public function test_input_types(): void
    {
        $this->graphql('mutation { createPost(input: { title: "Hello world", body: "An input type in action." }) { title status } }', as: $this->member)
            ->assertJsonPath('data.createPost', ['title' => 'Hello world', 'status' => 'Draft']);

        $this->assertSame(PostStatus::Draft, Post::sole()->status);
    }
}
