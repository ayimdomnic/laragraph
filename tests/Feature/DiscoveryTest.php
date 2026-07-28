<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Discovery\Discover;
use Ayimdomnic\Laragraph\Support\Mutation;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Subscription;
use Ayimdomnic\Laragraph\Support\Type;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;
use Illuminate\Support\Facades\File;

class DiscoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        File::deleteDirectory(app_path('GraphQL'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(app_path('GraphQL'));
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Discover::aliasFor
    // -------------------------------------------------------------------------

    public function test_alias_for_type_strips_type_suffix(): void
    {
        $alias = Discover::aliasFor('App\\GraphQL\\Types\\UserType', Type::class);
        $this->assertSame('User', $alias);
    }

    public function test_alias_for_query_produces_camel_case(): void
    {
        $alias = Discover::aliasFor('App\\GraphQL\\Queries\\UsersQuery', Query::class);
        $this->assertSame('users', $alias);
    }

    public function test_alias_for_mutation_produces_camel_case(): void
    {
        $alias = Discover::aliasFor('App\\GraphQL\\Mutations\\CreateUserMutation', Mutation::class);
        $this->assertSame('createUser', $alias);
    }

    public function test_alias_for_subscription_produces_camel_case(): void
    {
        $alias = Discover::aliasFor('App\\GraphQL\\Subscriptions\\UserCreatedSubscription', Subscription::class);
        $this->assertSame('userCreated', $alias);
    }

    public function test_alias_for_unknown_base_returns_camel_case(): void
    {
        // Non-Type/Query/Mutation/Subscription base → strips no suffix, returns camelCase
        $alias = Discover::aliasFor('App\\Some\\FooBar', \stdClass::class);
        $this->assertSame('fooBar', $alias);
    }

    // -------------------------------------------------------------------------
    // Discover::scan — empty directory
    // -------------------------------------------------------------------------

    public function test_scan_non_existent_directory_returns_empty(): void
    {
        $result = Discover::scan('app/GraphQL/Nonexistent', Type::class);
        $this->assertSame([], $result);
    }

    public function test_scan_empty_path_returns_empty(): void
    {
        $result = Discover::scan('', Type::class);
        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // Discover::scan — skips abstract / non-subclass files
    // -------------------------------------------------------------------------

    public function test_scan_skips_abstract_classes(): void
    {
        // Write an abstract Type file that Discover::scan should skip
        $dir = app_path('GraphQL/Types');
        File::ensureDirectoryExists($dir);
        file_put_contents("{$dir}/AbstractType.php", <<<'PHP'
<?php
namespace App\GraphQL\Types;
use Ayimdomnic\Laragraph\Support\Type;
abstract class AbstractType extends Type {
    public function fields(): array { return []; }
}
PHP);
        require_once "{$dir}/AbstractType.php";

        config(['laragraph.discover.types' => 'app/GraphQL/Types']);
        $result = Discover::scan('app/GraphQL/Types', Type::class);
        $this->assertArrayNotHasKey('Abstract', $result);
    }

    public function test_scan_skips_files_with_no_matching_class(): void
    {
        // Write a PHP file whose class name doesn't match the file name
        $dir = app_path('GraphQL/Types');
        File::ensureDirectoryExists($dir);
        file_put_contents("{$dir}/MismatchedFile.php", <<<'PHP'
<?php
namespace App\GraphQL\Types;
class SomeOtherName {}
PHP);

        $result = Discover::scan('app/GraphQL/Types', Type::class);
        // MismatchedFile → tries App\GraphQL\Types\MismatchedFile, which doesn't exist
        $this->assertSame([], array_filter($result, fn ($v) => $v === 'App\\GraphQL\\Types\\MismatchedFile'));
    }

    // -------------------------------------------------------------------------
    // Discover typed helpers
    // -------------------------------------------------------------------------

    public function test_typed_helpers_return_empty_for_nonexistent_paths(): void
    {
        $this->assertSame([], Discover::types('app/GraphQL/Nonexistent9999'));
        $this->assertSame([], Discover::queries('app/GraphQL/Nonexistent9999'));
        $this->assertSame([], Discover::mutations('app/GraphQL/Nonexistent9999'));
        $this->assertSame([], Discover::subscriptions('app/GraphQL/Nonexistent9999'));
    }

    // -------------------------------------------------------------------------
    // Discover::namespaceForDirectory — fallback path
    // -------------------------------------------------------------------------

    public function test_namespace_falls_back_for_unknown_path(): void
    {
        // A path not registered in any PSR-4 map → should fall back to app namespace
        $ns = Discover::namespaceForDirectory('/tmp/laragraph_test_unknown_path_xyz');
        // testbench's app namespace is 'App'
        $this->assertSame('App', $ns);
    }

    public function test_match_psr4_map_handles_nonexistent_directory(): void
    {
        $reflect = new \ReflectionClass(Discover::class);
        $method  = $reflect->getMethod('matchPsr4Map');
        $method->setAccessible(true);

        // A PSR-4 map with a directory that doesn't exist — realpath() returns false
        $result = $method->invoke(null, '/some/path/', [
            'Fake\\Ns\\' => ['/nonexistent_dir_xyz_123'],
        ]);
        $this->assertSame('', $result);
    }

    // -------------------------------------------------------------------------
    // Discover::namespaceForDirectory
    // -------------------------------------------------------------------------

    public function test_namespace_is_derived_from_psr4_map(): void
    {
        $path = realpath(__DIR__ . '/../../src/Support');
        $ns   = Discover::namespaceForDirectory($path);
        $this->assertSame('Ayimdomnic\\Laragraph\\Support', $ns);
    }

    // -------------------------------------------------------------------------
    // Auto-discovery via SchemaBuilder
    // -------------------------------------------------------------------------

    public function test_auto_discovered_queries_are_available(): void
    {
        // Create a query class file in app/GraphQL/Queries at runtime
        $dir = app_path('GraphQL/Queries');
        File::ensureDirectoryExists($dir);

        $content = <<<'PHP'
<?php
namespace App\GraphQL\Queries;
use Ayimdomnic\Laragraph\Support\Query;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
class DiscoveredQuery extends Query {
    public function type(): Type { return Type::string(); }
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed { return 'discovered'; }
}
PHP;
        file_put_contents("{$dir}/DiscoveredQuery.php", $content);
        require_once "{$dir}/DiscoveredQuery.php";

        // Enable discovery
        config(['laragraph.discover.queries' => 'app/GraphQL/Queries']);

        $this->app->forgetInstance('laragraph');
        $this->app->make('laragraph');

        $result = $this->graphql('{ discovered }');
        $this->assertSame('discovered', $result['data']['discovered'] ?? null);
    }
}
