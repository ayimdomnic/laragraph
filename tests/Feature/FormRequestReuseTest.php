<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Support\Mutation;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Foundation\Http\FormRequest;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class FormRequestReuseFixtureRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Deliberately false — proves the mutation below never calls this.
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'email' => ['required', 'email'],
        ];

        // Reads $this->input(), populated from the GraphQL args via merge().
        if ($this->input('plan') === 'pro') {
            $rules['seats'] = ['required', 'integer', 'min:5'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['email.email' => 'Custom FormRequest email message.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['seats' => 'seat count'];
    }
}

class FormRequestMutation extends Mutation
{
    public function type(): Type
    {
        return Type::string();
    }

    public function args(): array
    {
        return [
            'email' => ['type' => Type::nonNull(Type::string())],
            'plan'  => ['type' => Type::string()],
            'seats' => ['type' => Type::int()],
        ];
    }

    protected function formRequest(): ?string
    {
        return FormRequestReuseFixtureRequest::class;
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'ok';
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class FormRequestReuseTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default', [
            'mutation' => ['withFormRequest' => FormRequestMutation::class],
        ]);
    }

    public function test_form_request_rules_are_reused_for_validation(): void
    {
        $response = $this->postJson('/graphql', [
            'query' => 'mutation { withFormRequest(email: "not-an-email") }',
        ]);

        $errors = $response->json('errors');
        $this->assertNotEmpty($errors);
        $this->assertSame('validation', $errors[0]['extensions']['category'] ?? null);
        $this->assertArrayHasKey('email', $errors[0]['extensions']['validation']);
    }

    public function test_form_request_custom_message_is_used(): void
    {
        $response = $this->postJson('/graphql', [
            'query' => 'mutation { withFormRequest(email: "bad") }',
        ]);

        $validationErrors = $response->json('errors.0.extensions.validation');
        $this->assertStringContainsString('Custom FormRequest email message.', $validationErrors['email'][0]);
    }

    public function test_form_request_custom_attribute_is_used(): void
    {
        $response = $this->postJson('/graphql', [
            'query' => 'mutation { withFormRequest(email: "user@example.com", plan: "pro") }',
        ]);

        $validationErrors = $response->json('errors.0.extensions.validation');
        $this->assertStringContainsString('seat count', $validationErrors['seats'][0]);
    }

    public function test_form_request_conditional_rule_sees_graphql_args_as_input(): void
    {
        // No "pro" plan → the conditional `seats` rule never applies.
        $this->postJson('/graphql', [
            'query' => 'mutation { withFormRequest(email: "user@example.com") }',
        ])->assertJsonPath('data.withFormRequest', 'ok')
            ->assertJsonMissingPath('errors');
    }

    public function test_form_request_authorize_is_never_invoked(): void
    {
        // FormRequestReuseFixtureRequest::authorize() always returns false —
        // if it were called, this would fail authorization instead of validating.
        $this->postJson('/graphql', [
            'query' => 'mutation { withFormRequest(email: "user@example.com", plan: "pro", seats: 10) }',
        ])->assertJsonPath('data.withFormRequest', 'ok')
            ->assertJsonMissingPath('errors');
    }
}
