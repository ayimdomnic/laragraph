<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * This file is part of the Laragraph package.
 *
 * (c) Odhiambo Dormnic <ayimdomnic@gmail.com>
 */
#[AsCommand(name: 'laragraph:make:exception', description: 'Create a new GraphQL-aware exception class')]
class ExceptionMakeCommand extends GeneratorCommand
{
    protected $name        = 'laragraph:make:exception';
    protected $description = 'Create a new GraphQL-aware exception class';
    protected $type        = 'Exception';

    public function handle(): ?bool
    {
        $result = parent::handle();

        if ($result !== false) {
            $key = Str::snake($this->errorBaseName());
            $this->components->info("Add its message under the 'errors' key in your app's lang files, e.g. lang/en/errors.php: '{$key}' => '...'.");
        }

        return $result;
    }

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/exception.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\GraphQL\\Exceptions';
    }

    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the exception if it already exists'],
        ];
    }

    /**
     * @param string $name
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);
        $base = $this->errorBaseName();

        return str_replace(
            ['DummyCode', 'DummyKey'],
            [Str::upper(Str::snake($base)), Str::snake($base)],
            $stub,
        );
    }

    /** The class name with a trailing "Exception" stripped, e.g. "InvalidCredentials". */
    protected function errorBaseName(): string
    {
        $short = class_basename($this->qualifyClass($this->getNameInput()));

        return Str::endsWith($short, 'Exception') ? substr($short, 0, -\strlen('Exception')) : $short;
    }
}
