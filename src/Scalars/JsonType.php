<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Scalars;

use Ayimdomnic\Laragraph\Support\ScalarType;
use GraphQL\Error\Error;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\StringValueNode;

/**
 * A scalar that represents an arbitrary JSON value.
 *
 * Accepts any valid JSON: objects, arrays, strings, numbers, booleans, null.
 */
class JsonType extends ScalarType
{
    public string $name = 'JSON';
    public ?string $description = 'An arbitrary JSON value, including objects, arrays, scalars, and null.';

    public function serialize(mixed $value): mixed
    {
        return $value;
    }

    public function parseValue(mixed $value): mixed
    {
        return $value;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): mixed
    {
        return $this->parseLiteralNode($valueNode);
    }

    private function parseLiteralNode(Node $node): mixed
    {
        return match (true) {
            $node instanceof StringValueNode,
            $node instanceof BooleanValueNode => $node->value,
            $node instanceof IntValueNode    => (int) $node->value,
            $node instanceof FloatValueNode  => (float) $node->value,
            $node instanceof NullValueNode   => null,
            $node instanceof ListValueNode   => array_map(
                fn (Node $item) => $this->parseLiteralNode($item),
                iterator_to_array($node->values),
            ),
            $node instanceof ObjectValueNode => array_reduce(
                iterator_to_array($node->fields),
                function (array $carry, Node $field): array {
                    $carry[$field->name->value] = $this->parseLiteralNode($field->value);
                    return $carry;
                },
                [],
            ),
            default => throw new Error('Cannot parse JSON literal of type ' . $node::class),
        };
    }
}
