<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Console;

use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Support\Facades\File;

// ---------------------------------------------------------------------------
// Fake model for scaffolding — avoids any DB/migration dependency
// ---------------------------------------------------------------------------

class FakeScaffoldModel extends \Illuminate\Database\Eloquent\Model
{
    protected $table    = 'fake_scaffold_models';
    protected $fillable = ['name', 'email', 'age', 'is_admin', 'score', 'meta', 'birth_date', 'created_at'];
    protected $casts    = [
        'age'        => 'integer',
        'is_admin'   => 'boolean',
        'score'      => 'float',
        'meta'       => 'json',
        'birth_date' => 'date',
        'created_at' => 'datetime',
    ];
}

/** Model whose constructor throws — exercises the catch(\Throwable) branch in extractFields(). */
class ThrowingScaffoldModel extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'throwing_models';

    public function __construct(array $attributes = [])
    {
        throw new \RuntimeException('Intentional constructor failure for test coverage.');
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class ScaffoldCommandTest extends TestCase
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
    // Basic scaffold (Type + 2 Queries)
    // -------------------------------------------------------------------------

    public function test_scaffold_generates_type_and_queries(): void
    {
        $this->artisan('laragraph:scaffold', [
            'model' => FakeScaffoldModel::class,
        ])->assertSuccessful();

        $shortName = 'FakeScaffoldModel';
        $this->assertFileExists(app_path("GraphQL/Types/{$shortName}Type.php"));
        $this->assertFileExists(app_path("GraphQL/Queries/{$shortName}Query.php"));
        $this->assertFileExists(app_path("GraphQL/Queries/{$shortName}sQuery.php"));
    }

    // -------------------------------------------------------------------------
    // --with-crud also generates Mutations
    // -------------------------------------------------------------------------

    public function test_scaffold_with_crud_generates_mutations(): void
    {
        $this->artisan('laragraph:scaffold', [
            'model'      => FakeScaffoldModel::class,
            '--with-crud' => true,
        ])->assertSuccessful();

        $shortName = 'FakeScaffoldModel';
        $this->assertFileExists(app_path("GraphQL/Mutations/Create{$shortName}Mutation.php"));
        $this->assertFileExists(app_path("GraphQL/Mutations/Update{$shortName}Mutation.php"));
        $this->assertFileExists(app_path("GraphQL/Mutations/Delete{$shortName}Mutation.php"));
    }

    // -------------------------------------------------------------------------
    // --register appends a hint comment
    // -------------------------------------------------------------------------

    public function test_scaffold_with_register_succeeds_when_no_config(): void
    {
        // config/laragraph.php doesn't exist in the testbench app → warns but succeeds
        $this->artisan('laragraph:scaffold', [
            'model'      => FakeScaffoldModel::class,
            '--register' => true,
        ])->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // Invalid model name → FAILURE
    // -------------------------------------------------------------------------

    public function test_scaffold_invalid_model_returns_failure(): void
    {
        $this->artisan('laragraph:scaffold', [
            'model' => 'NonExistentModelXyzAbc',
        ])->assertFailed();
    }

    // -------------------------------------------------------------------------
    // --all: no app/Models directory → FAILURE
    // -------------------------------------------------------------------------

    public function test_scaffold_all_without_models_dir_fails(): void
    {
        // In testbench there is no app/Models by default
        if (is_dir(app_path('Models'))) {
            $this->markTestSkipped('app/Models exists — cannot test missing directory branch.');
        }

        $this->artisan('laragraph:scaffold', [
            'model' => 'placeholder',
            '--all'  => true,
        ])->assertFailed();
    }

    // -------------------------------------------------------------------------
    // --all: models directory exists but is empty → SUCCESS (warn + 0 models)
    // -------------------------------------------------------------------------

    public function test_scaffold_all_with_empty_models_dir_succeeds(): void
    {
        File::ensureDirectoryExists(app_path('Models'));

        $this->artisan('laragraph:scaffold', [
            'model' => 'placeholder',
            '--all'  => true,
        ])->assertSuccessful();

        File::deleteDirectory(app_path('Models'));
    }

    // -------------------------------------------------------------------------
    // --all: models directory has PHP files → scaffold is called for each
    // -------------------------------------------------------------------------

    public function test_scaffold_all_with_models_calls_scaffold_for_each(): void
    {
        File::ensureDirectoryExists(app_path('Models'));

        $widgetFile = app_path('Models/Widget.php');
        file_put_contents($widgetFile, <<<'PHP'
<?php
namespace App\Models;
class Widget extends \Illuminate\Database\Eloquent\Model {
    protected $table    = 'widgets';
    protected $fillable = ['title'];
}
PHP);
        require_once $widgetFile;

        $this->artisan('laragraph:scaffold', [
            'model' => 'placeholder',
            '--all'  => true,
        ])->assertSuccessful();

        $this->assertFileExists(app_path('GraphQL/Types/WidgetType.php'));

        File::delete($widgetFile);
        File::deleteDirectory(app_path('Models'));
    }

    // -------------------------------------------------------------------------
    // Skips existing files (no --force)
    // -------------------------------------------------------------------------

    public function test_scaffold_skips_existing_files(): void
    {
        $typePath = app_path('GraphQL/Types/FakeScaffoldModelType.php');
        File::ensureDirectoryExists(dirname($typePath));
        file_put_contents($typePath, '<?php // existing');

        $this->artisan('laragraph:scaffold', [
            'model' => FakeScaffoldModel::class,
        ])->assertSuccessful();

        // File content should NOT have changed (it was skipped)
        $this->assertStringContainsString('// existing', file_get_contents($typePath));
    }

    // -------------------------------------------------------------------------
    // resolveModel — FQCN passed directly
    // -------------------------------------------------------------------------

    public function test_scaffold_accepts_fully_qualified_class_name(): void
    {
        $this->artisan('laragraph:scaffold', [
            'model' => FakeScaffoldModel::class,
        ])->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // castToGraphQLType — exercised via extractFields
    // -------------------------------------------------------------------------

    public function test_generated_type_contains_all_cast_mappings(): void
    {
        $this->artisan('laragraph:scaffold', [
            'model' => FakeScaffoldModel::class,
        ])->assertSuccessful();

        $content = file_get_contents(app_path('GraphQL/Types/FakeScaffoldModelType.php'));

        $this->assertStringContainsString('GType::int()', $content);       // age => integer
        $this->assertStringContainsString('GType::boolean()', $content);   // is_admin => boolean
        $this->assertStringContainsString('GType::float()', $content);     // score => float
        $this->assertStringContainsString("type('JSON')", $content);       // meta => json
        $this->assertStringContainsString("type('Date')", $content);       // birth_date => date
        $this->assertStringContainsString("type('DateTime')", $content);   // created_at => datetime
    }

    // -------------------------------------------------------------------------
    // extractFields — model constructor throws (catch \Throwable branch)
    // -------------------------------------------------------------------------

    public function test_scaffold_falls_back_to_id_only_when_model_throws(): void
    {
        $this->artisan('laragraph:scaffold', [
            'model' => ThrowingScaffoldModel::class,
        ])->assertSuccessful();

        // Should generate a type with at least the 'id' field despite the throw
        $content = file_get_contents(app_path('GraphQL/Types/ThrowingScaffoldModelType.php'));
        $this->assertStringContainsString('id', $content);
    }

    // -------------------------------------------------------------------------
    // registerInConfig — config file exists (lines 248-252)
    // -------------------------------------------------------------------------

    public function test_scaffold_with_register_and_existing_config_writes_hint(): void
    {
        // Create a stub config file in the testbench app's config dir
        $configPath = config_path('laragraph.php');
        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($configPath));
        file_put_contents($configPath, "<?php\nreturn [];\n");

        $this->artisan('laragraph:scaffold', [
            'model'      => FakeScaffoldModel::class,
            '--register' => true,
        ])->assertSuccessful();

        @unlink($configPath);
    }

    // -------------------------------------------------------------------------
    // render() — missing stub throws RuntimeException (line 264)
    // -------------------------------------------------------------------------

    public function test_render_throws_for_missing_stub(): void
    {
        $command = new \Ayimdomnic\Laragraph\Console\ScaffoldCommand();
        $reflect = new \ReflectionClass($command);
        $method  = $reflect->getMethod('render');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/stub \[nonexistent.stub\] not found/');
        $method->invoke($command, 'nonexistent', []);
    }
}
