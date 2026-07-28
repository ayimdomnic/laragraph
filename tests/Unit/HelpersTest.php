<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit;

use Ayimdomnic\Laragraph\Helpers;
use Ayimdomnic\Laragraph\Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_apply_each_with_scalar(): void
    {
        $result = Helpers::applyEach(fn ($v) => $v * 2, 5);
        $this->assertSame(10, $result);
    }

    public function test_apply_each_with_array(): void
    {
        $result = Helpers::applyEach(fn ($v) => $v . '!', ['a', 'b', 'c']);
        $this->assertSame(['a!', 'b!', 'c!'], $result);
    }

    public function test_apply_each_with_traversable(): void
    {
        $gen = (static function () {
            yield 'x' => 1;
            yield 'y' => 2;
        })();

        $result = Helpers::applyEach(fn ($v) => $v + 10, $gen);

        $this->assertSame(['x' => 11, 'y' => 12], $result);
    }

    public function test_apply_each_with_empty_array(): void
    {
        $result = Helpers::applyEach(fn ($v) => $v, []);
        $this->assertSame([], $result);
    }
}
