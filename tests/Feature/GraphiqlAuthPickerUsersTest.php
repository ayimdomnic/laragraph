<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Tests\Support\PickerUser;
use Ayimdomnic\Laragraph\Tests\Support\TokenablePickerUser;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class GraphiqlAuthPickerUsersTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('auth.providers.users.model', PickerUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        PickerUser::create(['name' => 'Ada Moderator', 'email' => 'moderator@example.test']);
    }

    protected function tearDown(): void
    {
        Model::clearBootedModels();

        parent::tearDown();
    }

    public function test_users_without_sanctum_are_listed_without_tokens(): void
    {
        $response = $this->getJson('/graphql/graphiql/dev-tokens');

        $response->assertOk();

        $tokens = $response->json('tokens');

        $this->assertCount(1, $tokens);
        $this->assertSame('moderator', $tokens[0]['role']);
        $this->assertSame('moderator@example.test', $tokens[0]['identifier']);
        $this->assertNull($tokens[0]['token']);
        $this->assertStringContainsString('HasApiTokens', (string) $response->json('note'));
    }

    public function test_sanctum_capable_users_receive_bearer_tokens(): void
    {
        config(['auth.providers.users.model' => TokenablePickerUser::class]);

        $response = $this->getJson('/graphql/graphiql/dev-tokens');

        $response->assertOk();

        $tokens = $response->json('tokens');

        $this->assertCount(1, $tokens);
        $this->assertSame('dev-fixture-token', $tokens[0]['token']);
        $this->assertNull($response->json('note'));
    }

    public function test_graphiql_page_shows_the_logged_in_session_user(): void
    {
        config(['session.driver' => 'array']);

        $this->actingAs(PickerUser::first(), 'web');

        $response = $this->get('/graphql/graphiql');

        $response->assertOk();
        $response->assertSee('Ada Moderator', false);
    }
}
