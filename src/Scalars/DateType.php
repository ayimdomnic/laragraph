<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Scalars;

use Ayimdomnic\Laragraph\Support\ScalarType;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;

/**
 * A scalar representing an ISO-8601 date string (no time component).
 *
 * Input: "2024-01-15"
 */
class DateType extends ScalarType
{
    public string $name = 'Date';
    public ?string $description = 'A date string in ISO-8601 format: YYYY-MM-DD';

    public function serialize(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        throw new Error('Date cannot represent value: ' . json_encode($value));
    }

    public function parseValue(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($value);
        }

        if (is_string($value)) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
            if ($dt === false) {
                throw new Error("Invalid Date value: {$value}. Expected YYYY-MM-DD.");
            }
            return $dt;
        }

        throw new Error('Date must be a string.');
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): \DateTimeImmutable
    {
        if (!$valueNode instanceof StringValueNode) {
            throw new Error('Date literal must be a string.');
        }

        return $this->parseValue($valueNode->value);
    }
}
