<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Console;

use Ayimdomnic\Laragraph\Discovery\Discover;
use Ayimdomnic\Laragraph\LaragraphServiceProvider;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class ValidPingQuery extends Query
{
    public function type(): Type
    {
        return Type::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'pong';
    }
}

class BrokenTypeQuery extends Query
{
    public function type(): Type
    {
        // An object type with no fields is invalid per the GraphQL spec.
        return new ObjectType(['name' => 'Empty', 'fields' => []]);
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return null;
    }
}

class CacheAndValidateCommandsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default', ['query' => ['ping' => ValidPingQuery::class]]);
        $app['config']->set('laragraph.schemas.broken', ['query' => ['broken' => BrokenTypeQuery::class]]);
    }

    protected function tearDown(): void
    {
        Discover::clearCache();
        File::deleteDirectory(app_path('GraphQL'));

        parent::tearDown();
    }

    private function writeDiscoverableQuery(string $class, string $returns): void
    {
        $dir = app_path('GraphQL/Queries');
        File::ensureDirectoryExists($dir);
        file_put_contents("{$dir}/{$class}.php", <<<PHP
<?php
namespace App\\GraphQL\\Queries;
class {$class} extends \\Ayimdomnic\\Laragraph\\Support\\Query {
    public function type(): \\GraphQL\\Type\\Definition\\Type { return \\GraphQL\\Type\\Definition\\Type::string(); }
    public function resolve(mixed \$root, array \$args, mixed \$context, \\GraphQL\\Type\\Definition\\ResolveInfo \$info): mixed { return '{$returns}'; }
}
PHP);
        require_once "{$dir}/{$class}.php";
    }

    public function test_cache_writes_a_manifest_that_discovery_reads_instead_of_scanning(): void
    {
        $this->writeDiscoverableQuery('CachedHelloQuery', 'hi');

        $this->artisan('laragraph:cache')
            ->expectsOutputToContain('Laragraph discovery cached successfully (1 classes)')
            ->assertSuccessful();

        $this->assertTrue(Discover::isCached());
        $this->assertSame(
            ['cachedHello' => 'App\\GraphQL\\Queries\\CachedHelloQuery'],
            (require Discover::manifestPath())['scan:' . Query::class . ':app/GraphQL/Queries'],
        );

        // Classes added after caching are not picked up until the cache is rebuilt.
        $this->writeDiscoverableQuery('LateQuery', 'late');
        $this->assertArrayNotHasKey('late', Discover::queries('app/GraphQL/Queries'));

        $this->artisan('laragraph:clear')
            ->expectsOutputToContain('Laragraph discovery cache cleared successfully')
            ->assertSuccessful();

        $this->assertFalse(Discover::isCached());
        $this->assertArrayHasKey('late', Discover::queries('app/GraphQL/Queries'));
    }

    public function test_manifest_is_read_back_from_disk_on_a_fresh_process(): void
    {
        $this->writeDiscoverableQuery('DiskQuery', 'disk');
        $this->artisan('laragraph:cache')->assertSuccessful();

        // Simulate a new worker: drop the in-memory copy, keep the file.
        (new \ReflectionProperty(Discover::class, 'manifest'))->setValue(null, null);
        File::deleteDirectory(app_path('GraphQL'));

        $this->assertSame(['disk' => 'App\\GraphQL\\Queries\\DiskQuery'], Discover::queries('app/GraphQL/Queries'));
    }

    public function test_cache_skips_disabled_categories_and_creates_the_cache_directory(): void
    {
        config(['laragraph.discover' => ['types' => '', 'queries' => ['path' => 'app/GraphQL/Queries']]]);
        File::deleteDirectory(dirname(Discover::manifestPath()));

        $manifest = Discover::cache();

        $this->assertSame(['scan:' . Query::class . ':app/GraphQL/Queries'], array_keys($manifest));
        $this->assertFileExists(Discover::manifestPath());
    }

    public function test_commands_are_registered_with_optimize(): void
    {
        if (!method_exists(ServiceProvider::class, 'optimizes')) {
            $this->markTestSkipped('optimize integration requires Laravel 11.27+.');
        }

        $this->assertSame('laragraph:cache', ServiceProvider::$optimizeCommands['laragraph'] ?? null);
        $this->assertSame('laragraph:clear', ServiceProvider::$optimizeClearCommands['laragraph'] ?? null);
    }

    public function test_validate_reports_each_schema(): void
    {
        $this->artisan('laragraph:validate')
            ->expectsOutputToContain('default')
            ->expectsOutputToContain('INVALID')
            ->assertFailed();
    }

    public function test_validate_can_target_specific_schemas(): void
    {
        $this->artisan('laragraph:validate', ['--schema' => ['default']])->assertSuccessful();
        $this->artisan('laragraph:validate', ['--schema' => ['missing']])->assertFailed();
    }

    public function test_about_lists_the_laragraph_section(): void
    {
        config(['laragraph.security.disable_introspection' => true]);

        Artisan::call('about', ['--only' => 'laragraph', '--json' => true]);
        $about = json_decode(Artisan::output(), true)['laragraph'] ?? [];

        $this->assertNotSame('unknown', $about['version'] ?? 'unknown');
        $this->assertSame('/graphql', $about['endpoint'] ?? null);
        $this->assertStringContainsString('default', $about['schemas'] ?? '');
        $this->assertStringContainsString('NOT CACHED', $about['discovery'] ?? '');
        $this->assertSame('OFF', $about['introspection'] ?? null);
    }

    public function test_about_version_falls_back_when_the_package_is_not_installed(): void
    {
        $provider = new LaragraphServiceProvider($this->app);
        $version  = new \ReflectionMethod($provider, 'installedVersion');

        $this->assertSame('unknown', $version->invoke($provider, ['vendor/not-installed']));
    }
}
