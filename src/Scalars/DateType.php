<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Scalars;

use Ayimdomnic\Laragraph\Scalars\Concerns\ParsesDates;
use Ayimdomnic\Laragraph\Support\ScalarType;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;

/**
 * A calendar date in ISO-8601 format: `YYYY-MM-DD`.
 *
 * Input values are parsed into a `DateTimeImmutable` at midnight; impossible
 * dates (2024-02-31) are rejected rather than rolled over.
 */
class DateType extends ScalarType
{
    use ParsesDates;

    private const FORMAT = 'Y-m-d';

    public string $name = 'Date';

    public ?string $description = 'A date string in ISO-8601 format: YYYY-MM-DD';

    public function serialize(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(self::FORMAT);
        }

        if (is_string($value) && self::parseStrict($value, [self::FORMAT]) instanceof \DateTimeImmutable) {
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
            return self::parseStrict($value, [self::FORMAT])
                ?? throw new Error("Invalid Date value: {$value}. Expected a real date as YYYY-MM-DD.");
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
