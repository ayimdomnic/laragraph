<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;

class MalformedPingQuery extends Query
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

class MalformedRequestTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.debug', false);
        $app['config']->set('laragraph.batching.enabled', true);
        $app['config']->set('laragraph.schemas.default.query', ['ping' => MalformedPingQuery::class]);
    }

    private function rawJson(string $body): TestResponse
    {
        return $this->call('POST', '/graphql', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
    }

    private function multipart(mixed $operations, mixed $map): TestResponse
    {
        return $this->call('POST', '/graphql', ['operations' => $operations, 'map' => $map], server: ['CONTENT_TYPE' => 'multipart/form-data; boundary=x']);
    }

    /**
     * @return iterable<string, array{\Closure(self): TestResponse, string}>
     */
    public static function malformedRequests(): iterable
    {
        yield 'invalid JSON'           => [fn(self $t): TestResponse => $t->rawJson('{bad'), 'must be a JSON object'];
        yield 'scalar JSON body'       => [fn(self $t): TestResponse => $t->rawJson('5'), 'must be a JSON object'];
        yield 'query is not a string'  => [fn(self $t) => $t->postJson('/graphql', ['query' => ['a' => 1]]), '`query` must be a string'];
        yield 'operationName is array' => [fn(self $t) => $t->postJson('/graphql', ['query' => '{ ping }', 'operationName' => ['x']]), '`operationName` must be a string'];
        yield 'variables is a list'    => [fn(self $t) => $t->postJson('/graphql', ['query' => '{ ping }', 'variables' => [1, 2]]), '`variables` must be an object'];
        yield 'variables bad JSON'     => [fn(self $t) => $t->getJson('/graphql?query=' . urlencode('{ ping }') . '&variables=nope'), '`variables` must be a JSON object'];
        yield 'batch leading scalar'   => [fn(self $t) => $t->postJson('/graphql', [5, ['query' => '{ ping }']]), 'must be a JSON object'];
        yield 'batch trailing scalar'  => [fn(self $t) => $t->postJson('/graphql', [['query' => '{ ping }'], 5]), 'must be a JSON object'];
        yield 'multipart ops not JSON' => [fn(self $t): TestResponse => $t->multipart(['a'], '{}'), 'need a JSON `operations` field'];
        yield 'multipart map scalar'   => [fn(self $t): TestResponse => $t->multipart('{"query":"{ ping }"}', '5'), 'must be JSON'];
        yield 'multipart bad map path' => [fn(self $t): TestResponse => $t->multipart('{"query":"{ ping }"}', '{"0":["query"]}'), 'must point into `variables`'];
        yield 'multipart wildcard path' => [fn(self $t): TestResponse => $t->multipart('{"query":"{ ping }"}', '{"0":["variables.*"]}'), 'must point into `variables`'];
    }

    #[DataProvider('malformedRequests')]
    public function test_malformed_requests_get_a_400(\Closure $send, string $message): void
    {
        $response = $send($this);

        $response->assertStatus(400)->assertJsonPath('errors.0.extensions.code', 'BAD_REQUEST');
        $this->assertStringContainsString($message, (string) $response->json('errors.0.message'));
    }

    public function test_unknown_schemas_get_a_404(): void
    {
        $this->postJson('/graphql/nope', ['query' => '{ ping }'])
            ->assertNotFound()
            ->assertJsonPath('errors.0.extensions.code', 'SCHEMA_NOT_FOUND');
    }

    public function test_well_formed_requests_still_work(): void
    {
        $this->postJson('/graphql', ['query' => '{ ping }', 'variables' => [], 'operationName' => null])->assertJsonPath('data.ping', 'pong');
        $this->postJson('/graphql', ['query' => '{ ping }', 'variables' => ['a' => 1]])->assertJsonPath('data.ping', 'pong');
        $this->getJson('/graphql?query=' . urlencode('{ ping }') . '&variables=' . urlencode('{"a":1}'))->assertJsonPath('data.ping', 'pong');
        $this->postJson('/graphql', [['query' => '{ ping }'], ['query' => '{ ping }']])->assertJsonPath('1.data.ping', 'pong');
        $this->rawJson('')->assertOk(); // empty body: falls through to normal execution errors
    }
}
