<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Contracts;

use Ayimdomnic\Laragraph\Schema\SchemaBuilder;
use GraphQL\Type\Schema;

/**
 * Compiles a Laragraph schema configuration array into a GraphQL\Type\Schema.
 *
 * @see SchemaBuilder the built-in implementation.
 */
interface SchemaBuilderInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function build(array $config): Schema;
}
