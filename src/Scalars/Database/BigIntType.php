<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Scalars\Database;

use Ayimdomnic\Laragraph\Support\ScalarType;
use GraphQL\Error\Error;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;

/**
 * A scalar representing a 64-bit integer, serialised as a string.
 *
 * JavaScript can only safely represent integers up to 2^53 − 1.  Database IDs
 * (e.g. PostgreSQL `bigserial`, MSSQL `bigint`) can exceed that limit, so this
 * scalar always transports the value as a decimal string.
 *
 * Compatible with: PostgreSQL `bigint`/`bigserial`, MSSQL `bigint`,
 *                  Oracle `NUMBER(19)`, CockroachDB `int8`.
 */
class BigIntType extends ScalarType
{
    public string $name = 'BigInt';
    public ?string $description = 'A 64-bit integer represented as a decimal string to avoid JavaScript precision loss (e.g. "9007199254740993").';

    public function serialize(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value) && $this->isIntegerString($value)) {
            return $value;
        }

        throw new Error('BigInt cannot represent value: ' . json_encode($value));
    }

    public function parseValue(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value) && $this->isIntegerString($value)) {
            return $value;
        }

        throw new Error('BigInt must be an integer or integer string, got: ' . json_encode($value));
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        if ($valueNode instanceof IntValueNode) {
            return $valueNode->value;
        }

        if ($valueNode instanceof StringValueNode) {
            return $this->parseValue($valueNode->value);
        }

        throw new Error('BigInt must be provided as an integer literal or string.');
    }

    private function isIntegerString(string $value): bool
    {
        return $value !== '' && ctype_digit(ltrim($value, '-'));
    }
}
