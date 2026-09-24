<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class MainSchemaQuery extends Query
{
    public function type(): Type
    {
        return Type::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'main';
    }
}

class CustomDefaultSchemaRouteTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.default_schema', 'main');
        $app['config']->set('laragraph.schemas', ['main' => ['query' => ['value' => MainSchemaQuery::class]]]);
    }

    public function test_the_root_endpoint_serves_the_configured_default_schema(): void
    {
        $this->postJson('/graphql', ['query' => '{ value }'])->assertJsonPath('data.value', 'main');
    }
}
