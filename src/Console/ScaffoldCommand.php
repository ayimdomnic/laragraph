<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;
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
                            {model?      : Model class name — short (User) or FQCN (App\\Models\\User)}
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

        $model = $this->argument('model');

        if (!is_string($model) || $model === '') {
            $this->components->error('Pass a model name, or use --all to scaffold every model in app/Models.');

            return self::FAILURE;
        }

        return $this->scaffold($model);
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

        // Never expose attributes the model hides from serialisation (passwords, tokens, …).
        $this->generateType($shortName, $modelClass, array_diff_key($fields, array_flip($this->hiddenAttributes($modelClass))));
        $this->generateQuery($shortName, 'single');
        $this->generateQuery($shortName, 'list');

        if ($this->option('with-crud')) {
            $this->generateMutation($shortName, 'create', $fields);
            $this->generateMutation($shortName, 'update', $fields);
            $this->generateMutation($shortName, 'delete');
        }

        $registered = $this->option('register') && $this->registerInConfig($shortName);

        $this->newLine();
        $this->components->info($registered
            ? 'Done!'
            : "Done! Don't forget to register the generated classes in config/laragraph.php if auto-discovery is disabled.");

        return self::SUCCESS;
    }

    protected function scaffoldAll(): int
    {
        $modelsPath = app_path('Models');

        if (!is_dir($modelsPath)) {
            $this->components->error('No app/Models directory found.');
            return self::FAILURE;
        }

        $models = glob("{$modelsPath}/*.php") ?: [];

        if ($models === []) {
            $this->components->warn('No models found in app/Models/.');
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

    /**
     * @param class-string $modelClass
     * @param array<string, string> $fields
     */
    protected function generateType(string $model, string $modelClass, array $fields): void
    {
        $path = app_path("GraphQL/Types/{$model}Type.php");
        $this->ensureDirectory(dirname($path));

        $fieldLines = collect($fields)
            ->map(fn($type, $name): string => "            '{$name}' => ['type' => {$type}],")
            ->implode("\n");

        $relations = $this->extractRelations($modelClass);

        $relationFieldLines = collect($relations)
            ->map(function (array $relation, string $name): string {
                $relatedType = "app('laragraph')->type('" . class_basename($relation['relatedClass']) . "')";

                if ($this->isManyRelation($relation['relationType'])) {
                    $relatedType = "GType::listOf({$relatedType})";
                }

                return "            '{$name}' => ['type' => {$relatedType}],";
            })
            ->implode("\n");

        $relationMethods = collect($relations)
            ->keys()
            ->map(fn(string $name): string => $this->relationResolverMethod($modelClass, $name))
            ->implode('');

        $this->writeFile($path, $this->render('type', [
            'NAMESPACE'        => 'App\\GraphQL\\Types',
            'MODEL'            => $model,
            'MODEL_LOWER'      => Str::lower($model),
            'FIELDS'           => collect([$fieldLines, $relationFieldLines])->filter()->implode("\n"),
            'RELATION_METHODS' => $relationMethods,
        ]));
    }

    /**
     * @param class-string $modelClass
     */
    private function relationResolverMethod(string $modelClass, string $relation): string
    {
        $method = 'resolve' . ucfirst($relation) . 'Field';

        return sprintf(
            '
    protected function %s(mixed $root, array $args, mixed $context): mixed
    {
        return $this->batchRelation(\%s::class, \'%s\', $root, $context);
    }
',
            $method,
            $modelClass,
            $relation,
        );
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
     * @param array<string, string> $fields
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
            ->reject(fn($type, $name): bool => $name === 'id')
            ->map(fn($type, $name): string => "            '{$name}' => ['type' => {$type}],")
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

    /**
     * @return class-string
     */
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
            "Model [{$model}] not found. Tried: " . implode(', ', $candidates),
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
            /** @var Model $instance */
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

    /**
     * @param class-string $modelClass
     * @return list<string>
     */
    protected function hiddenAttributes(string $modelClass): array
    {
        try {
            /** @var Model $instance */
            $instance = new $modelClass();

            return array_values($instance->getHidden());
        } catch (\Throwable) {
            return [];
        }
    }

    protected function castToGraphQLType(string $cast): string
    {
        if (enum_exists($cast)) {
            return "app('laragraph')->type('" . class_basename($cast) . "')";
        }

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

    /**
     * Extract Eloquent relation methods via reflection — a locally scoped
     * subset of Illuminate\Database\Eloquent\ModelInspector's own detection
     * (an internal class, not part of any public contract): public,
     * zero-required-param, non-static, non-abstract methods not declared on
     * Model itself, whose declared return type is a Relation subclass or
     * whose source references one of Eloquent's relation-builder methods,
     * then actually invoked and checked `instanceof Relation`.
     *
     * `morphTo()` relations are skipped — the related model can't be known
     * without an actual row to read the morph type from.
     *
     * @param class-string $modelClass
     * @return array<string, array{relatedClass: class-string<Model>, relationType: string}>
     */
    protected function extractRelations(string $modelClass): array
    {
        $relations = [];

        try {
            /** @var Model $instance */
            $instance   = new $modelClass();
            $reflection = new \ReflectionClass($instance);

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isStatic()
                    || $method->isAbstract()
                    || $method->getDeclaringClass()->getName() === Model::class
                    || $method->getNumberOfParameters() > 0
                ) {
                    continue;
                }

                if (!$this->declaresRelationReturnType($method) && !$this->methodBodyBuildsRelation($method)) {
                    continue;
                }

                try {
                    $relation = $method->invoke($instance);
                } catch (\Throwable) {
                    continue;
                }

                if (!$relation instanceof Relation || $relation instanceof MorphTo) {
                    continue;
                }

                $relations[$method->getName()] = [
                    'relatedClass' => $relation->getRelated()::class,
                    'relationType' => class_basename($relation),
                ];
            }
        } catch (\Throwable) {
            // Model could not be instantiated — no relations detected.
        }

        return $relations;
    }

    private function declaresRelationReturnType(\ReflectionMethod $method): bool
    {
        $returnType = $method->getReturnType();

        return $returnType instanceof \ReflectionNamedType
            && !$returnType->isBuiltin()
            && is_subclass_of($returnType->getName(), Relation::class);
    }

    /**
     * Fallback for a relation method with no declared return type: scan its
     * source for a call to one of Eloquent's relation-builder methods.
     */
    private function methodBodyBuildsRelation(\ReflectionMethod $method): bool
    {
        $filename = $method->getFileName();

        if ($filename === false) {
            return false;
        }

        $file = new \SplFileObject($filename);
        $file->seek($method->getStartLine() - 1);

        $code = '';
        while ($file->key() < $method->getEndLine()) {
            $line = $file->current();
            $code .= is_string($line) ? trim($line) : '';
            $file->next();
        }

        $relationBuilders = [
            'hasMany', 'hasManyThrough', 'hasOneThrough', 'belongsToMany',
            'hasOne', 'belongsTo', 'morphOne', 'morphTo', 'morphMany',
            'morphToMany', 'morphedByMany',
        ];

        foreach ($relationBuilders as $builder) {
            if (str_contains($code, '$this->' . $builder . '(')) {
                return true;
            }
        }

        return false;
    }

    private function isManyRelation(string $relationType): bool
    {
        return in_array($relationType, ['HasMany', 'HasManyThrough', 'BelongsToMany', 'MorphMany', 'MorphToMany', 'MorphedByMany'], true);
    }

    // -------------------------------------------------------------------------
    // Config registration
    // -------------------------------------------------------------------------

    /**
     * Insert the generated classes into config/laragraph.php's 'types' and
     * schemas.default 'query'/'mutation' arrays. Never guesses: any array
     * whose opening bracket doesn't appear exactly once at the start of its
     * own line (multiple schemas, a reformatted file, a stale doc-comment
     * example) is left alone, and the whole file is left untouched — falling
     * back to the manual tip — the moment any single insertion isn't safe.
     */
    protected function registerInConfig(string $model): bool
    {
        $configPath = config_path('laragraph.php');

        if (!file_exists($configPath)) {
            $this->components->warn('config/laragraph.php not found — skipping --register.');
            return false;
        }

        $source = (string) file_get_contents($configPath);
        $failed = false;

        $insert = function (string $arrayKey, string $entryKey, string $line) use (&$source, &$failed): void {
            if ($failed) {
                return;
            }

            $result = $this->insertConfigEntry($source, $arrayKey, $entryKey, $line);

            if ($result === null) {
                $failed = true;
                return;
            }

            $source = $result;
        };

        $insert('types', "'{$model}'", "        '{$model}' => \\App\\GraphQL\\Types\\{$model}Type::class,");
        $insert('query', "'" . Str::camel($model) . "'", "                '" . Str::camel($model) . "' => \\App\\GraphQL\\Queries\\{$model}Query::class,");
        $insert('query', "'" . Str::camel(Str::plural($model)) . "'", "                '" . Str::camel(Str::plural($model)) . "' => \\App\\GraphQL\\Queries\\{$model}sQuery::class,");

        if ($this->option('with-crud')) {
            foreach (['create' => 'Create', 'update' => 'Update', 'delete' => 'Delete'] as $verb => $prefix) {
                $key   = Str::camel("{$verb}{$model}");
                $class = "{$prefix}{$model}Mutation";
                $insert('mutation', "'{$key}'", "                '{$key}' => \\App\\GraphQL\\Mutations\\{$class}::class,");
            }
        }

        if ($failed || !$this->isValidPhp($source)) {
            $this->components->info('Tip: add the generated classes to config/laragraph.php or enable auto-discovery.');
            return false;
        }

        file_put_contents($configPath, $source);
        $this->components->info('Registered the generated classes in config/laragraph.php.');

        return true;
    }

    /**
     * Insert `$line` right after `'$arrayKey' => [`'s opening bracket, but
     * only when that array starts at the beginning of its own line (so a
     * commented-out example of the same shape, e.g. this file's own "Example:"
     * doc-block, is never mistaken for the real array) and appears exactly
     * once in the whole file. Returns null — "not safe to insert" — otherwise,
     * and returns $source unchanged when $entryKey is already registered.
     */
    protected function insertConfigEntry(string $source, string $arrayKey, string $entryKey, string $line): ?string
    {
        if (str_contains($source, $entryKey . ' =>')) {
            return $source;
        }

        $pattern = '/[\'"]' . preg_quote($arrayKey, '/') . '[\'"]\s*=>\s*\[\r?\n/';

        if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        $candidates = array_values(array_filter($matches[0], function (array $match) use ($source): bool {
            [, $offset] = $match;
            $lineStart  = strrpos(substr($source, 0, $offset), "\n");
            $lineStart  = $lineStart === false ? 0 : $lineStart + 1;

            return trim(substr($source, $lineStart, $offset - $lineStart)) === '';
        }));

        if (count($candidates) !== 1) {
            return null;
        }

        [$match, $offset] = $candidates[0];
        $insertAt = $offset + strlen($match);

        return substr($source, 0, $insertAt) . $line . "\n" . substr($source, $insertAt);
    }

    /**
     * A last safety net before overwriting the consumer's config file: never
     * write back something that isn't even valid PHP, however unlikely that
     * is given the careful insertion above.
     */
    protected function isValidPhp(string $source): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'laragraph_config_');

        if ($tmp === false) {
            return false;
        }

        try {
            file_put_contents($tmp, $source);
            exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $output, $exitCode);

            return $exitCode === 0;
        } finally {
            unlink($tmp);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string, string> $replacements
     */
    protected function render(string $stub, array $replacements): string
    {
        $path = __DIR__ . "/stubs/scaffold/{$stub}.stub";

        if (!file_exists($path)) {
            throw new \RuntimeException("Scaffold stub [{$stub}.stub] not found at {$path}.");
        }

        $content = File::get($path);

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
        $this->components->info('Created: ' . str_replace(base_path() . '/', '', $path));
    }
}
