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
#[AsCommand(name: 'laragraph:make:loader', description: 'Create a new custom DataLoader (BatchResolver) class')]
class LoaderMakeCommand extends GeneratorCommand
{
    protected $name        = 'laragraph:make:loader';
    protected $description = 'Create a new custom DataLoader (BatchResolver) class';
    protected $type        = 'Loader';

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/loader.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\GraphQL\\Loaders';
    }

    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the loader if it already exists'],
        ];
    }
}
