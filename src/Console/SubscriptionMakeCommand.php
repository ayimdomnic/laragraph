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
#[AsCommand(name: 'laragraph:make:subscription', description: 'Create a new GraphQL Subscription class')]
class SubscriptionMakeCommand extends GeneratorCommand
{
    protected $name        = 'laragraph:make:subscription';
    protected $description = 'Create a new GraphQL Subscription class';
    protected $type        = 'Subscription';

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/subscription.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\GraphQL\\Subscriptions';
    }

    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the subscription if it already exists'],
        ];
    }
}
