<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\Pagination\ConnectionType;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class FirstConnectionQuery extends Query
{
    public function type(): Type
    {
        return new ConnectionType('NumberConnection', Type::int());
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return null;
    }
}

class SecondConnectionQuery extends Query
{
    public function type(): Type
    {
        return new ConnectionType('WordConnection', Type::string());
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return null;
    }
}

class SharedConnectionQuery extends Query
{
    public function type(): Type
    {
        return ConnectionType::make('WordConnection', Type::string());
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return null;
    }
}

class MultipleConnectionsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default.query', [
            'numbers'   => FirstConnectionQuery::class,
            'words'     => SharedConnectionQuery::class,
            'moreWords' => SharedConnectionQuery::class,
        ]);
    }

    public function test_a_schema_with_several_connections_is_valid(): void
    {
        app(Laragraph::class)->schema()->assertValid();

        $this->addToAssertionCount(1);
    }

    public function test_introspection_works_with_several_connections(): void
    {
        $result = $this->graphql('{ __type(name: "PageInfo") { name } }');

        $this->assertSame('PageInfo', $result['data']['__type']['name'] ?? null);
    }

    public function test_make_reuses_a_connection_per_name_and_node_type(): void
    {
        $string = Type::string();

        $this->assertSame(ConnectionType::make('WordConnection', $string), ConnectionType::make('WordConnection', $string));
        $this->assertNotSame(ConnectionType::make('WordConnection', $string), ConnectionType::make('WordConnection', Type::int()));
    }

    public function test_separately_constructed_connections_share_page_info(): void
    {
        $first  = new FirstConnectionQuery();
        $second = new SecondConnectionQuery();

        $this->assertSame(
            $first->type()->getField('pageInfo')->getType()->getWrappedType(),
            $second->type()->getField('pageInfo')->getType()->getWrappedType(),
        );
    }
}
