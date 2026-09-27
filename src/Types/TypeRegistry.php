<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Types;

use Ayimdomnic\Laragraph\Contracts\TypeRegistryInterface;
use Ayimdomnic\Laragraph\Laragraph;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\PhpEnumType;
use GraphQL\Type\Definition\Type;
use Illuminate\Contracts\Container\Container;

/**
 * Registers and resolves GraphQL type classes by alias/name.
 *
 * Extracted from the type-registry responsibilities {@see Laragraph}
 * used to own directly.
 */
final class TypeRegistry implements TypeRegistryInterface
{
    /** @var array<string, string> Type class map: alias => FQCN. */
    private array $types = [];

    /** @var array<string, Type> Resolved type instances keyed by alias. */
    private array $typesInstances = [];

    public function __construct(private readonly Container $container) {}

    public function addType(string|Type $class, ?string $alias = null): string
    {
        if ($class instanceof Type) {
            if ($alias === null) {
                if (!$class instanceof NamedType) {
                    throw new \InvalidArgumentException('An alias is required when registering a type that is not a NamedType.');
                }

                $alias = $class->name();
            }

            $this->typesInstances[$alias] = $class;

            return $alias;
        }

        $alias ??= $this->resolveTypeName($class);
        $this->types[$alias] = $class;
        unset($this->typesInstances[$alias]); // invalidate cached instance

        return $alias;
    }

    public function type(string $name, bool $fresh = false): Type
    {
        if (!$fresh && isset($this->typesInstances[$name])) {
            return $this->typesInstances[$name];
        }

        if (!isset($this->types[$name])) {
            throw new \InvalidArgumentException(
                "Type [{$name}] is not registered. Add it to laragraph.types in your config.",
            );
        }

        $class = $this->types[$name];
        $type  = enum_exists($class)
            ? new PhpEnumType($class, $name)
            : $this->container->make($class);

        $this->typesInstances[$name] = $type;

        return $type;
    }

    /**
     * Aliases usually match the GraphQL name, but need not (e.g. an alias of
     * `UserInput` for an input type named `CreateUserInput`). The schema's
     * type loader is always asked by GraphQL name, so it goes through here.
     */
    public function typeByName(string $graphqlName): ?Type
    {
        if ($this->hasType($graphqlName)) {
            $type = $this->type($graphqlName);

            if ($type instanceof NamedType && $type->name() === $graphqlName) {
                return $type;
            }
        }

        foreach (array_unique([...array_keys($this->types), ...array_keys($this->typesInstances)]) as $alias) {
            $type = $this->type((string) $alias);

            if ($type instanceof NamedType && $type->name() === $graphqlName) {
                return $type;
            }
        }

        return null;
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    public function hasType(string $name): bool
    {
        return isset($this->types[$name]) || isset($this->typesInstances[$name]);
    }

    private function resolveTypeName(string $class): string
    {
        // Native PHP enums are exposed under their short class name.
        if (enum_exists($class)) {
            return class_basename($class);
        }

        // Try to get the name without instantiating (cheaper)
        if (defined("{$class}::NAME")) {
            return $class::NAME;
        }

        $instance = $this->container->make($class);

        if (property_exists($instance, 'name')) {
            return $instance->name;
        }

        return class_basename($class);
    }
}
