<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Introspection;

/**
 * The same defaults with app.debug on (local development).
 */
class SecureDefaultsDebugTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.debug', true);
        $app['config']->set('laragraph.graphiql.enabled', null);
        $app['config']->set('laragraph.schemas.default', ['query' => ['node' => SecureDefaultsNodeQuery::class]]);
    }

    public function test_graphiql_and_introspection_are_available_while_debugging(): void
    {
        $this->get('/graphql/graphiql')->assertOk();

        // The full standard introspection query (GraphiQL, codegen) fits within the default limits.
        $result = $this->graphql(Introspection::getIntrospectionQuery());

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame('Query', $result['data']['__schema']['queryType']['name']);
    }
}
