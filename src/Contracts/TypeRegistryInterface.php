<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Contracts;

use Ayimdomnic\Laragraph\Types\TypeRegistry;
use GraphQL\Type\Definition\Type;

/**
 * Registers and resolves GraphQL type classes by alias/name.
 *
 * @see TypeRegistry the built-in implementation.
 */
interface TypeRegistryInterface
{
    /**
     * Register a type class (or instance) with an optional alias.
     *
     * @return string The alias the type was registered under.
     */
    public function addType(string|Type $class, ?string $alias = null): string;

    /**
     * Resolve a type instance by alias/name.
     *
     * @throws \InvalidArgumentException
     */
    public function type(string $name, bool $fresh = false): Type;

    /**
     * Resolve a registered type by its GraphQL name rather than its alias.
     */
    public function typeByName(string $graphqlName): ?Type;

    /**
     * Return all registered type class aliases.
     *
     * @return array<string, string>
     */
    public function getTypes(): array;

    /** Check whether a type name is registered. */
    public function hasType(string $name): bool;
}
