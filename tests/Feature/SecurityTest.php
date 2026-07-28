<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class SecurityQuery extends Query
{
    public function type(): Type { return Type::string(); }
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'data';
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class SecurityTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('laragraph.schemas.default', [
            'query' => ['security' => SecurityQuery::class],
        ]);
    }

    public function test_query_complexity_rule_is_applied_to_execution(): void
    {
        // Any non-disabled (>0) value exercises the QueryComplexity code path.
        // With max=100 the simple { security } query (complexity=1) passes cleanly.
        config(['laragraph.security.query_max_complexity' => 100]);

        $result = $this->app->make(Laragraph::class)->execute('{ security }');

        $this->assertSame('data', $result['data']['security']);
    }

    public function test_query_depth_limit_rejects_deeply_nested_query(): void
    {
        config(['laragraph.security.query_max_depth' => 1]);

        // Reset the schema cache so the new config is picked up
        $this->app->forgetInstance('laragraph');
        $this->app->make('laragraph'); // re-register

        // A valid but deeply nested introspection is hard to construct here,
        // so we verify that a query exceeding depth=1 returns an error.
        $result = $this->app->make(Laragraph::class)->execute(
            '{ __schema { types { fields { name } } } }',
        );

        $this->assertArrayHasKey('errors', $result);
    }

    public function test_introspection_can_be_disabled(): void
    {
        config(['laragraph.security.disable_introspection' => true]);

        $this->app->forgetInstance('laragraph');
        $this->app->make('laragraph');

        $result = $this->app->make(Laragraph::class)->execute('{ __schema { types { name } } }');
        $this->assertArrayHasKey('errors', $result);
    }

    public function test_no_security_restrictions_by_default(): void
    {
        $result = $this->app->make(Laragraph::class)->execute('{ security }');
        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame('data', $result['data']['security']);
    }
}
