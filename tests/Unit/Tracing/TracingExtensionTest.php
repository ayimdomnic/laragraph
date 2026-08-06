<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Tracing;

use Ayimdomnic\Laragraph\Tests\TestCase;
use Ayimdomnic\Laragraph\Tracing\TracingCollector;
use Ayimdomnic\Laragraph\Tracing\TracingExtension;

class TracingExtensionTest extends TestCase
{
    public function test_key_is_tracing(): void
    {
        $ext = new TracingExtension(new TracingCollector());

        $this->assertSame('tracing', $ext->key());
    }

    public function test_get_returns_empty_array_when_collector_is_inactive(): void
    {
        $ext = new TracingExtension(new TracingCollector());

        $this->assertSame([], $ext->get());
    }

    public function test_get_returns_apollo_tracing_shape_when_active(): void
    {
        $collector = new TracingCollector();
        $collector->reset();

        $ext  = new TracingExtension($collector);
        $data = $ext->get();

        $this->assertSame(1, $data['version']);
        $this->assertIsString($data['startTime']);
        $this->assertIsString($data['endTime']);
        $this->assertIsInt($data['duration']);
        $this->assertSame([], $data['execution']['resolvers']);
    }
}
