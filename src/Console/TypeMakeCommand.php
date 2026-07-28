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
#[AsCommand(name: 'laragraph:make:type', description: 'Create a new GraphQL Object Type class')]
class TypeMakeCommand extends GeneratorCommand
{
    protected $name        = 'laragraph:make:type';
    protected $description = 'Create a new GraphQL Object Type class';
    protected $type        = 'Type';

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/type.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\GraphQL\\Types';
    }

    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the type if it already exists'],
        ];
    }
}
