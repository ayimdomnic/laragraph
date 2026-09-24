<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\DataLoader\DataLoaderRegistry;
use Ayimdomnic\Laragraph\Http\GraphQLContext;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class ContextProbeQuery extends Query
{
    public static mixed $seen = null;

    public function type(): Type
    {
        return Type::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        self::$seen = $context;

        return $context instanceof GraphQLContext ? (string) $context->input('marker') : 'other';
    }
}

class ExecutionContextTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default.query', ['probe' => ContextProbeQuery::class]);
    }

    public function test_http_resolvers_receive_a_graphql_context_with_data_loaders(): void
    {
        $response = $this->postJson('/graphql', ['query' => '{ probe }', 'marker' => 'from-body']);

        $response->assertOk()->assertJsonPath('data.probe', 'from-body');

        $context = ContextProbeQuery::$seen;
        $this->assertInstanceOf(GraphQLContext::class, $context);
        $this->assertInstanceOf(DataLoaderRegistry::class, $context->dataLoaders);
        $this->assertSame($context->dataLoaders, DataLoaderRegistry::for($context));
    }

    public function test_each_execution_gets_a_fresh_registry(): void
    {
        $this->postJson('/graphql', ['query' => '{ probe }']);
        $first = ContextProbeQuery::$seen->dataLoaders;

        $this->postJson('/graphql', ['query' => '{ probe }']);

        $this->assertNotSame($first, ContextProbeQuery::$seen->dataLoaders);
    }
}
