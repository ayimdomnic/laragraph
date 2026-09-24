<?php

declare(strict_types=1);

namespace Tests\Feature\GraphQL;

class AuthenticationTest extends GraphQLTestCase
{
    private const LOGIN = 'mutation ($email: String!, $password: String!) {
        login(email: $email, password: $password) { token expiresIn user { name role } }
    }';

    public function test_register_validates_input_and_returns_a_token(): void
    {
        $invalid = $this->graphql('mutation {
            register(name: "", email: "nope", password: "short", password_confirmation: "other", organizationSlug: "missing") { token }
        }')->json('errors.0.extensions');

        $this->assertSame('validation', $invalid['category']);
        $this->assertSame(['name', 'email', 'password', 'organizationSlug'], array_keys($invalid['validation']));
        $this->assertSame(['There is no organization with that slug.'], $invalid['validation']['organizationSlug']);

        $this->graphql('mutation {
            register(name: "Neo", email: "neo@example.com", password: "password1", password_confirmation: "password1", organizationSlug: "acme") {
                token user { name role organization { slug } }
            }
        }')
            ->assertJsonPath('data.register.user', ['name' => 'Neo', 'role' => 'Member', 'organization' => ['slug' => 'acme']])
            ->assertJsonPath('data.register.token', fn (string $token): bool => $token !== '');
    }

    public function test_login_returns_a_token_that_authenticates_later_requests(): void
    {
        $token = $this->graphql(self::LOGIN, ['email' => $this->member->email, 'password' => 'password'])
            ->assertJsonPath('data.login.user.name', 'Mo Member')
            ->json('data.login.token');

        $this->postJson('/graphql', ['query' => '{ me { name } }'], ['Authorization' => "Bearer {$token}"])
            ->assertJsonPath('data.me.name', 'Mo Member');

        $this->postJson('/graphql', ['query' => '{ me { name } }'])->assertJsonPath('data.me', null);
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $this->graphql(self::LOGIN, ['email' => $this->member->email, 'password' => 'wrong'])
            ->assertJsonPath('errors.0.message', 'The provided credentials are incorrect.');
    }

    public function test_login_is_throttled_by_field_middleware(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->graphql(self::LOGIN, ['email' => $this->member->email, 'password' => 'wrong']);
        }

        $message = $this->graphql(self::LOGIN, ['email' => $this->member->email, 'password' => 'password'])->json('errors.0.message');

        $this->assertStringContainsString('Too many requests for field [login]', $message);
    }

    public function test_logout_requires_authentication_and_invalidates_the_token(): void
    {
        $this->graphql('mutation { logout }')->assertJsonPath('errors.0.extensions.category', 'authorization');

        $headers = $this->headersFor($this->member);

        $this->postJson('/graphql', ['query' => 'mutation { logout }'], $headers)->assertJsonPath('data.logout', true);
        $this->postJson('/graphql', ['query' => '{ me { name } }'], $headers)->assertJsonPath('data.me', null);
    }

    public function test_the_deprecated_current_user_field_still_works_and_is_flagged(): void
    {
        $this->graphql('{ currentUser { name } }', as: $this->member)->assertJsonPath('data.currentUser.name', 'Mo Member');

        $field = collect($this->graphql('{ __type(name: "Query") { fields(includeDeprecated: true) { name isDeprecated deprecationReason } } }')
            ->json('data.__type.fields'))->firstWhere('name', 'currentUser');

        $this->assertSame(['name' => 'currentUser', 'isDeprecated' => true, 'deprecationReason' => 'Use `me` instead.'], $field);
    }
}
