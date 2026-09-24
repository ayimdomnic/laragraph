<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Tests\TestCase;

class GraphiqlRouteConfigTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // `->middleware(null)` returns the middleware *array*, so chaining
        // `->name()` onto it used to fatal while registering routes.
        $app['config']->set('laragraph.graphiql.middleware', null);
    }

    public function test_graphiql_route_registers_when_middleware_config_is_null(): void
    {
        $this->get('/graphql/graphiql')->assertOk();
    }
}
