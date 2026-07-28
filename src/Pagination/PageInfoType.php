<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Pagination;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Relay-spec cursor pagination — PageInfo type.
 *
 * Returned inside every Connection type.
 */
class PageInfoType extends ObjectType
{
    public function __construct()
    {
        parent::__construct([
            'name'        => 'PageInfo',
            'description' => 'Pagination context from the cursor connection.',
            'fields'      => [
                'hasNextPage' => [
                    'type'        => Type::nonNull(Type::boolean()),
                    'description' => 'Whether there are more items after the current page.',
                ],
                'hasPreviousPage' => [
                    'type'        => Type::nonNull(Type::boolean()),
                    'description' => 'Whether there are more items before the current page.',
                ],
                'startCursor' => [
                    'type'        => Type::string(),
                    'description' => 'Cursor of the first item in the current page.',
                ],
                'endCursor' => [
                    'type'        => Type::string(),
                    'description' => 'Cursor of the last item in the current page.',
                ],
                'total' => [
                    'type'        => Type::int(),
                    'description' => 'Total number of items in the connection.',
                ],
            ],
        ]);
    }
}
