<?php

declare(strict_types=1);

namespace Tests\Feature\GraphQL;

use App\Models\Post;
use Ayimdomnic\Laragraph\Relay\GlobalId;

class NodeTest extends GraphQLTestCase
{
    private const QUERY = 'query ($id: ID!) {
        node(id: $id) {
            id
            ... on User { name }
            ... on Post { title }
            ... on Organization { name }
        }
    }';

    public function test_node_refetches_a_user(): void
    {
        $globalId = GlobalId::encode('User', $this->member->id);

        $this->graphql(self::QUERY, ['id' => $globalId])
            ->assertJsonPath('data.node.name', 'Mo Member');
    }

    public function test_node_refetches_an_organization(): void
    {
        $globalId = GlobalId::encode('Organization', $this->acme->id);

        $this->graphql(self::QUERY, ['id' => $globalId])
            ->assertJsonPath('data.node.name', 'Acme');
    }

    public function test_node_refetches_a_published_post_for_anyone(): void
    {
        $post = Post::factory()->published()->by($this->member)->create(['title' => 'Hello, Relay']);

        $this->graphql(self::QUERY, ['id' => GlobalId::encode('Post', $post->id)])
            ->assertJsonPath('data.node.title', 'Hello, Relay');
    }

    public function test_node_hides_a_draft_post_from_everyone_but_its_author_and_admins(): void
    {
        $draft = Post::factory()->by($this->member)->create();
        $globalId = GlobalId::encode('Post', $draft->id);

        $this->graphql(self::QUERY, ['id' => $globalId])->assertJsonPath('data.node', null);
        $this->graphql(self::QUERY, ['id' => $globalId], $this->member)->assertJsonPath('data.node.id', (string) $draft->id);
        $this->graphql(self::QUERY, ['id' => $globalId], $this->admin)->assertJsonPath('data.node.id', (string) $draft->id);
    }

    public function test_node_returns_null_for_an_unregistered_type(): void
    {
        $this->graphql(self::QUERY, ['id' => GlobalId::encode('NotARealType', 1)])
            ->assertJsonPath('data.node', null);
    }

    public function test_node_returns_null_for_a_malformed_global_id(): void
    {
        $this->graphql(self::QUERY, ['id' => 'not-a-global-id'])
            ->assertJsonPath('data.node', null);
    }
}
