<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Schema;

use Ayimdomnic\Laragraph\Contracts\SchemaBuilderInterface;
use Ayimdomnic\Laragraph\Contracts\SchemaRegistryInterface;
use Ayimdomnic\Laragraph\Events\SchemaBuilt;
use Ayimdomnic\Laragraph\Exceptions\SchemaException;
use Ayimdomnic\Laragraph\Laragraph;
use GraphQL\Type\Schema;

/**
 * Resolves and caches compiled schemas by name.
 *
 * Extracted from the schema-resolution responsibility {@see Laragraph}
 * used to own directly.
 */
final class SchemaRegistry implements SchemaRegistryInterface
{
    /** @var array<string, Schema> Built schema cache keyed by schema name. */
    private array $schemas = [];

    public function __construct(private readonly SchemaBuilderInterface $builder) {}

    /**
     * @throws SchemaException
     */
    public function schema(?string $name = null): Schema
    {
        $name ??= config('laragraph.default_schema', 'default');

        if (isset($this->schemas[$name])) {
            return $this->schemas[$name];
        }

        $schemaConfig = config("laragraph.schemas.{$name}");

        if ($schemaConfig === null) {
            throw new SchemaException("Schema [{$name}] not found in laragraph configuration.");
        }

        // Merge global types into every schema build
        $schemaConfig['types'] = array_merge(
            config('laragraph.types', []),
            $schemaConfig['types'] ?? [],
        );

        $schema = $this->schemas[$name] = $this->builder->build($schemaConfig);
        event(new SchemaBuilt($name, $schema));

        return $schema;
    }
}
