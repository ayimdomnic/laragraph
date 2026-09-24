<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Support\Mutation;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class HttpPingQuery extends Query
{
    public function type(): Type
    {
        return Type::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'pong';
    }
}

class HttpSideEffectMutation extends Mutation
{
    public static int $calls = 0;

    public function type(): Type
    {
        return Type::int();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return ++self::$calls;
    }
}

class GraphQLOverHttpTest extends TestCase
{
    private const MEDIA_TYPE = 'application/graphql-response+json';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default', [
            'query'    => ['ping' => HttpPingQuery::class],
            'mutation' => ['sideEffect' => HttpSideEffectMutation::class],
        ]);
    }

    public function test_get_executes_queries(): void
    {
        $this->getJson('/graphql?query=' . urlencode('{ ping }'))
            ->assertOk()
            ->assertJsonPath('data.ping', 'pong');
    }

    public function test_get_refuses_to_execute_mutations(): void
    {
        HttpSideEffectMutation::$calls = 0;

        $this->getJson('/graphql?query=' . urlencode('mutation { sideEffect }'))
            ->assertStatus(405)
            ->assertHeader('Allow', 'POST')
            ->assertJsonPath('errors.0.extensions.code', 'METHOD_NOT_ALLOWED');

        $this->assertSame(0, HttpSideEffectMutation::$calls);
    }

    public function test_get_refuses_a_mutation_selected_by_operation_name(): void
    {
        $query = urlencode('query Read { ping } mutation Write { sideEffect }');

        $this->getJson("/graphql?query={$query}&operationName=Write")->assertStatus(405);
        $this->getJson("/graphql?query={$query}&operationName=Read")->assertOk()->assertJsonPath('data.ping', 'pong');
    }

    public function test_post_still_executes_mutations(): void
    {
        $this->postJson('/graphql', ['query' => 'mutation { sideEffect }'])
            ->assertOk()
            ->assertJsonPath('data.sideEffect', fn(mixed $value): bool => is_int($value));
    }

    public function test_graphql_response_media_type_is_negotiated(): void
    {
        $response = $this->postJson('/graphql', ['query' => '{ ping }'], ['Accept' => self::MEDIA_TYPE]);

        $response->assertOk()->assertJsonPath('data.ping', 'pong');
        $this->assertStringStartsWith(self::MEDIA_TYPE, (string) $response->headers->get('Content-Type'));
    }

    public function test_graphql_response_media_type_uses_4xx_for_requests_that_never_execute(): void
    {
        $this->postJson('/graphql', ['query' => '{ nope }'], ['Accept' => self::MEDIA_TYPE])
            ->assertStatus(400)
            ->assertJsonStructure(['errors']);
    }

    public function test_application_json_keeps_the_legacy_200_for_validation_errors(): void
    {
        $response = $this->postJson('/graphql', ['query' => '{ nope }']);

        $response->assertOk()->assertJsonStructure(['errors']);
        $this->assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));
    }

    public function test_batched_responses_use_the_negotiated_media_type(): void
    {
        config(['laragraph.batching.enabled' => true]);

        $response = $this->postJson('/graphql', [['query' => '{ ping }'], ['query' => '{ ping }']], ['Accept' => self::MEDIA_TYPE]);

        $response->assertOk()->assertJsonPath('1.data.ping', 'pong');
        $this->assertStringStartsWith(self::MEDIA_TYPE, (string) $response->headers->get('Content-Type'));
    }

    public function test_application_graphql_request_bodies_are_executed(): void
    {
        $response = $this->call('POST', '/graphql', server: ['CONTENT_TYPE' => 'application/graphql'], content: '{ ping }');

        $response->assertOk()->assertJsonPath('data.ping', 'pong');
    }
}
