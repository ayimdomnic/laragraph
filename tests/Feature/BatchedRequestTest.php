<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Support\Mutation;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

// ---------------------------------------------------------------------------
// Minimal fixtures used only by this test suite
// ---------------------------------------------------------------------------

class BatchGreetQuery extends Query
{
    public function type(): Type { return Type::string(); }

    public function args(): array
    {
        return ['name' => ['type' => Type::string()]];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'Hello, ' . ($args['name'] ?? 'World') . '!';
    }
}

class BatchSumMutation extends Mutation
{
    public function type(): Type { return Type::int(); }

    public function args(): array
    {
        return [
            'x' => ['type' => Type::nonNull(Type::int())],
            'y' => ['type' => Type::nonNull(Type::int())],
        ];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return $args['x'] + $args['y'];
    }
}

// ---------------------------------------------------------------------------

class BatchedRequestTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default', [
            'query'    => ['greet' => BatchGreetQuery::class],
            'mutation' => ['sum'   => BatchSumMutation::class],
        ]);
    }

    // -------------------------------------------------------------------------
    // Batching disabled (default)
    // -------------------------------------------------------------------------

    public function test_batch_disabled_returns_400(): void
    {
        config(['laragraph.batching.enabled' => false]);

        $response = $this->postJson('/graphql', [
            ['query' => '{ greet }'],
            ['query' => '{ greet }'],
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString(
            'disabled',
            strtolower($response->json('errors.0.message')),
        );
    }

    public function test_single_operation_not_affected_by_batching_flag(): void
    {
        // Single operations are never routed through BatchProcessor
        config(['laragraph.batching.enabled' => false]);

        $response = $this->postJson('/graphql', ['query' => '{ greet }']);
        $response->assertStatus(200);
        $this->assertSame('Hello, World!', $response->json('data.greet'));
    }

    // -------------------------------------------------------------------------
    // Batch enabled
    // -------------------------------------------------------------------------

    public function test_batch_single_item_returns_array(): void
    {
        config(['laragraph.batching.enabled' => true]);

        $response = $this->postJson('/graphql', [
            ['query' => '{ greet }'],
        ]);

        $response->assertStatus(200);
        $body = $response->json();
        $this->assertIsArray($body);
        $this->assertCount(1, $body);
        $this->assertSame('Hello, World!', $body[0]['data']['greet']);
    }

    public function test_batch_multiple_operations_returned_in_order(): void
    {
        config(['laragraph.batching.enabled' => true]);

        $response = $this->postJson('/graphql', [
            ['query' => '{ greet }'],
            ['query' => 'mutation { sum(x: 3, y: 4) }'],
        ]);

        $response->assertStatus(200);
        $body = $response->json();
        $this->assertCount(2, $body);
        $this->assertSame('Hello, World!', $body[0]['data']['greet']);
        $this->assertSame(7, $body[1]['data']['sum']);
    }

    public function test_batch_with_variables(): void
    {
        config(['laragraph.batching.enabled' => true]);

        $response = $this->postJson('/graphql', [
            ['query' => 'query Greet($n: String) { greet(name: $n) }', 'variables' => ['n' => 'Alice']],
            ['query' => 'query Greet($n: String) { greet(name: $n) }', 'variables' => ['n' => 'Bob']],
        ]);

        $response->assertStatus(200);
        $body = $response->json();
        $this->assertSame('Hello, Alice!', $body[0]['data']['greet']);
        $this->assertSame('Hello, Bob!', $body[1]['data']['greet']);
    }

    public function test_batch_with_operation_names(): void
    {
        config(['laragraph.batching.enabled' => true]);

        $response = $this->postJson('/graphql', [
            [
                'query'         => 'query GreetAlice { greet(name: "Alice") }',
                'operationName' => 'GreetAlice',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertSame('Hello, Alice!', $response->json('0.data.greet'));
    }

    // -------------------------------------------------------------------------
    // Max operations limit
    // -------------------------------------------------------------------------

    public function test_batch_exceeding_max_operations_returns_400(): void
    {
        config(['laragraph.batching.enabled' => true, 'laragraph.batching.max_operations' => 2]);

        $response = $this->postJson('/graphql', [
            ['query' => '{ greet }'],
            ['query' => '{ greet }'],
            ['query' => '{ greet }'],
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString(
            'exceeds',
            strtolower($response->json('errors.0.message')),
        );
    }

    public function test_batch_exactly_at_max_operations_is_accepted(): void
    {
        config(['laragraph.batching.enabled' => true, 'laragraph.batching.max_operations' => 2]);

        $response = $this->postJson('/graphql', [
            ['query' => '{ greet }'],
            ['query' => '{ greet }'],
        ]);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json());
    }

    // -------------------------------------------------------------------------
    // Error isolation
    // -------------------------------------------------------------------------

    public function test_error_in_one_operation_does_not_fail_others(): void
    {
        config(['laragraph.batching.enabled' => true]);

        $response = $this->postJson('/graphql', [
            ['query' => '{ greet }'],
            ['query' => '{ nonExistentField }'],
            ['query' => 'mutation { sum(x: 1, y: 2) }'],
        ]);

        $response->assertStatus(200);
        $body = $response->json();

        $this->assertCount(3, $body);
        // First op succeeds
        $this->assertSame('Hello, World!', $body[0]['data']['greet']);
        // Second op has a GraphQL error but still returns a result envelope
        $this->assertArrayHasKey('errors', $body[1]);
        // Third op succeeds
        $this->assertSame(3, $body[2]['data']['sum']);
    }
}
