<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Tests\TestCase;

/**
 * A production *boot* never registers the dev-tokens route at all, even
 * when the IDE itself is explicitly opted in.
 */
class GraphiqlAuthPickerProductionBootTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.env', 'production');
        $app['config']->set('laragraph.graphiql.enabled', true);
    }

    public function test_dev_tokens_route_is_not_registered(): void
    {
        $this->assertFalse(app('router')->has('laragraph.graphiql.dev-tokens'));
    }
}
