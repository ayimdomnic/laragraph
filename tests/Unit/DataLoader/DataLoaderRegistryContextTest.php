<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\DataLoader;

use Ayimdomnic\Laragraph\DataLoader\DataLoaderRegistry;
use Ayimdomnic\Laragraph\Tests\TestCase;

#[\AllowDynamicProperties]
class DynamicContextFixture {}

class DynamicContextChildFixture extends DynamicContextFixture {}

class DeclaredContextFixture
{
    public ?DataLoaderRegistry $dataLoaders = null;
}

final class StrictContextFixture {}

class DataLoaderRegistryContextTest extends TestCase
{
    public function test_std_class_context_gets_the_property_and_is_resolvable(): void
    {
        $context  = new \stdClass();
        $registry = new DataLoaderRegistry();

        DataLoaderRegistry::attach($context, $registry);

        $this->assertSame($registry, $context->dataLoaders);
        $this->assertSame($registry, DataLoaderRegistry::for($context));
    }

    public function test_allow_dynamic_properties_is_honoured_including_inheritance(): void
    {
        $parent = new DynamicContextFixture();
        $child  = new DynamicContextChildFixture();

        DataLoaderRegistry::attach($parent, $parentRegistry = new DataLoaderRegistry());
        DataLoaderRegistry::attach($child, $childRegistry = new DataLoaderRegistry());

        $this->assertSame($parentRegistry, $parent->dataLoaders);
        $this->assertSame($childRegistry, $child->dataLoaders);
    }

    public function test_declared_property_is_populated(): void
    {
        $context = new DeclaredContextFixture();

        DataLoaderRegistry::attach($context, $registry = new DataLoaderRegistry());

        $this->assertSame($registry, $context->dataLoaders);
    }

    public function test_strict_objects_never_receive_a_dynamic_property(): void
    {
        $context = new StrictContextFixture();

        // Would throw under withoutDeprecationHandling() if a dynamic property were created.
        DataLoaderRegistry::attach($context, $registry = new DataLoaderRegistry());

        $this->assertFalse(property_exists($context, 'dataLoaders'));
        $this->assertSame($registry, DataLoaderRegistry::for($context));
    }

    public function test_class_capability_is_memoised(): void
    {
        DataLoaderRegistry::attach($first = new StrictContextFixture(), new DataLoaderRegistry());
        DataLoaderRegistry::attach($second = new StrictContextFixture(), $registry = new DataLoaderRegistry());

        $this->assertFalse(property_exists($first, 'dataLoaders'));
        $this->assertSame($registry, DataLoaderRegistry::for($second));
    }

    public function test_array_context_lookup(): void
    {
        $registry = new DataLoaderRegistry();

        $this->assertSame($registry, DataLoaderRegistry::for(['dataLoaders' => $registry]));
        $this->assertNull(DataLoaderRegistry::for(['dataLoaders' => 'nope']));
        $this->assertNull(DataLoaderRegistry::for([]));
    }

    public function test_unattached_and_scalar_contexts_resolve_to_null(): void
    {
        $this->assertNull(DataLoaderRegistry::for(new StrictContextFixture()));
        $this->assertNull(DataLoaderRegistry::for(42));
        $this->assertNull(DataLoaderRegistry::for(null));
    }
}
