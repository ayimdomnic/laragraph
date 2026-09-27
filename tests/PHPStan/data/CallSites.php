<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\PHPStan\Data;

use Ayimdomnic\Laragraph\Facades\Laragraph as LaragraphFacade;
use Ayimdomnic\Laragraph\Laragraph;

class StaticCallSites
{
    public function registeredInConfig(): void
    {
        LaragraphFacade::type('Foo');
    }

    public function discoveredByBasename(): void
    {
        LaragraphFacade::type('Bar');
    }

    public function discoveredByNameConstant(): void
    {
        LaragraphFacade::type('CustomBaz');
    }

    public function unregistered(): void
    {
        LaragraphFacade::type('Typo');
    }

    public function dynamicNameIsSkipped(string $name): void
    {
        LaragraphFacade::type($name);
    }
}

class MethodCallSites
{
    public function registeredInConfig(Laragraph $manager): void
    {
        $manager->type('Foo');
    }

    public function unregistered(Laragraph $manager): void
    {
        $manager->type('Typo');
    }

    public function unrelatedTypeIsSkipped(object $notLaragraph): void
    {
        $notLaragraph->type('Typo');
    }
}
