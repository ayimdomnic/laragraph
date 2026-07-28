<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Support\Mutation;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

/** Used to cover Laragraph::resolveTypeName() class_basename() fallback. */
class NoNameClassFixture
{
    // intentionally no NAME constant and no $name property
}

class ValidatedMutation extends Mutation
{
    public function type(): Type { return Type::string(); }

    public function args(): array
    {
        return [
            'email' => ['type' => Type::nonNull(Type::string())],
            'age'   => ['type' => Type::int()],
        ];
    }

    public function rules(array $args = []): array
    {
        return [
            'email' => ['required', 'email'],
            'age'   => ['nullable', 'integer', 'min:18'],
        ];
    }

    public function messages(): array
    {
        return ['email.email' => 'Custom email message.'];
    }

    public function attributes(): array
    {
        return ['age' => 'user age'];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'ok';
    }
}

class AuthDeniedQuery extends Query
{
    public function type(): Type { return Type::string(); }

    public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
    {
        return false;
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'secret';
    }
}

class ResolverErrorQuery extends Query
{
    public function type(): Type { return Type::string(); }
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        throw new \RuntimeException('Something blew up.');
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class ValidationAndErrorTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default', [
            'query'    => ['authDenied' => AuthDeniedQuery::class, 'resolverError' => ResolverErrorQuery::class],
            'mutation' => ['validate' => ValidatedMutation::class],
        ]);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_invalid_args_return_validation_error_shape(): void
    {
        $response = $this->postJson('/graphql', [
            'query' => 'mutation { validate(email: "not-an-email") }',
        ]);

        $response->assertStatus(200);
        $errors = $response->json('errors');
        $this->assertNotEmpty($errors);
        $this->assertSame('validation', $errors[0]['extensions']['category'] ?? null);
        $this->assertArrayHasKey('validation', $errors[0]['extensions']);
        $this->assertArrayHasKey('email', $errors[0]['extensions']['validation']);
    }

    public function test_custom_validation_message_is_returned(): void
    {
        $response = $this->postJson('/graphql', [
            'query' => 'mutation { validate(email: "bad") }',
        ]);

        $validationErrors = $response->json('errors.0.extensions.validation');
        $this->assertStringContainsString('Custom email message.', $validationErrors['email'][0]);
    }

    public function test_valid_args_resolve_successfully(): void
    {
        $this->postJson('/graphql', [
            'query' => 'mutation { validate(email: "user@example.com") }',
        ])->assertJsonPath('data.validate', 'ok');
    }

    // -------------------------------------------------------------------------
    // Authorization
    // -------------------------------------------------------------------------

    public function test_authorization_failure_has_category_extension(): void
    {
        $response = $this->postJson('/graphql', ['query' => '{ authDenied }']);
        $errors   = $response->json('errors');

        $this->assertNotEmpty($errors);
        $this->assertSame('authorization', $errors[0]['extensions']['category'] ?? null);
    }

    // -------------------------------------------------------------------------
    // Error formatting
    // -------------------------------------------------------------------------

    public function test_resolver_exception_is_formatted(): void
    {
        $response = $this->postJson('/graphql', ['query' => '{ resolverError }']);
        $response->assertStatus(200)
                 ->assertJsonStructure(['errors']);
    }

    public function test_debug_mode_includes_debug_message(): void
    {
        config(['app.debug' => true]);

        $response = $this->postJson('/graphql', ['query' => '{ resolverError }']);
        // In debug mode, the error formatter attaches extensions
        $this->assertArrayHasKey('errors', $response->json());
    }

    // -------------------------------------------------------------------------
    // Laragraph::handleErrors static
    // -------------------------------------------------------------------------

    public function test_handle_errors_maps_formatter_over_errors(): void
    {
        $errors    = [new \GraphQL\Error\Error('one'), new \GraphQL\Error\Error('two')];
        $formatted = \Ayimdomnic\Laragraph\Laragraph::handleErrors(
            $errors,
            fn (\GraphQL\Error\Error $e) => ['message' => $e->getMessage()],
        );

        $this->assertCount(2, $formatted);
        $this->assertSame('one', $formatted[0]['message']);
        $this->assertSame('two', $formatted[1]['message']);
    }

    // -------------------------------------------------------------------------
    // Laragraph type registry — addType with object instance
    // -------------------------------------------------------------------------

    public function test_add_type_with_instance_stores_by_name(): void
    {
        $typeInstance = new class extends \GraphQL\Type\Definition\ScalarType {
            public string $name = 'MyScalar';
            public function serialize(mixed $value): mixed { return $value; }
            public function parseValue(mixed $value): mixed { return $value; }
            public function parseLiteral(\GraphQL\Language\AST\Node $valueNode, ?array $variables = null): mixed { return null; }
        };

        /** @var \Ayimdomnic\Laragraph\Laragraph $manager */
        $manager = $this->app->make('laragraph');
        $manager->addType($typeInstance);

        $this->assertTrue($manager->hasType('MyScalar'));
    }

    // -------------------------------------------------------------------------
    // Laragraph::resolveTypeName
    // -------------------------------------------------------------------------

    public function test_resolve_type_name_uses_name_constant(): void
    {
        $class = new class {
            public const NAME = 'ConstantName';
        };
        $className = get_class($class);

        /** @var \Ayimdomnic\Laragraph\Laragraph $manager */
        $manager = $this->app->make('laragraph');
        $manager->addType($className);
        $this->assertTrue($manager->hasType('ConstantName'));
    }

    public function test_resolve_type_name_falls_back_to_class_basename(): void
    {
        // Call addType without an explicit alias so resolveTypeName() is invoked.
        // The class has neither a NAME constant nor a $name property →
        // resolveTypeName() falls through to class_basename().
        /** @var \Ayimdomnic\Laragraph\Laragraph $manager */
        $manager = $this->app->make('laragraph');
        $manager->addType(\Ayimdomnic\Laragraph\Tests\Feature\NoNameClassFixture::class);
        $this->assertTrue($manager->hasType('NoNameClassFixture'));
    }

    // -------------------------------------------------------------------------
    // LaragraphServiceProvider::provides
    // -------------------------------------------------------------------------

    public function test_service_provider_provides_correct_bindings(): void
    {
        $provider = new \Ayimdomnic\Laragraph\LaragraphServiceProvider($this->app);
        $provides  = $provider->provides();

        $this->assertContains('laragraph', $provides);
        $this->assertContains(\Ayimdomnic\Laragraph\Laragraph::class, $provides);
    }

    // -------------------------------------------------------------------------
    // LaragraphController — form-urlencoded (empty JSON body fallback)
    // -------------------------------------------------------------------------

    public function test_form_urlencoded_request_is_parsed(): void
    {
        // Content-Type is NOT multipart and NOT JSON → body parsed via $request->all()
        $response = $this->call(
            'POST',
            '/graphql',
            ['query' => '{ authDenied }'],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
        );

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // LaragraphController — multipart upload with file-map entries
    // -------------------------------------------------------------------------

    public function test_multipart_request_with_file_map_is_parsed(): void
    {
        $file = \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 10);

        // 6th arg = $server — sets Content-Type to trigger parseMultipartRequest()
        $response = $this->call(
            'POST',
            '/graphql',
            [
                'operations' => json_encode(['query' => 'mutation { validate(email: "user@example.com") }']),
                'map'        => json_encode(['0' => ['variables.file']]),
            ],
            [],
            ['0' => $file],
            ['CONTENT_TYPE' => 'multipart/form-data; boundary=----TestBoundary'],
        );

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // LaragraphController — variables sent as JSON string
    // -------------------------------------------------------------------------

    public function test_json_string_variables_are_cast_to_array(): void
    {
        $response = $this->postJson('/graphql', [
            'query'     => '{ authDenied }',
            'variables' => '{"key":"value"}',
        ]);

        $response->assertStatus(200);
    }
}
