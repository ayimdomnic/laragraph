<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Scalars;

use Ayimdomnic\Laragraph\Scalars\Concerns\ParsesDates;
use Ayimdomnic\Laragraph\Support\ScalarType;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;

/**
 * An ISO-8601 date-time, serialized as `YYYY-MM-DDTHH:MM:SS±HH:MM`.
 *
 * Accepted input:
 *  - `2024-01-15T09:30:00Z` / `2024-01-15T09:30:00+02:00`
 *  - with fractional seconds, as JavaScript's `toISOString()` produces:
 *    `2024-01-15T09:30:00.000Z`
 *  - the SQL format `2024-01-15 09:30:00` (application timezone)
 *  - a plain date `2024-01-15` (midnight, application timezone)
 *
 * Impossible values (month 13, February 31st, …) are rejected rather than
 * rolled over.
 */
class DateTimeType extends ScalarType
{
    use ParsesDates;

    private const INPUT_FORMATS = [
        \DateTimeInterface::ATOM,  // Y-m-d\TH:i:sP (P also accepts "Z")
        'Y-m-d\TH:i:s.uP',
        'Y-m-d H:i:s',
        'Y-m-d',
    ];

    public string $name = 'DateTime';

    public ?string $description = 'A datetime string in ISO-8601 format: YYYY-MM-DDTHH:mm:ssZ';

    public function serialize(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        // Valid date-time strings are passed through unchanged; invalid ones are rejected.
        if (is_string($value) && self::parseStrict($value, self::INPUT_FORMATS) instanceof \DateTimeImmutable) {
            return $value;
        }

        throw new Error('DateTime cannot represent value: ' . json_encode($value));
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
            return self::parseStrict($value, self::INPUT_FORMATS)
                ?? throw new Error("Invalid DateTime value: {$value}");
        }

        throw new Error('DateTime must be a string.');
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): \DateTimeImmutable
    {
        if (!$valueNode instanceof StringValueNode) {
            throw new Error('DateTime literal must be a string.');
        }

        return $this->parseValue($valueNode->value);
    }
}
