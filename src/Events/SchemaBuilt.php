<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Events;

use GraphQL\Type\Schema;

/**
 * Fired once per schema name when a GraphQL schema is first compiled and
 * cached. Subsequent calls for the same name return the cached instance and
 * do **not** re-fire this event.
 *
 * Listen to this event to inspect, validate, or warm-up a just-built schema.
 */
final readonly class SchemaBuilt
{
    public function __construct(
        /** The name of the schema as defined in `laragraph.schemas`. */
        public string $schemaName,
        /** The compiled schema instance. */
        public Schema $schema,
    ) {}
}
