<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Console;

use App\GraphQL\Mutations\CreateFakeScaffoldModelMutation;
use App\GraphQL\Mutations\DeleteFakeScaffoldModelMutation;
use App\GraphQL\Mutations\UpdateFakeScaffoldModelMutation;
use App\GraphQL\Queries\FakeScaffoldModelQuery;
use App\GraphQL\Queries\FakeScaffoldModelsQuery;
use App\GraphQL\Types\FakeScaffoldModelType;
use Ayimdomnic\Laragraph\Console\ScaffoldCommand;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

// ---------------------------------------------------------------------------
// Fake model for scaffolding — avoids any DB/migration dependency
// ---------------------------------------------------------------------------

class FakeScaffoldModel extends Model
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
class ThrowingScaffoldModel extends Model
{
    protected $table = 'throwing_models';

    public function __construct()
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

    public function test_scaffold_without_a_model_or_all_flag_fails(): void
    {
        $this->artisan('laragraph:scaffold')
            ->expectsOutputToContain('Pass a model name')
            ->assertFailed();
    }

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
        File::ensureDirectoryExists(dirname($configPath));
        file_put_contents($configPath, "<?php\nreturn [];\n");

        $this->artisan('laragraph:scaffold', [
            'model'      => FakeScaffoldModel::class,
            '--register' => true,
        ])->assertSuccessful()
          ->expectsOutputToContain("Don't forget to register");

        // None of the anchors exist in an empty array — nothing should be written.
        $this->assertSame("<?php\nreturn [];\n", file_get_contents($configPath));

        @unlink($configPath);
    }

    // -------------------------------------------------------------------------
    // --register against the real, published config shape
    // -------------------------------------------------------------------------

    private function publishRealConfig(): string
    {
        $configPath = config_path('laragraph.php');
        File::ensureDirectoryExists(dirname($configPath));
        copy(__DIR__ . '/../../config/laragraph.php', $configPath);

        return $configPath;
    }

    public function test_register_inserts_entries_into_the_real_published_config_shape(): void
    {
        $configPath = $this->publishRealConfig();

        try {
            $this->artisan('laragraph:scaffold', [
                'model'       => FakeScaffoldModel::class,
                '--with-crud' => true,
                '--register'  => true,
            ])->assertSuccessful()
              ->expectsOutputToContain('Registered the generated classes');

            // Valid PHP, and the array actually contains what we expect —
            // not just a string match, but real evaluation of the config.
            $config = require $configPath;

            $this->assertSame(
                FakeScaffoldModelType::class,
                $config['types']['FakeScaffoldModel'],
            );
            $this->assertSame(
                FakeScaffoldModelQuery::class,
                $config['schemas']['default']['query']['fakeScaffoldModel'],
            );
            $this->assertSame(
                FakeScaffoldModelsQuery::class,
                $config['schemas']['default']['query']['fakeScaffoldModels'],
            );
            $this->assertSame(
                CreateFakeScaffoldModelMutation::class,
                $config['schemas']['default']['mutation']['createFakeScaffoldModel'],
            );
            $this->assertSame(
                UpdateFakeScaffoldModelMutation::class,
                $config['schemas']['default']['mutation']['updateFakeScaffoldModel'],
            );
            $this->assertSame(
                DeleteFakeScaffoldModelMutation::class,
                $config['schemas']['default']['mutation']['deleteFakeScaffoldModel'],
            );
        } finally {
            @unlink($configPath);
        }
    }

    public function test_register_is_idempotent_on_a_second_run(): void
    {
        $configPath = $this->publishRealConfig();

        try {
            $this->artisan('laragraph:scaffold', ['model' => FakeScaffoldModel::class, '--register' => true])
                ->assertSuccessful();
            $this->artisan('laragraph:scaffold', ['model' => FakeScaffoldModel::class, '--register' => true, '--force' => true])
                ->assertSuccessful();

            $config = require $configPath;
            $this->assertSame(
                FakeScaffoldModelType::class,
                $config['types']['FakeScaffoldModel'],
            );
            // Exactly one entry — the second run didn't duplicate it.
            $this->assertSame(
                1,
                substr_count(file_get_contents($configPath), "'FakeScaffoldModel' =>"),
            );
        } finally {
            @unlink($configPath);
        }
    }

    public function test_register_falls_back_to_the_tip_when_a_second_schema_makes_query_ambiguous(): void
    {
        $configPath = $this->publishRealConfig();
        $original   = file_get_contents($configPath);

        // A second schema means a second 'query' => [ array — no longer safe to guess which one.
        $withSecondSchema = preg_replace(
            "/'schemas' => \[/",
            "'schemas' => [\n        'admin' => [\n            'query' => [\n            ],\n            'mutation' => [\n            ],\n        ],",
            (string) $original,
            1,
        );
        file_put_contents($configPath, $withSecondSchema);

        try {
            $this->artisan('laragraph:scaffold', ['model' => FakeScaffoldModel::class, '--register' => true])
                ->assertSuccessful()
                ->expectsOutputToContain("Don't forget to register");

            // Untouched beyond the fixture's own edit above.
            $this->assertSame($withSecondSchema, file_get_contents($configPath));
        } finally {
            @unlink($configPath);
        }
    }

    // -------------------------------------------------------------------------
    // render() — missing stub throws RuntimeException (line 264)
    // -------------------------------------------------------------------------

    public function test_render_throws_for_missing_stub(): void
    {
        $command = new ScaffoldCommand();
        $reflect = new \ReflectionClass($command);
        $method  = $reflect->getMethod('render');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/stub \[nonexistent.stub\] not found/');
        $method->invoke($command, 'nonexistent', []);
    }
}
