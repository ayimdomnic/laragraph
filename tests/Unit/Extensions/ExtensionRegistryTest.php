<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Extensions;

use Ayimdomnic\Laragraph\Extensions\ExtensionRegistry;
use Ayimdomnic\Laragraph\Extensions\GraphQLExtensionInterface;
use Ayimdomnic\Laragraph\Tests\TestCase;

// ---------------------------------------------------------------------------
// Stub extensions
// ---------------------------------------------------------------------------

class FixedExtension implements GraphQLExtensionInterface
{
    public function __construct(
        private readonly string $key,
        private readonly array $data,
    ) {}

    public function key(): string { return $this->key; }
    public function get(array $context = []): array { return $this->data; }
}

class ContextEchoExtension implements GraphQLExtensionInterface
{
    public function key(): string { return 'echo'; }
    public function get(array $context = []): array { return $context; }
}

class EmptyExtension implements GraphQLExtensionInterface
{
    public function key(): string { return 'empty'; }
    public function get(array $context = []): array { return []; }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class ExtensionRegistryTest extends TestCase
{
    public function test_new_registry_is_empty(): void
    {
        $registry = new ExtensionRegistry();

        $this->assertTrue($registry->isEmpty());
        $this->assertSame([], $registry->all());
    }

    public function test_add_registers_extension(): void
    {
        $registry = new ExtensionRegistry();
        $ext      = new FixedExtension('a', ['x' => 1]);

        $registry->add($ext);

        $this->assertFalse($registry->isEmpty());
        $this->assertCount(1, $registry->all());
        $this->assertSame($ext, $registry->all()[0]);
    }

    public function test_multiple_extensions_are_stored_in_order(): void
    {
        $registry = new ExtensionRegistry();
        $e1 = new FixedExtension('first', []);
        $e2 = new FixedExtension('second', []);

        $registry->add($e1);
        $registry->add($e2);

        $this->assertSame([$e1, $e2], $registry->all());
    }

    public function test_collect_returns_keyed_extension_data(): void
    {
        $registry = new ExtensionRegistry();
        $registry->add(new FixedExtension('meta', ['version' => '1.0']));
        $registry->add(new FixedExtension('feature', ['enabled' => true]));

        $collected = $registry->collect();

        $this->assertSame(['version' => '1.0'], $collected['meta']);
        $this->assertSame(['enabled' => true], $collected['feature']);
    }

    public function test_collect_passes_context_to_each_extension(): void
    {
        $registry = new ExtensionRegistry();
        $registry->add(new ContextEchoExtension());

        $ctx       = ['execution_ms' => 42.5];
        $collected = $registry->collect($ctx);

        $this->assertSame($ctx, $collected['echo']);
    }

    public function test_collect_skips_extensions_returning_empty_array(): void
    {
        $registry = new ExtensionRegistry();
        $registry->add(new EmptyExtension());
        $registry->add(new FixedExtension('kept', ['ok' => true]));

        $collected = $registry->collect();

        $this->assertArrayNotHasKey('empty', $collected);
        $this->assertArrayHasKey('kept', $collected);
    }

    public function test_collect_on_empty_registry_returns_empty_array(): void
    {
        $registry = new ExtensionRegistry();

        $this->assertSame([], $registry->collect());
        $this->assertSame([], $registry->collect(['execution_ms' => 1.0]));
    }

    public function test_is_empty_returns_false_after_adding_extension(): void
    {
        $registry = new ExtensionRegistry();
        $registry->add(new FixedExtension('x', []));

        $this->assertFalse($registry->isEmpty());
    }
}
