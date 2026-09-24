<?php

declare(strict_types=1);

namespace Tests\Feature\GraphQL;

use App\Models\Organization;
use App\Models\Post;
use App\Models\User;

class AuthorizationTest extends GraphQLTestCase
{
    public function test_the_policy_shortcut_limits_users_to_admins(): void
    {
        $query = '{ users { edges { node { name } } } }';

        $this->graphql($query)->assertJsonPath('errors.0.extensions.category', 'authorization');
        $this->graphql($query, as: $this->member)->assertJsonPath('errors.0.extensions.category', 'authorization');

        $this->graphql($query, as: $this->admin)
            ->assertJsonPath('data.users.edges.*.node.name', ['Ada Admin', 'Mo Member']);
    }

    public function test_users_cannot_read_people_outside_their_organization(): void
    {
        $stranger = User::factory()->in(Organization::factory()->create())->create();

        $this->graphql('query ($id: ID!) { user(id: $id) { name } }', ['id' => $stranger->id], $this->admin)
            ->assertJsonPath('errors.0.extensions.category', 'authorization');
    }

    public function test_drafts_are_only_visible_to_their_author_and_admins(): void
    {
        $draft = Post::factory()->by($this->member)->create();
        $query = 'query ($id: ID!) { post(id: $id) { title } }';

        $this->graphql($query, ['id' => $draft->id])->assertJsonPath('errors.0.extensions.category', 'authorization');
        $this->graphql($query, ['id' => $draft->id], $this->member)->assertJsonPath('data.post.title', $draft->title);
        $this->graphql($query, ['id' => $draft->id], $this->admin)->assertJsonPath('data.post.title', $draft->title);

        $colleague = User::factory()->in($this->acme)->create();
        $this->graphql($query, ['id' => $draft->id], $colleague)->assertJsonPath('errors.0.extensions.category', 'authorization');

        // Lists apply the same rule.
        $this->graphql('{ posts { edges { node { id } } } }')->assertJsonPath('data.posts.edges', []);
        $this->graphql('{ me { posts { id } } }', as: $colleague)->assertJsonPath('data.me.posts', []);
    }

    public function test_only_authors_and_admins_can_publish_or_delete(): void
    {
        $post = Post::factory()->by($this->member)->create();
        $colleague = User::factory()->in($this->acme)->create();

        $this->graphql('mutation ($id: ID!) { publishPost(id: $id) { status } }', ['id' => $post->id], $colleague)
            ->assertJsonPath('errors.0.extensions.category', 'authorization');

        $this->graphql('mutation ($id: ID!) { publishPost(id: $id) { status } }', ['id' => $post->id], $this->admin)
            ->assertJsonPath('data.publishPost.status', 'Published');

        $this->graphql('mutation ($id: ID!) { deletePost(id: $id) }', ['id' => $post->id], $colleague)
            ->assertJsonPath('errors.0.extensions.category', 'authorization');

        $this->graphql('mutation ($id: ID!) { deletePost(id: $id) }', ['id' => $post->id], $this->member)
            ->assertJsonPath('data.deletePost', true);
    }

    public function test_missing_records_look_exactly_like_forbidden_ones(): void
    {
        $this->graphql('mutation { deletePost(id: 99999) }', as: $this->admin)
            ->assertJsonPath('errors.0.extensions.category', 'authorization');
    }

    public function test_profiles_can_only_be_updated_by_their_owner(): void
    {
        $this->graphql('mutation { updateProfile(name: "Hacker") { name } }')
            ->assertJsonPath('errors.0.extensions.category', 'authorization');

        $this->graphql('mutation { updateProfile(name: "Mo Renamed") { name } }', as: $this->member)
            ->assertJsonPath('data.updateProfile.name', 'Mo Renamed');

        $this->assertSame('Ada Admin', $this->admin->fresh()->name);
    }

    public function test_members_without_an_organization_cannot_post(): void
    {
        $loner = User::factory()->create(['organization_id' => null]);

        $this->graphql('mutation { createPost(input: { title: "Hello", body: "Nobody will read this." }) { id } }', as: $loner)
            ->assertJsonPath('errors.0.extensions.category', 'authorization');
    }
}
