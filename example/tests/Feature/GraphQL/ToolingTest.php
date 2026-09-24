<?php

declare(strict_types=1);

namespace Tests\Feature\GraphQL;

use Ayimdomnic\Laragraph\Discovery\Discover;
use Illuminate\Support\Facades\Artisan;

/**
 * The artisan commands a deployment pipeline runs.
 */
class ToolingTest extends GraphQLTestCase
{
    protected function tearDown(): void
    {
        Discover::clearCache();

        parent::tearDown();
    }

    public function test_every_schema_is_valid(): void
    {
        $this->artisan('laragraph:validate')
            ->expectsOutputToContain('default')
            ->expectsOutputToContain('admin')
            ->assertSuccessful();
    }

    public function test_discovery_can_be_cached_for_production(): void
    {
        $this->artisan('laragraph:cache')->assertSuccessful();
        $this->assertTrue(Discover::isCached());

        $this->graphql('{ organization(slug: "acme") { name } }')->assertJsonPath('data.organization.name', 'Acme');

        $this->artisan('laragraph:clear')->assertSuccessful();
        $this->assertFalse(Discover::isCached());
    }

    public function test_the_schema_can_be_exported_as_sdl(): void
    {
        $this->assertSame(0, Artisan::call('laragraph:schema:export'));

        $sdl = Artisan::output();
        $this->assertStringContainsString('type Post implements Node', $sdl);
        $this->assertStringContainsString('enum UserRole', $sdl);
        $this->assertStringContainsString('union SearchResult = User | Post | Organization', $sdl);
    }
}
