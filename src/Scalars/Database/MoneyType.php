<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Scalars\Database;

use Ayimdomnic\Laragraph\Support\ScalarType;
use GraphQL\Error\Error;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;

/**
 * A scalar representing a monetary amount as a decimal string.
 *
 * Using a string preserves decimal precision that would otherwise be lost when
 * transporting values through JSON floats or JavaScript numbers.
 *
 * Accepts a sign-optional decimal: `[-]digits[.digits]` — e.g. `"1234.56"`,
 * `"-9.99"`, `"0"`.
 *
 * Compatible with: PostgreSQL `numeric`/`decimal`/`money`,
 *                  MSSQL `money`/`smallmoney`, Oracle `NUMBER`.
 */
class MoneyType extends ScalarType
{
    public string $name = 'Money';
    public ?string $description = 'A monetary amount as a decimal string preserving full precision (e.g. "1234.56", "-9.99").';

    private const PATTERN = '/^-?\d+(\.\d+)?$/';

    public function serialize(mixed $value): string
    {
        if (is_float($value)) {
            // Strip trailing zeros but keep at least the integer part
            return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
        }

        $str = (string) $value;

        if (!preg_match(self::PATTERN, $str)) {
            throw new Error('Money cannot represent value: ' . json_encode($value));
        }

        return $str;
    }

    public function parseValue(mixed $value): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new Error('Money must be a decimal number string, got: ' . gettype($value));
        }

        $str = (string) $value;

        if (!preg_match(self::PATTERN, $str)) {
            throw new Error('Money must be a decimal number string, got: ' . json_encode($value));
        }

        return $str;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        if (
            $valueNode instanceof StringValueNode
            || $valueNode instanceof IntValueNode
            || $valueNode instanceof FloatValueNode
        ) {
            return $this->parseValue($valueNode->value);
        }

        throw new Error('Money must be provided as a numeric literal or string.');
    }
}
