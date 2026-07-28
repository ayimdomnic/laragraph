<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Scalars\Database;

use Ayimdomnic\Laragraph\Scalars\JsonType;

/**
 * A scalar representing a PostgreSQL JSONB column.
 *
 * Behaves identically to the `JSON` scalar but is exposed under the `JSONB`
 * type name so schema authors can distinguish the storage-layer distinction
 * (binary JSON with indexing support) from generic JSON.
 *
 * Compatible with: PostgreSQL `jsonb`, CockroachDB `jsonb`.
 */
class JsonbType extends JsonType
{
    public string $name = 'JSONB';
    public ?string $description = 'A PostgreSQL JSONB value. Accepts any valid JSON structure (object, array, string, number, boolean, null).';
}
