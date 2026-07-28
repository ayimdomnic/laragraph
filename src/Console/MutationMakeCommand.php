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
#[AsCommand(name: 'laragraph:make:mutation', description: 'Create a new GraphQL Mutation class')]
class MutationMakeCommand extends GeneratorCommand
{
    protected $name        = 'laragraph:make:mutation';
    protected $description = 'Create a new GraphQL Mutation class';
    protected $type        = 'Mutation';

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/mutation.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\GraphQL\\Mutations';
    }

    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the mutation if it already exists'],
        ];
    }
}
