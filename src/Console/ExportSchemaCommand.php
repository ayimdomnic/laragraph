<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Console;

use Ayimdomnic\Laragraph\Exceptions\SchemaException;
use Ayimdomnic\Laragraph\Laragraph;
use GraphQL\Utils\SchemaPrinter;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Export a compiled GraphQL schema as Schema Definition Language (SDL).
 *
 * The SDL representation is useful for:
 *   - Client code-generation (graphql-code-generator, etc.)
 *   - Schema diffing / change detection in CI
 *   - Sharing the public API surface with consumers
 *
 * ## Usage
 *
 *   php artisan laragraph:schema:export
 *   php artisan laragraph:schema:export --schema=admin
 *   php artisan laragraph:schema:export --output=schema.graphql
 *   php artisan laragraph:schema:export --schema=admin --output=storage/graphql/admin.graphql
 */
#[AsCommand(name: 'laragraph:schema:export', description: 'Export a GraphQL schema as SDL')]
class ExportSchemaCommand extends Command
{
    protected $signature = 'laragraph:schema:export
                            {--schema=default    : Schema name to export}
                            {--output=           : Destination file path (prints to stdout if omitted)}';

    protected $description = 'Export a compiled GraphQL schema as Schema Definition Language (SDL)';

    public function handle(Laragraph $laragraph): int
    {
        $schemaName = (string) ($this->option('schema') ?: 'default');

        try {
            $schema = $laragraph->schema($schemaName);
        } catch (SchemaException $e) {
            $this->components->error($e->getMessage());
            return self::FAILURE;
        }

        $sdl = SchemaPrinter::doPrint($schema);

        $outputPath = $this->option('output');

        if ($outputPath) {
            $dir = dirname((string) $outputPath);
            if ($dir !== '.' && !is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents((string) $outputPath, $sdl);
            $this->components->info("Schema [{$schemaName}] exported to {$outputPath}");
        } else {
            $this->line($sdl);
        }

        return self::SUCCESS;
    }
}
