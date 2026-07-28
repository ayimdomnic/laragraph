<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Scalars;

use Ayimdomnic\Laragraph\Support\ScalarType;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;

/**
 * A scalar representing an ISO-8601 datetime string.
 *
 * Input: "2024-01-15T09:30:00Z"
 * Output: Carbon instance (or string if no Carbon available)
 */
class DateTimeType extends ScalarType
{
    public string $name = 'DateTime';
    public ?string $description = 'A datetime string in ISO-8601 format: YYYY-MM-DDTHH:mm:ssZ';

    public function serialize(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (is_string($value)) {
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
            $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value)
               ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value)
               ?: \DateTimeImmutable::createFromFormat('Y-m-d', $value);

            if ($dt === false) {
                throw new Error("Invalid DateTime value: {$value}");
            }

            return $dt;
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
