<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

// ---------------------------------------------------------------------------
// Fixture
// ---------------------------------------------------------------------------

class ExportPingQuery extends Query
{
    public function type(): Type { return Type::string(); }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'pong';
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class ExportSchemaCommandTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.schemas.default', [
            'query' => ['exportPing' => ExportPingQuery::class],
        ]);
    }

    // -------------------------------------------------------------------------
    // Stdout (no --output)
    // -------------------------------------------------------------------------

    public function test_exports_sdl_to_stdout(): void
    {
        $this->artisan('laragraph:schema:export')
             ->assertSuccessful()
             ->expectsOutputToContain('type Query');
    }

    // -------------------------------------------------------------------------
    // File output
    // -------------------------------------------------------------------------

    public function test_exports_sdl_to_file(): void
    {
        $path = sys_get_temp_dir() . '/laragraph_export_test_' . uniqid() . '.graphql';

        try {
            $this->artisan('laragraph:schema:export', ['--output' => $path])
                 ->assertSuccessful();

            $this->assertFileExists($path);
            $contents = file_get_contents($path);
            $this->assertStringContainsString('type Query', $contents);
            $this->assertStringContainsString('exportPing', $contents);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_exports_sdl_creates_missing_output_directory(): void
    {
        $dir  = sys_get_temp_dir() . '/laragraph_newdir_' . uniqid();
        $path = $dir . '/schema.graphql';

        try {
            $this->artisan('laragraph:schema:export', ['--output' => $path])
                 ->assertSuccessful();

            $this->assertFileExists($path);
            $this->assertStringContainsString('type Query', file_get_contents($path));
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Named schema
    // -------------------------------------------------------------------------

    public function test_exports_named_schema(): void
    {
        config(['laragraph.schemas.admin' => [
            'query' => ['exportPing' => ExportPingQuery::class],
        ]]);

        $this->artisan('laragraph:schema:export', ['--schema' => 'admin'])
             ->assertSuccessful()
             ->expectsOutputToContain('type Query');
    }

    // -------------------------------------------------------------------------
    // Unknown schema → failure
    // -------------------------------------------------------------------------

    public function test_fails_for_unknown_schema(): void
    {
        $this->artisan('laragraph:schema:export', ['--schema' => 'nonexistent'])
             ->assertFailed();
    }
}
