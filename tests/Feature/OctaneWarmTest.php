<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\LaragraphServiceProvider;
use Ayimdomnic\Laragraph\Tests\TestCase;

class OctaneWarmTest extends TestCase
{
    public function test_laragraph_is_warmed_when_octane_is_configured(): void
    {
        config(['octane.warm' => ['auth', 'cache']]);

        (new LaragraphServiceProvider($this->app))->boot();

        $this->assertSame(['auth', 'cache', 'laragraph'], config('octane.warm'));
    }

    public function test_it_is_added_only_once(): void
    {
        config(['octane.warm' => ['laragraph']]);

        (new LaragraphServiceProvider($this->app))->boot();

        $this->assertSame(['laragraph'], config('octane.warm'));
    }

    public function test_warming_can_be_turned_off(): void
    {
        config(['octane.warm' => ['auth'], 'laragraph.octane.warm' => false]);

        (new LaragraphServiceProvider($this->app))->boot();

        $this->assertSame(['auth'], config('octane.warm'));
    }

    public function test_nothing_changes_without_octane(): void
    {
        (new LaragraphServiceProvider($this->app))->boot();

        $this->assertNull(config('octane'));
    }
}
