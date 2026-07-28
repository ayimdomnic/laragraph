<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\DataLoader;

use Ayimdomnic\Laragraph\DataLoader\BatchResolver;
use Ayimdomnic\Laragraph\DataLoader\DataLoaderRegistry;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Overblog\DataLoader\DataLoader;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class StubBatchResolver extends BatchResolver
{
    public function batch(array $keys): array
    {
        return array_map(fn (mixed $k): string => "resolved:{$k}", $keys);
    }
}

class AnotherStubBatchResolver extends BatchResolver
{
    public function batch(array $keys): array
    {
        return array_map(fn (mixed $k): string => "other:{$k}", $keys);
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class DataLoaderRegistryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    public function test_get_returns_dataloader_instance(): void
    {
        $registry = new DataLoaderRegistry();
        $loader   = $registry->get(StubBatchResolver::class);

        $this->assertInstanceOf(DataLoader::class, $loader);
    }

    public function test_get_returns_same_instance_for_same_class(): void
    {
        $registry = new DataLoaderRegistry();

        $a = $registry->get(StubBatchResolver::class);
        $b = $registry->get(StubBatchResolver::class);

        $this->assertSame($a, $b);
    }

    public function test_get_returns_different_instances_for_different_classes(): void
    {
        $registry = new DataLoaderRegistry();

        $a = $registry->get(StubBatchResolver::class);
        $b = $registry->get(AnotherStubBatchResolver::class);

        $this->assertNotSame($a, $b);
    }

    public function test_batch_closure_is_invoked_when_key_is_loaded(): void
    {
        $registry = new DataLoaderRegistry();
        $loader   = $registry->get(StubBatchResolver::class);

        // load() enqueues the key; DataLoader::await() flushes the batch queue
        // and calls our closure: $resolver->batch(['hello']) → ['resolved:hello']
        $promise = $loader->load('hello');
        $result  = DataLoader::await($promise);

        $this->assertSame('resolved:hello', $result);
    }

    // -------------------------------------------------------------------------
    // clear()
    // -------------------------------------------------------------------------

    public function test_clear_causes_new_instance_to_be_created(): void
    {
        $registry = new DataLoaderRegistry();

        $before = $registry->get(StubBatchResolver::class);
        $registry->clear();
        $after = $registry->get(StubBatchResolver::class);

        // After clear(), the registry creates a fresh DataLoader on next get()
        $this->assertNotSame($before, $after);
    }

    public function test_clear_removes_all_loader_entries(): void
    {
        $registry = new DataLoaderRegistry();

        // Pre-populate two loaders
        $registry->get(StubBatchResolver::class);
        $registry->get(AnotherStubBatchResolver::class);

        $registry->clear();

        // Both return new instances after clear
        $stub    = $registry->get(StubBatchResolver::class);
        $another = $registry->get(AnotherStubBatchResolver::class);

        $this->assertInstanceOf(DataLoader::class, $stub);
        $this->assertInstanceOf(DataLoader::class, $another);
    }
}
