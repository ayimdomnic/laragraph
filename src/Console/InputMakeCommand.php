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
#[AsCommand(name: 'laragraph:make:input', description: 'Create a new GraphQL Input Type class')]
class InputMakeCommand extends GeneratorCommand
{
    protected $name        = 'laragraph:make:input';
    protected $description = 'Create a new GraphQL Input Type class';
    protected $type        = 'Input';

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/input.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\GraphQL\\Inputs';
    }

    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the input type if it already exists'],
        ];
    }
}
