<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Tests\TestCase;

class GraphiqlAuthPickerTest extends TestCase
{
    public function test_dev_tokens_route_is_registered_outside_production_by_default(): void
    {
        $this->assertTrue(app('router')->has('laragraph.graphiql.dev-tokens'));

        $this->getJson('/graphql/graphiql/dev-tokens')
            ->assertOk()
            ->assertJsonStructure(['tokens', 'note']);
    }

    public function test_picker_shows_on_the_ide_page_outside_production(): void
    {
        $response = $this->get('/graphql/graphiql');

        $response->assertOk();
        $response->assertDontSee('id="prod-banner"', false);
        $response->assertSee('authPicker: true', false);
        $response->assertSee('tokensUrl: "http', false);
    }

    public function test_production_environment_disables_the_picker_and_banners_the_ide(): void
    {
        // Routes register at boot, before the test can change config, so
        // this covers the request-time layer: the render/endpoint re-read
        // app.env on every hit. A production *boot* skipping registration
        // entirely is covered by GraphiqlAuthPickerProductionBootTest.
        config([
            'app.env'                    => 'production',
            'app.debug'                  => false,
            'laragraph.graphiql.enabled' => true, // explicit opt-in still serves the IDE
        ]);

        $this->getJson('/graphql/graphiql/dev-tokens')->assertNotFound();

        $response = $this->get('/graphql/graphiql');

        $response->assertOk();
        $response->assertSee('id="prod-banner"', false);
        $response->assertSee('authPicker: false', false);
        $response->assertDontSee('tokensUrl: "http', false);
    }

    public function test_dev_tokens_refuse_to_serve_when_production_is_set_at_runtime(): void
    {
        // Simulates a stale route registration surviving into a production
        // boot: the picker was force-enabled, yet the action must still
        // answer 404 once app.env says production.
        config([
            'laragraph.graphiql.enabled'     => true,
            'laragraph.graphiql.auth_picker' => true,
            'app.env'                        => 'production',
        ]);

        $this->getJson('/graphql/graphiql/dev-tokens')->assertNotFound();
    }

    public function test_missing_test_users_come_back_empty_with_an_explanatory_note(): void
    {
        config(['laragraph.graphiql.test_users' => ['ghost' => 'ghost@example.test']]);

        $response = $this->getJson('/graphql/graphiql/dev-tokens');

        $response->assertOk();
        $this->assertSame([], $response->json('tokens'));
        $this->assertStringContainsString('No configured test users matched', (string) $response->json('note'));
    }
}
