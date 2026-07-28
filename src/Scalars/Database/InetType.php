<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Scalars\Database;

use Ayimdomnic\Laragraph\Support\ScalarType;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;

/**
 * A scalar representing an IPv4 or IPv6 host address, with an optional CIDR
 * prefix length (e.g. `"192.168.0.0/24"`, `"::1"`, `"10.0.0.1"`).
 *
 * Input is validated via PHP's `FILTER_VALIDATE_IP`.  CIDR notation is
 * supported: the prefix is stripped before validation of the host part.
 *
 * Compatible with: PostgreSQL `inet`/`cidr`, CockroachDB `inet`.
 */
class InetType extends ScalarType
{
    public string $name = 'Inet';
    public ?string $description = 'An IPv4 or IPv6 address with optional CIDR prefix (e.g. "192.168.0.0/24", "::1").';

    public function serialize(mixed $value): string
    {
        if (!is_string($value)) {
            throw new Error('Inet cannot represent non-string value: ' . json_encode($value));
        }

        return $value;
    }

    public function parseValue(mixed $value): string
    {
        if (!is_string($value)) {
            throw new Error('Inet must be a string, got: ' . gettype($value));
        }

        $host = strpos($value, '/') !== false
            ? explode('/', $value, 2)[0]
            : $value;

        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            throw new Error("Inet is not a valid IP address: {$value}");
        }

        return $value;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        if (!$valueNode instanceof StringValueNode) {
            throw new Error('Inet must be provided as a string literal.');
        }

        return $this->parseValue($valueNode->value);
    }
}
