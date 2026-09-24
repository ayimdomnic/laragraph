<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Console;

use Ayimdomnic\Laragraph\Laragraph;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Build every configured schema and run the full GraphQL type-system
 * validation on it — a cheap deploy/CI gate that catches broken types,
 * missing registrations and invalid field definitions before users do.
 *
 *   php artisan laragraph:validate
 *   php artisan laragraph:validate --schema=admin
 */
#[AsCommand(name: 'laragraph:validate', description: 'Build and validate the configured GraphQL schemas')]
class ValidateSchemaCommand extends Command
{
    protected $signature = 'laragraph:validate
                            {--schema=* : Only validate these schemas (defaults to all configured schemas)}';

    protected $description = 'Build and validate the configured GraphQL schemas';

    public function handle(Laragraph $laragraph): int
    {
        /** @var list<string> $names */
        $names = $this->option('schema') ?: array_keys((array) config('laragraph.schemas', []));
        $valid = true;

        foreach ($names as $name) {
            // The GraphiQL route (/graphql/graphiql) shadows a schema of that name.
            if ($name === 'graphiql' && (config('laragraph.graphiql.enabled') ?? config('app.debug'))) {
                $valid = false;
                $this->components->twoColumnDetail($name, '<fg=red;options=bold>UNREACHABLE</>');
                $this->components->bulletList(['/graphql/graphiql serves the GraphiQL IDE; rename this schema or disable GraphiQL.']);

                continue;
            }

            try {
                $laragraph->schema($name)->assertValid();
                $this->components->twoColumnDetail($name, '<fg=green;options=bold>VALID</>');
            } catch (\Throwable $e) {
                $valid = false;
                $this->components->twoColumnDetail($name, '<fg=red;options=bold>INVALID</>');
                $this->components->bulletList([$e->getMessage()]);
            }
        }

        return $valid ? self::SUCCESS : self::FAILURE;
    }
}
