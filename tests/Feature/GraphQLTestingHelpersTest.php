<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Exceptions\GraphQLException;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\LaragraphServiceProvider;
use Ayimdomnic\Laragraph\Support\Mutation;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Testing\MakesGraphQLRequests;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use PHPUnit\Framework\ExpectationFailedException;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class HelperHelloQuery extends Query
{
    public function type(): Type
    {
        return Type::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'hello';
    }
}

class HelperValidatedMutation extends Mutation
{
    public function type(): Type
    {
        return Type::string();
    }

    public function args(): array
    {
        return ['email' => ['type' => Type::nonNull(Type::string())]];
    }

    public function rules(array $args = []): array
    {
        return ['email' => ['required', 'email']];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'ok';
    }
}

class HelperFailingQuery extends Query
{
    public function type(): Type
    {
        return Type::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        throw new GraphQLException('errors.out_of_stock', 'OUT_OF_STOCK');
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

/**
 * Deliberately extends Orchestra's own TestCase directly, not Laragraph's
 * internal tests/TestCase.php — that class has its own, differently-shaped
 * graphql() helper for the package's own tests and is never autoloaded into
 * a consumer app anyway. This is the shape a real app's test base class
 * actually takes when it adopts MakesGraphQLRequests.
 */
class GraphQLTestingHelpersTest extends OrchestraTestCase
{
    use MakesGraphQLRequests;

    protected function getPackageProviders($app): array
    {
        return [LaragraphServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Laragraph' => Laragraph::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('laragraph.default_schema', 'default');
        $app['config']->set('laragraph.schemas.default', [
            'query'    => ['hello' => HelperHelloQuery::class, 'failing' => HelperFailingQuery::class],
            'mutation' => ['validate' => HelperValidatedMutation::class],
        ]);
    }

    // -------------------------------------------------------------------------
    // MakesGraphQLRequests::graphql()
    // -------------------------------------------------------------------------

    public function test_graphql_helper_posts_the_query_and_returns_a_test_response(): void
    {
        $this->graphql('{ hello }')->assertOk()->assertGraphQLData('hello', 'hello');
    }

    public function test_graphql_helper_sends_auth_headers_from_the_overridable_hook(): void
    {
        $this->graphql('{ hello }', as: 'someone')
            ->assertJsonPath('data.hello', 'hello'); // default graphqlAuthHeaders() returns [] — request still succeeds unauthenticated
    }

    // -------------------------------------------------------------------------
    // assertGraphQLData
    // -------------------------------------------------------------------------

    public function test_assert_graphql_data(): void
    {
        $this->graphql('{ hello }')->assertGraphQLData('hello', 'hello');
    }

    // -------------------------------------------------------------------------
    // assertGraphQLErrors / assertNoGraphQLErrors
    // -------------------------------------------------------------------------

    public function test_assert_no_graphql_errors_passes_on_a_clean_response(): void
    {
        $this->graphql('{ hello }')->assertNoGraphQLErrors();
    }

    public function test_assert_graphql_errors_passes_when_errors_are_present(): void
    {
        $this->graphql('{ failing }')->assertGraphQLErrors();
    }

    public function test_assert_no_graphql_errors_fails_when_errors_are_present(): void
    {
        $this->expectException(ExpectationFailedException::class);

        $this->graphql('{ failing }')->assertNoGraphQLErrors();
    }

    // -------------------------------------------------------------------------
    // assertGraphQLErrorCode / assertGraphQLErrorCategory
    // -------------------------------------------------------------------------

    public function test_assert_graphql_error_code(): void
    {
        $this->graphql('{ failing }')->assertGraphQLErrorCode('OUT_OF_STOCK');
    }

    public function test_assert_graphql_error_category(): void
    {
        $this->graphql('{ failing }')->assertGraphQLErrorCategory('application');
    }

    // -------------------------------------------------------------------------
    // assertGraphQLValidationError
    // -------------------------------------------------------------------------

    public function test_assert_graphql_validation_error_without_a_message(): void
    {
        $this->graphql('mutation { validate(email: "not-an-email") }')
            ->assertGraphQLValidationError('email');
    }

    public function test_assert_graphql_validation_error_with_a_message(): void
    {
        $this->graphql('mutation { validate(email: "not-an-email") }')
            ->assertGraphQLValidationError('email', 'The email field must be a valid email address.');
    }

    public function test_assert_graphql_validation_error_fails_for_the_wrong_field(): void
    {
        $this->expectException(ExpectationFailedException::class);

        $this->graphql('mutation { validate(email: "not-an-email") }')
            ->assertGraphQLValidationError('name');
    }
}
