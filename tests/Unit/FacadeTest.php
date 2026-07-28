<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit;

use Ayimdomnic\Laragraph\Facades\Laragraph as LaragraphFacade;
use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\Tests\TestCase;

class FacadeTest extends TestCase
{
    public function test_facade_resolves_laragraph_instance(): void
    {
        $this->assertInstanceOf(Laragraph::class, LaragraphFacade::getFacadeRoot());
    }

    public function test_facade_is_same_singleton_as_container(): void
    {
        $this->assertSame(
            $this->app->make('laragraph'),
            LaragraphFacade::getFacadeRoot(),
        );
    }
}
