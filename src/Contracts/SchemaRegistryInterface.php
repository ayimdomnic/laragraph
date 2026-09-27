<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Contracts;

use Ayimdomnic\Laragraph\Exceptions\SchemaException;
use Ayimdomnic\Laragraph\Schema\SchemaRegistry;
use GraphQL\Type\Schema;

/**
 * Resolves and caches compiled schemas by name.
 *
 * @see SchemaRegistry the built-in implementation.
 */
interface SchemaRegistryInterface
{
    /**
     * Get (and cache) a compiled GraphQL Schema by name.
     *
     * @throws SchemaException
     */
    public function schema(?string $name = null): Schema;
}
