<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Tests\TestCase;

class SkippedSchemaRoutesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.graphiql.enabled', true);
        $app['config']->set('laragraph.schemas.graphiql', ['query' => []]);
        $app['config']->set('laragraph.schemas.not a valid segment', ['query' => []]);
    }

    public function test_unroutable_schema_names_get_no_route_of_their_own(): void
    {
        $router = app('router');

        $this->assertFalse($router->has('laragraph.query.graphiql'));
        $this->assertFalse($router->has('laragraph.query.not a valid segment'));
        $this->get('/graphql/graphiql')->assertOk(); // still the IDE
    }
}
