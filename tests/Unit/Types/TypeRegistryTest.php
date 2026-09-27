<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Types;

use Ayimdomnic\Laragraph\Tests\TestCase;
use Ayimdomnic\Laragraph\Types\TypeRegistry;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;

class NamedFixtureType extends ScalarType
{
    public string $name = 'NamedFixture';

    public function serialize(mixed $value): mixed
    {
        return $value;
    }

    public function parseValue(mixed $value): mixed
    {
        return $value;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): mixed
    {
        return null;
    }
}

class ConstantNamedFixture
{
    public const NAME = 'ConstantNamed';
}

class NoNameFixture
{
    // Deliberately no NAME constant and no $name property.
}

class TypeRegistryTest extends TestCase
{
    private function registry(): TypeRegistry
    {
        return new TypeRegistry($this->app);
    }

    public function test_add_type_by_fqcn_resolves_alias_from_name_constant(): void
    {
        $registry = $this->registry();

        $alias = $registry->addType(ConstantNamedFixture::class);

        $this->assertSame('ConstantNamed', $alias);
        $this->assertTrue($registry->hasType('ConstantNamed'));
    }

    public function test_add_type_by_fqcn_falls_back_to_class_basename(): void
    {
        $registry = $this->registry();

        $alias = $registry->addType(NoNameFixture::class);

        $this->assertSame('NoNameFixture', $alias);
    }

    public function test_add_type_with_explicit_alias(): void
    {
        $registry = $this->registry();

        $alias = $registry->addType(ConstantNamedFixture::class, 'MyAlias');

        $this->assertSame('MyAlias', $alias);
        $this->assertTrue($registry->hasType('MyAlias'));
        $this->assertFalse($registry->hasType('ConstantNamed'));
    }

    public function test_add_type_instance_stores_by_its_own_name(): void
    {
        $registry = $this->registry();
        $instance = new NamedFixtureType();

        $alias = $registry->addType($instance);

        $this->assertSame('NamedFixture', $alias);
        $this->assertSame($instance, $registry->type('NamedFixture'));
    }

    public function test_add_type_instance_requires_alias_for_non_named_type(): void
    {
        $registry = $this->registry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('An alias is required');

        $registry->addType(Type::listOf(Type::string()));
    }

    public function test_type_resolves_and_caches_the_instance(): void
    {
        $registry = $this->registry();
        $registry->addType(NamedFixtureType::class, 'Fixture');

        $first  = $registry->type('Fixture');
        $second = $registry->type('Fixture');

        $this->assertSame($first, $second);
    }

    public function test_type_fresh_bypasses_the_cache(): void
    {
        $registry = $this->registry();
        $registry->addType(NamedFixtureType::class, 'Fixture');

        $first  = $registry->type('Fixture');
        $second = $registry->type('Fixture', fresh: true);

        $this->assertNotSame($first, $second);
    }

    public function test_type_throws_for_unregistered_name(): void
    {
        $registry = $this->registry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not registered');

        $registry->type('Missing');
    }

    public function test_type_by_name_finds_a_type_registered_under_a_different_alias(): void
    {
        $registry = $this->registry();
        $registry->addType(NamedFixtureType::class, 'SomeAlias');

        $type = $registry->typeByName('NamedFixture');

        $this->assertNotNull($type);
        $this->assertSame('NamedFixture', $type->name());
    }

    public function test_type_by_name_returns_null_when_nothing_matches(): void
    {
        $registry = $this->registry();

        $this->assertNull($registry->typeByName('DoesNotExist'));
    }

    public function test_get_types_returns_the_alias_to_fqcn_map(): void
    {
        $registry = $this->registry();
        $registry->addType(ConstantNamedFixture::class, 'A');

        $this->assertSame(['A' => ConstantNamedFixture::class], $registry->getTypes());
    }

    public function test_re_registering_an_alias_invalidates_the_cached_instance(): void
    {
        $registry = $this->registry();
        $registry->addType(NamedFixtureType::class, 'Fixture');
        $first = $registry->type('Fixture');

        $registry->addType(NamedFixtureType::class, 'Fixture');
        $second = $registry->type('Fixture');

        $this->assertNotSame($first, $second);
    }
}
