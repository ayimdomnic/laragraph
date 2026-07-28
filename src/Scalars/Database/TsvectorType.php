<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Scalars\Database;

use Ayimdomnic\Laragraph\Support\ScalarType;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;

/**
 * A scalar representing a PostgreSQL `tsvector` full-text search value.
 *
 * A tsvector is an opaque, sorted list of distinct lexemes with optional
 * position and weight information (e.g. `'cat':1 'fat':2 'sat':3`).
 * From GraphQL's perspective it is treated as an opaque string — conversion
 * to/from a real tsvector is handled by PostgreSQL when the value is stored
 * or retrieved.
 *
 * Compatible with: PostgreSQL `tsvector`.
 */
class TsvectorType extends ScalarType
{
    public string $name = 'TSVector';
    public ?string $description = "A PostgreSQL tsvector full-text-search value (e.g. \"'cat':1 'fat':2 'sat':3\").";

    public function serialize(mixed $value): string
    {
        if (!is_string($value)) {
            throw new Error('TSVector cannot represent non-string value: ' . json_encode($value));
        }

        return $value;
    }

    public function parseValue(mixed $value): string
    {
        if (!is_string($value)) {
            throw new Error('TSVector must be a string, got: ' . gettype($value));
        }

        return $value;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        if (!$valueNode instanceof StringValueNode) {
            throw new Error('TSVector must be provided as a string literal.');
        }

        return $valueNode->value;
    }
}
