<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Scalars\Database;

use Ayimdomnic\Laragraph\Support\ScalarType;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;

/**
 * A scalar representing a duration interval in ISO 8601 format.
 *
 * Examples: `"P1Y2M3DT4H5M6S"`, `"PT30M"`, `"P1DT12H"`.
 *
 * PHP `\DateInterval` objects are automatically serialised to ISO 8601.
 * Plain strings are passed through as-is so PostgreSQL-style intervals
 * (e.g. `"1 year 2 months 3 days"`) are also accepted on output.
 *
 * Compatible with: PostgreSQL `interval`, Oracle `INTERVAL DAY TO SECOND`.
 */
class IntervalType extends ScalarType
{
    public string $name = 'Interval';
    public ?string $description = 'An ISO 8601 duration interval (e.g. "P1Y2M3DT4H5M6S", "PT30M").';

    public function serialize(mixed $value): string
    {
        if ($value instanceof \DateInterval) {
            return $this->dateIntervalToIso($value);
        }

        if (!is_string($value)) {
            throw new Error('Interval cannot represent value: ' . json_encode($value));
        }

        return $value;
    }

    public function parseValue(mixed $value): string
    {
        if (!is_string($value)) {
            throw new Error('Interval must be a string, got: ' . gettype($value));
        }

        return $value;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        if (!$valueNode instanceof StringValueNode) {
            throw new Error('Interval must be provided as a string literal.');
        }

        return $this->parseValue($valueNode->value);
    }

    private function dateIntervalToIso(\DateInterval $interval): string
    {
        $date = '';
        if ($interval->y !== 0) {
            $date .= $interval->y . 'Y';
        }
        if ($interval->m !== 0) {
            $date .= $interval->m . 'M';
        }
        if ($interval->d !== 0) {
            $date .= $interval->d . 'D';
        }

        $time = '';
        if ($interval->h !== 0) {
            $time .= $interval->h . 'H';
        }
        if ($interval->i !== 0) {
            $time .= $interval->i . 'M';
        }
        if ($interval->s !== 0) {
            $time .= $interval->s . 'S';
        }

        if ($time !== '') {
            return 'P' . $date . 'T' . $time;
        }

        return ($date !== '') ? 'P' . $date : 'P0D';
    }
}
