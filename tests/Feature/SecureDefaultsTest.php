<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class SecureDefaultsNodeQuery extends Query
{
    private static ?ObjectType $node = null;

    public static function node(): ObjectType
    {
        return self::$node ??= new ObjectType([
            'name'   => 'SecureDefaultsNode',
            'fields' => fn(): array => [
                'id'    => Type::int(),
                'child' => ['type' => self::node(), 'resolve' => fn(): array => ['id' => 1]],
            ],
        ]);
    }

    public function type(): Type
    {
        return self::node();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return ['id' => 0];
    }
}

/**
 * The package's own config defaults, with app.debug off (production).
 */
class SecureDefaultsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.debug', false);
        $app['config']->set('laragraph.graphiql.enabled', null); // the package default
        $app['config']->set('laragraph.schemas.default', ['query' => ['node' => SecureDefaultsNodeQuery::class]]);
    }

    private function nested(int $depth): string
    {
        return '{ node ' . str_repeat('{ child ', $depth - 1) . '{ id }' . str_repeat(' }', $depth - 1) . ' }';
    }

    public function test_graphiql_is_not_served_in_production_by_default(): void
    {
        $this->assertFalse(app('router')->has('laragraph.graphiql'));
        $this->assertNotSame(200, $this->get('/graphql/graphiql')->status());
    }

    public function test_introspection_is_disabled_in_production_by_default(): void
    {
        $result = $this->graphql('{ __schema { queryType { name } } }');

        $this->assertStringContainsString('introspection', strtolower($result['errors'][0]['message'] ?? ''));
    }

    public function test_introspection_can_be_explicitly_enabled(): void
    {
        config(['laragraph.security.disable_introspection' => false]);

        $this->assertSame('Query', $this->graphql('{ __schema { queryType { name } } }')['data']['__schema']['queryType']['name'] ?? null);
    }

    public function test_depth_is_limited_by_default(): void
    {
        $this->assertArrayNotHasKey('errors', $this->graphql($this->nested(10)));
        $this->assertStringContainsString('Max query depth should be 15', $this->graphql($this->nested(20))['errors'][0]['message'] ?? '');
    }

    public function test_aliases_are_limited_by_default(): void
    {
        $query = fn(int $n): string => '{ ' . implode(' ', array_map(fn(int $i): string => "a{$i}: node { id }", range(1, $n))) . ' }';

        $this->assertArrayNotHasKey('errors', $this->graphql($query(30)));
        $this->assertStringContainsString('aliases', $this->graphql($query(31))['errors'][0]['message'] ?? '');
    }
}
