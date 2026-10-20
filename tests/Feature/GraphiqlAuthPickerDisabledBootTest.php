<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Tests\TestCase;

/**
 * Explicitly disabling the picker at boot drops both the route and the
 * in-IDE UI, regardless of environment.
 */
class GraphiqlAuthPickerDisabledBootTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.graphiql.auth_picker', false);
    }

    public function test_dev_tokens_route_is_not_registered(): void
    {
        $this->assertFalse(app('router')->has('laragraph.graphiql.dev-tokens'));
    }

    public function test_picker_is_hidden_on_the_ide_page(): void
    {
        $this->get('/graphql/graphiql')
            ->assertOk()
            ->assertSee('authPicker: false', false)
            ->assertDontSee('tokensUrl: "http', false);
    }
}
