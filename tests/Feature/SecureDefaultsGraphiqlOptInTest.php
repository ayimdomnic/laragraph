<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Tests\TestCase;

/**
 * An explicit opt-in still serves GraphiQL in production.
 */
class SecureDefaultsGraphiqlOptInTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.debug', false);
        $app['config']->set('laragraph.graphiql.enabled', true);
    }

    public function test_graphiql_can_be_explicitly_enabled(): void
    {
        $this->get('/graphql/graphiql')->assertOk();
    }
}
