<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ScalarType as GraphQLScalarType;

/**
 * Base class for custom GraphQL Scalar Types.
 *
 * Usage:
 *
 *   class EmailType extends ScalarType
 *   {
 *       public string $name = 'Email';
 *       public ?string $description = 'An RFC-5321 compliant email address.';
 *
 *       public function serialize(mixed $value): string
 *       {
 *           return (string) $value;
 *       }
 *
 *       public function parseValue(mixed $value): string
 *       {
 *           if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
 *               throw new \GraphQL\Error\Error("Invalid email address: {$value}");
 *           }
 *           return $value;
 *       }
 *
 *       public function parseLiteral(Node $valueNode, ?array $variables = null): string
 *       {
 *           return $this->parseValue($valueNode->value);
 *       }
 *   }
 */
abstract class ScalarType extends GraphQLScalarType
{
    // All abstract methods (serialize, parseValue, parseLiteral) are inherited
    // from GraphQL\Type\Definition\ScalarType. Override them in subclasses.
}
