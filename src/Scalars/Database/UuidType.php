<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Scalars\Database;

use Ayimdomnic\Laragraph\Support\ScalarType;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;

/**
 * A scalar representing an RFC 4122 UUID.
 *
 * Accepts all UUID variants (v1–v5) in the canonical hyphenated lowercase form.
 * The value is normalised to lowercase on both input and output.
 *
 * Compatible with: PostgreSQL `uuid`, MSSQL `uniqueidentifier`, CockroachDB `uuid`.
 */
class UuidType extends ScalarType
{
    public string $name = 'UUID';
    public ?string $description = 'An RFC 4122 UUID string (e.g. "550e8400-e29b-41d4-a716-446655440000").';

    /** Matches UUID v1–v5 in hyphenated form (case-insensitive). */
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function serialize(mixed $value): string
    {
        if (!is_string($value)) {
            throw new Error('UUID cannot represent non-string value: ' . json_encode($value));
        }

        return strtolower($value);
    }

    public function parseValue(mixed $value): string
    {
        if (!is_string($value) || !preg_match(self::PATTERN, $value)) {
            throw new Error('UUID must be a valid RFC 4122 UUID, got: ' . json_encode($value));
        }

        return strtolower($value);
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        if (!$valueNode instanceof StringValueNode) {
            throw new Error('UUID must be provided as a string literal.');
        }

        return $this->parseValue($valueNode->value);
    }
}
