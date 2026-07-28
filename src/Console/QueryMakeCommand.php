<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * This file is part of the Laragraph package.
 *
 * (c) Odhiambo Dormnic <ayimdomnic@gmail.com>
 */
#[AsCommand(name: 'laragraph:make:query', description: 'Create a new GraphQL Query class')]
class QueryMakeCommand extends GeneratorCommand
{
    protected $name        = 'laragraph:make:query';
    protected $description = 'Create a new GraphQL Query class';
    protected $type        = 'Query';

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/query.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\GraphQL\\Queries';
    }

    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the query if it already exists'],
        ];
    }
}
