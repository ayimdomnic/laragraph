<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Exceptions\AuthorizationException;
use Ayimdomnic\Laragraph\Support\Mutation;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class HelloQuery extends Query
{
    public function type(): Type { return Type::string(); }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'Hello, Laragraph!';
    }
}

class SecureQuery extends Query
{
    public function type(): Type { return Type::string(); }

    public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
    {
        return false; // always deny
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'secret';
    }
}

class AddMutation extends Mutation
{
    public function type(): Type { return Type::int(); }

    public function args(): array
    {
        return [
            'a' => ['type' => Type::nonNull(Type::int())],
            'b' => ['type' => Type::nonNull(Type::int())],
        ];
    }

    public function rules(array $args = []): array
    {
        return [
            'a' => ['required', 'integer'],
            'b' => ['required', 'integer'],
        ];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return $args['a'] + $args['b'];
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class GraphQLEndpointTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default', [
            'query'    => [
                'hello'  => HelloQuery::class,
                'secure' => SecureQuery::class,
            ],
            'mutation' => [
                'add' => AddMutation::class,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Basic execution
    // -------------------------------------------------------------------------

    public function test_graphql_endpoint_returns_200(): void
    {
        $this->postJson('/graphql', ['query' => '{ hello }'])
            ->assertStatus(200);
    }

    public function test_get_request_executes_query(): void
    {
        $this->get('/graphql?query={hello}')
            ->assertStatus(200)
            ->assertJsonPath('data.hello', 'Hello, Laragraph!');
    }

    public function test_post_json_executes_query(): void
    {
        $response = $this->postJson('/graphql', ['query' => '{ hello }']);

        $response->assertStatus(200)
                 ->assertJsonPath('data.hello', 'Hello, Laragraph!');
    }

    public function test_mutation_executes_and_returns_result(): void
    {
        $response = $this->postJson('/graphql', [
            'query'     => 'mutation Add($a: Int!, $b: Int!) { add(a: $a, b: $b) }',
            'variables' => ['a' => 3, 'b' => 4],
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.add', 7);
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    public function test_syntax_error_returns_graphql_error(): void
    {
        $response = $this->postJson('/graphql', ['query' => '{ invalidSyntax {']);

        $response->assertStatus(200)
                 ->assertJsonStructure(['errors']);
    }

    public function test_authorization_failure_returns_error(): void
    {
        $response = $this->postJson('/graphql', ['query' => '{ secure }']);

        $response->assertStatus(200)
                 ->assertJsonStructure(['errors']);

        $errors = $response->json('errors');
        $this->assertNotEmpty($errors);
    }

    // -------------------------------------------------------------------------
    // Batch queries
    // -------------------------------------------------------------------------

    public function test_batch_queries_are_processed(): void
    {
        config(['laragraph.batching.enabled' => true]);

        $response = $this->postJson('/graphql', [
            ['query' => '{ hello }'],
            ['query' => 'mutation { add(a: 1, b: 2) }'],
        ]);

        $response->assertStatus(200);
        $body = $response->json();

        $this->assertIsArray($body);
        $this->assertCount(2, $body);
        $this->assertSame('Hello, Laragraph!', $body[0]['data']['hello']);
        $this->assertSame(3, $body[1]['data']['add']);
    }

    // -------------------------------------------------------------------------
    // GraphiQL
    // -------------------------------------------------------------------------

    public function test_graphiql_endpoint_returns_html(): void
    {
        $this->get('/graphql/graphiql')
            ->assertStatus(200)
            ->assertSee('GraphiQL');
    }

    // -------------------------------------------------------------------------
    // Named schema endpoint
    // -------------------------------------------------------------------------

    public function test_named_schema_endpoint_is_routed(): void
    {
        $this->app['config']->set('laragraph.schemas.v2', [
            'query'    => ['hello' => HelloQuery::class],
            'mutation' => [],
        ]);

        $this->postJson('/graphql/v2', ['query' => '{ hello }'])
            ->assertStatus(200)
            ->assertJsonPath('data.hello', 'Hello, Laragraph!');
    }
}
