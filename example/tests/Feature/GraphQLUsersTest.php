<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraphQLUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--force' => true]);

        config(['laragraph.schemas.default' => [
            'query' => [
                'users' => \App\GraphQL\Queries\UsersQuery::class,
                'user' => \App\GraphQL\Queries\UserQuery::class,
            ],
            'mutation' => [
                'createUser' => \App\GraphQL\Mutations\CreateUserMutation::class,
                'updateUser' => \App\GraphQL\Mutations\UpdateUserMutation::class,
            ],
        ]]);
    }

    public function test_users_query_returns_existing_users(): void
    {
        User::factory()->count(3)->create();

        $response = $this->postJson('/graphql', ['query' => '{ users(limit: 2) { id name email } }']);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.users')
            ->assertJsonStructure(['data' => ['users' => [['id', 'name', 'email']]]]);
    }

    public function test_user_query_returns_a_single_user(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/graphql', [
            'query' => 'query User($id: ID!) { user(id: $id) { id name email } }',
            'variables' => ['id' => $user->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.id', (string) $user->id)
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_create_user_mutation_creates_and_returns_user(): void
    {
        $response = $this->postJson('/graphql', [
            'query' => 'mutation CreateUser($name: String!, $email: String!, $password: String!) { createUser(name: $name, email: $email, password: $password) { id name email } }',
            'variables' => [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => 'password123',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.createUser.name', 'Jane Doe')
            ->assertJsonPath('data.createUser.email', 'jane@example.com');

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_update_user_mutation_updates_existing_user(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this->postJson('/graphql', [
            'query' => 'mutation UpdateUser($id: ID!, $name: String!) { updateUser(id: $id, name: $name) { id name email } }',
            'variables' => ['id' => $user->id, 'name' => 'New Name'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.updateUser.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }
}
