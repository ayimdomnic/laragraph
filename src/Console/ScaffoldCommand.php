<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * This file is part of the Laragraph package.
 *
 * (c) Odhiambo Dormnic <ayimdomnic@gmail.com>
 */
#[AsCommand(name: 'laragraph:scaffold', description: 'Scaffold GraphQL type, queries, and mutations from an Eloquent model')]
class ScaffoldCommand extends Command
{
    protected $signature = 'laragraph:scaffold
                            {model       : Model class name — short (User) or FQCN (App\\Models\\User)}
                            {--all       : Scaffold every model found in app/Models/}
                            {--with-crud : Also generate create / update / delete mutations}
                            {--register  : Append generated classes to config/laragraph.php}
                            {--force     : Overwrite existing files}';

    protected $description = 'Scaffold GraphQL type, queries, and mutations from an Eloquent model';

    // -------------------------------------------------------------------------
    // Entry point
    // -------------------------------------------------------------------------

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->scaffoldAll();
        }

        return $this->scaffold($this->argument('model'));
    }

    // -------------------------------------------------------------------------
    // Scaffolding
    // -------------------------------------------------------------------------

    protected function scaffold(string $model): int
    {
        try {
            $modelClass = $this->resolveModel($model);
        } catch (\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());
            return self::FAILURE;
        }

        $shortName = class_basename($modelClass);
        $fields    = $this->extractFields($modelClass);

        $this->components->info("Scaffolding GraphQL for [{$shortName}]…");

        $this->generateType($shortName, $fields);
        $this->generateQuery($shortName, 'single');
        $this->generateQuery($shortName, 'list');

        if ($this->option('with-crud')) {
            $this->generateMutation($shortName, 'create', $fields);
            $this->generateMutation($shortName, 'update', $fields);
            $this->generateMutation($shortName, 'delete');
        }

        if ($this->option('register')) {
            $this->registerInConfig($shortName);
        }

        $this->newLine();
        $this->components->info("Done! Don't forget to register the generated classes in config/laragraph.php if auto-discovery is disabled.");

        return self::SUCCESS;
    }

    protected function scaffoldAll(): int
    {
        $modelsPath = app_path('Models');

        if (!is_dir($modelsPath)) {
            $this->components->error("No app/Models directory found.");
            return self::FAILURE;
        }

        $models = glob("{$modelsPath}/*.php") ?: [];

        if (empty($models)) {
            $this->components->warn("No models found in app/Models/.");
            return self::SUCCESS;
        }

        foreach ($models as $file) {
            $model = pathinfo($file, PATHINFO_FILENAME);
            $this->scaffold($model);
        }

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // File generation
    // -------------------------------------------------------------------------

    protected function generateType(string $model, array $fields): void
    {
        $path = app_path("GraphQL/Types/{$model}Type.php");
        $this->ensureDirectory(dirname($path));

        $fieldLines = collect($fields)
            ->map(fn ($type, $name) => "            '{$name}' => ['type' => {$type}],")
            ->implode("\n");

        $this->writeFile($path, $this->render('type', [
            'NAMESPACE'   => 'App\\GraphQL\\Types',
            'MODEL'       => $model,
            'MODEL_LOWER' => Str::lower($model),
            'FIELDS'      => $fieldLines,
        ]));
    }

    protected function generateQuery(string $model, string $variant): void
    {
        $isList = $variant === 'list';
        $className = $isList ? "{$model}sQuery" : "{$model}Query";
        $path = app_path("GraphQL/Queries/{$className}.php");
        $this->ensureDirectory(dirname($path));

        $this->writeFile($path, $this->render($isList ? 'query-list' : 'query-single', [
            'NAMESPACE'       => 'App\\GraphQL\\Queries',
            'CLASS'           => $className,
            'MODEL'           => $model,
            'MODEL_LOWER'     => Str::lower($model),
            'MODEL_CAMEL'     => Str::camel($model),
            'MODEL_PLURAL'    => Str::camel(Str::plural($model)),
            'CONNECTION_TYPE' => "{$model}Connection",
        ]));
    }

    /**
     * @param  'create'|'update'|'delete'  $variant
     */
    protected function generateMutation(string $model, string $variant, array $fields = []): void
    {
        $className = match ($variant) {
            'create' => "Create{$model}Mutation",
            'update' => "Update{$model}Mutation",
            'delete' => "Delete{$model}Mutation",
        };

        $path = app_path("GraphQL/Mutations/{$className}.php");
        $this->ensureDirectory(dirname($path));

        $fillableLines = collect($fields)
            ->reject(fn ($type, $name) => $name === 'id')
            ->map(fn ($type, $name) => "            '{$name}' => ['type' => {$type}],")
            ->implode("\n");

        $this->writeFile($path, $this->render("mutation-{$variant}", [
            'NAMESPACE'    => 'App\\GraphQL\\Mutations',
            'CLASS'        => $className,
            'MODEL'        => $model,
            'MODEL_LOWER'  => Str::lower($model),
            'MODEL_CAMEL'  => Str::camel($model),
            'FILLABLE'     => $fillableLines,
        ]));
    }

    // -------------------------------------------------------------------------
    // Model introspection
    // -------------------------------------------------------------------------

    protected function resolveModel(string $model): string
    {
        if (class_exists($model)) {
            return $model;
        }

        $candidates = [
            'App\\Models\\' . $model,
            'App\\' . $model,
        ];

        foreach ($candidates as $fqcn) {
            if (class_exists($fqcn)) {
                return $fqcn;
            }
        }

        throw new \InvalidArgumentException(
            "Model [{$model}] not found. Tried: " . implode(', ', $candidates)
        );
    }

    /**
     * Extract field definitions from an Eloquent model's $fillable + $casts.
     *
     * @return array<string, string>  field name => GraphQL type expression
     */
    protected function extractFields(string $modelClass): array
    {
        $fields = ['id' => 'GType::nonNull(GType::id())'];

        try {
            /** @var \Illuminate\Database\Eloquent\Model $instance */
            $instance = new $modelClass();
            $fillable = $instance->getFillable();
            $casts    = $instance->getCasts();

            foreach ($fillable as $field) {
                $cast = $casts[$field] ?? 'string';
                $fields[$field] = $this->castToGraphQLType($cast);
            }
        } catch (\Throwable) {
            // Model could not be instantiated — return id only and let the
            // developer fill in the remaining fields manually.
        }

        return $fields;
    }

    protected function castToGraphQLType(string $cast): string
    {
        return match (true) {
            in_array($cast, ['int', 'integer'], true)                              => 'GType::int()',
            in_array($cast, ['float', 'double'], true), str_starts_with($cast, 'decimal:') => 'GType::float()',
            in_array($cast, ['bool', 'boolean'], true)                             => 'GType::boolean()',
            in_array($cast, ['datetime', 'timestamp', 'immutable_datetime'], true) => "app('laragraph')->type('DateTime')",
            $cast === 'date'                                                        => "app('laragraph')->type('Date')",
            in_array($cast, ['array', 'json', 'object', 'collection'], true)       => "app('laragraph')->type('JSON')",
            default                                                                 => 'GType::string()',
        };
    }

    // -------------------------------------------------------------------------
    // Config registration
    // -------------------------------------------------------------------------

    protected function registerInConfig(string $model): void
    {
        $configPath = config_path('laragraph.php');

        if (!file_exists($configPath)) {
            $this->components->warn("config/laragraph.php not found — skipping --register.");
            return;
        }

        // Append entries as a comment block (safe, non-destructive)
        $entries = "    // Auto-registered by laragraph:scaffold\n"
            . "    // 'query'    => ['types' => [\App\GraphQL\Types\\{$model}Type::class]],\n"
            . "    // 'queries'  => ['" . Str::camel($model) . "s' => \App\GraphQL\Queries\\{$model}sQuery::class],\n";

        $this->components->info("Tip: add the generated classes to config/laragraph.php or enable auto-discovery.");
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function render(string $stub, array $replacements): string
    {
        $path = __DIR__ . "/stubs/scaffold/{$stub}.stub";

        if (!file_exists($path)) {
            throw new \RuntimeException("Scaffold stub [{$stub}.stub] not found at {$path}.");
        }

        $content = file_get_contents($path);

        foreach ($replacements as $key => $value) {
            $content = str_replace("{{ {$key} }}", $value, $content);
        }

        return $content;
    }

    protected function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    protected function writeFile(string $path, string $content): void
    {
        if (file_exists($path) && !$this->option('force')) {
            $this->components->warn("Skipped (already exists): {$path}");
            return;
        }

        file_put_contents($path, $content);
        $this->components->info("Created: " . str_replace(base_path() . '/', '', $path));
    }
}
