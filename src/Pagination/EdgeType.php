<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Pagination;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Relay-spec cursor pagination — Edge type wrapper.
 *
 * Wraps each node with its opaque cursor.
 *
 * Usage:
 *
 *   new EdgeType('UserEdge', app('laragraph')->type('User'))
 */
class EdgeType extends ObjectType
{
    public function __construct(string $name, Type $nodeType)
    {
        parent::__construct([
            'name'        => $name,
            'description' => "An edge in a {$name} connection.",
            'fields'      => [
                'node' => [
                    'type'        => $nodeType,
                    'description' => 'The item at the end of the edge.',
                ],
                'cursor' => [
                    'type'        => Type::nonNull(Type::string()),
                    'description' => 'An opaque cursor used for cursor-based pagination.',
                ],
            ],
        ]);
    }
}
