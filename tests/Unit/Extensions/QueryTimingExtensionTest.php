<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Extensions;

use Ayimdomnic\Laragraph\Extensions\QueryTimingExtension;
use Ayimdomnic\Laragraph\Tests\TestCase;

class QueryTimingExtensionTest extends TestCase
{
    public function test_key_returns_timing(): void
    {
        $this->assertSame('timing', (new QueryTimingExtension())->key());
    }

    public function test_get_returns_execution_ms_from_context(): void
    {
        $result = (new QueryTimingExtension())->get(['execution_ms' => 42.5]);

        $this->assertSame(['execution_ms' => 42.5], $result);
    }

    public function test_get_returns_zero_when_context_is_empty(): void
    {
        $result = (new QueryTimingExtension())->get();

        $this->assertSame(['execution_ms' => 0.0], $result);
    }

    public function test_get_preserves_float_precision(): void
    {
        $result = (new QueryTimingExtension())->get(['execution_ms' => 1.23]);

        $this->assertSame(1.23, $result['execution_ms']);
    }
}
