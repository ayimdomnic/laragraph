<?php

declare(strict_types=1);

namespace Tests\Feature\GraphQL;

class LocalizationTest extends GraphQLTestCase
{
    private const LOGIN = 'mutation ($email: String!, $password: String!) {
        login(email: $email, password: $password) { token }
    }';

    public function test_error_messages_are_translated_per_accept_language_header(): void
    {
        $this->postJson('/graphql', [
            'query' => self::LOGIN,
            'variables' => ['email' => $this->member->email, 'password' => 'wrong'],
        ], ['Accept-Language' => 'fr'])
            ->assertJsonPath('errors.0.message', 'Les identifiants fournis sont incorrects.')
            ->assertJsonPath('errors.0.extensions.code', 'INVALID_CREDENTIALS');
    }

    public function test_default_locale_is_english_without_the_header(): void
    {
        $this->postJson('/graphql', [
            'query' => self::LOGIN,
            'variables' => ['email' => $this->member->email, 'password' => 'wrong'],
        ])->assertJsonPath('errors.0.message', 'The provided credentials are incorrect.');
    }

    public function test_an_unsupported_language_falls_back_to_english(): void
    {
        $this->postJson('/graphql', [
            'query' => self::LOGIN,
            'variables' => ['email' => $this->member->email, 'password' => 'wrong'],
        ], ['Accept-Language' => 'de'])
            ->assertJsonPath('errors.0.message', 'The provided credentials are incorrect.');
    }

    public function test_the_app_locale_is_restored_after_the_request(): void
    {
        $original = app()->getLocale();

        $this->postJson('/graphql', [
            'query' => self::LOGIN,
            'variables' => ['email' => $this->member->email, 'password' => 'wrong'],
        ], ['Accept-Language' => 'fr']);

        $this->assertSame($original, app()->getLocale());
    }

    public function test_a_vendor_lang_override_translates_a_package_message(): void
    {
        // example/lang/vendor/laragraph/fr/errors.php overrides authorization.field.
        $this->postJson('/graphql', ['query' => 'mutation { logout }'], ['Accept-Language' => 'fr'])
            ->assertJsonPath('errors.0.message', "Vous n'êtes pas autorisé à accéder à LogoutMutation.")
            ->assertJsonPath('errors.0.extensions.category', 'authorization');
    }
}
