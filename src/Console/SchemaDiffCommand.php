<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Console;

use Ayimdomnic\Laragraph\Exceptions\SchemaException;
use Ayimdomnic\Laragraph\Laragraph;
use GraphQL\Utils\BreakingChangesFinder;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Compare the current schema against a previously exported SDL baseline and
 * report breaking/dangerous changes — a CI gate against accidentally
 * breaking a schema clients already depend on.
 *
 * Classification is webonyx's own {@see BreakingChangesFinder} (the same
 * utility graphql-js's `findBreakingChanges` is based on) — this command
 * only wires it up, it doesn't reimplement change detection.
 *
 * ## Usage
 *
 *   php artisan laragraph:schema:diff --against=schema.graphql
 *   php artisan laragraph:schema:diff --schema=admin --against=admin.graphql --fail-on-dangerous
 *
 * When a schema change is intentional, refresh the baseline and commit it:
 *
 *   php artisan laragraph:schema:export --output=schema.graphql
 */
#[AsCommand(name: 'laragraph:schema:diff', description: 'Diff the current GraphQL schema against a baseline SDL file')]
class SchemaDiffCommand extends Command
{
    protected $signature = 'laragraph:schema:diff
                            {--schema=default      : Schema name to compare}
                            {--against=             : Path to the baseline SDL file exported by laragraph:schema:export}
                            {--fail-on-dangerous    : Also fail when dangerous (non-breaking but risky) changes are found}';

    protected $description = 'Diff the current GraphQL schema against a baseline SDL file';

    public function handle(Laragraph $laragraph): int
    {
        $against = $this->option('against');

        if (!is_string($against) || $against === '') {
            $this->components->error('The --against option is required: pass the path to a baseline SDL file (see laragraph:schema:export).');

            return self::INVALID;
        }

        if (!is_file($against)) {
            $this->components->info("No baseline found at [{$against}] yet — nothing to compare against. Run laragraph:schema:export to create one.");

            return self::SUCCESS;
        }

        $schemaOption = $this->option('schema');
        $schemaName   = is_string($schemaOption) && $schemaOption !== '' ? $schemaOption : 'default';

        try {
            // Round-tripped through SDL on both sides (not the live PHP schema
            // against an SDL-parsed one): a custom scalar's real PHP class and
            // BuildSchema's generic CustomScalarType are unrelated to each other
            // by PHP's `instanceof`, which findTypesThatChangedKind() uses to
            // detect a type's "kind" — comparing the live object graph against
            // an SDL-parsed one would misreport every custom scalar as a
            // breaking change on every run, even with zero real changes. SDL is
            // the actual public contract anyway; a type's PHP implementation
            // class was never part of it.
            $newSchema = BuildSchema::build(SchemaPrinter::doPrint($laragraph->schema($schemaName)));
        } catch (SchemaException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        try {
            $oldSchema = BuildSchema::build((string) file_get_contents($against));
        } catch (\Throwable $e) {
            $this->components->error("Could not parse the baseline at [{$against}]: {$e->getMessage()}");

            return self::FAILURE;
        }

        $breaking  = BreakingChangesFinder::findBreakingChanges($oldSchema, $newSchema);
        $dangerous = BreakingChangesFinder::findDangerousChanges($oldSchema, $newSchema);

        if ($breaking === [] && $dangerous === []) {
            $this->components->info("No breaking or dangerous changes in [{$schemaName}] against [{$against}].");

            return self::SUCCESS;
        }

        foreach ($breaking as $change) {
            $this->components->twoColumnDetail($change['type'], '<fg=red;options=bold>BREAKING</>');
            $this->components->bulletList([$change['description']]);
        }

        foreach ($dangerous as $change) {
            $this->components->twoColumnDetail($change['type'], '<fg=yellow;options=bold>DANGEROUS</>');
            $this->components->bulletList([$change['description']]);
        }

        if ($breaking !== []) {
            return self::FAILURE;
        }

        // $breaking is empty here, so the earlier guard guarantees $dangerous isn't.
        return $this->option('fail-on-dangerous') ? self::FAILURE : self::SUCCESS;
    }
}
