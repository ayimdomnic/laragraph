<?php

/**
 * This file is part of the Laragraph package.
 *
 * (c) Odhiambo Dormnic <ayimdomnic@gmail.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'laragraph:type:make', description: 'Create a new GraphQL type class')]
class TypeMakeCommand extends GeneratorCommand
{
    protected $name = 'laragraph:type:make';
    protected $description = 'Create a new GraphQL type class';
    protected $type = 'Type';

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/type.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\GraphQL\Types';
    }
}
