<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Pagination;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Relay-spec cursor pagination — Connection type.
 *
 * A Connection wraps a paginated list with edges + pageInfo.
 *
 * Usage (inside a Query):
 *
 *   public function type(): Type
 *   {
 *       return new ConnectionType('UserConnection', app('laragraph')->type('User'));
 *   }
 *
 * Connection args (add to your Query::args()):
 *
 *   ConnectionType::args()
 *
 * Resolving (inside Query::resolve()):
 *
 *   return ConnectionType::paginate(
 *       User::query(),
 *       $args,
 *   );
 */
class ConnectionType extends ObjectType
{
    public function __construct(string $name, Type $nodeType)
    {
        $edgeType    = new EdgeType("{$name}Edge", $nodeType);
        $pageInfo    = new PageInfoType();

        parent::__construct([
            'name'        => $name,
            'description' => "A paginated list of {$name} edges.",
            'fields'      => [
                'edges' => [
                    'type'        => Type::listOf($edgeType),
                    'description' => 'A list of edges.',
                ],
                'pageInfo' => [
                    'type'        => Type::nonNull($pageInfo),
                    'description' => 'Pagination context.',
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers — call these from your query resolvers
    // -------------------------------------------------------------------------

    /**
     * Standard cursor-pagination arguments to add to a query's args().
     *
     * @return array<string, array{type: \GraphQL\Type\Definition\Type, description: string}>
     */
    public static function args(): array
    {
        return [
            'first'  => ['type' => Type::int(), 'description' => 'Return the first N items.'],
            'after'  => ['type' => Type::string(), 'description' => 'Return items after this cursor.'],
            'last'   => ['type' => Type::int(), 'description' => 'Return the last N items.'],
            'before' => ['type' => Type::string(), 'description' => 'Return items before this cursor.'],
        ];
    }

    /**
     * Paginate an Eloquent builder using cursor (offset-encoded) pagination
     * and return a Connection-shaped array.
     *
     * @param  object  $query
     * @param  array<string, mixed>  $args
     * @return array{edges: array<int, array{node: mixed, cursor: string}>, pageInfo: array<string, mixed>}
     */
    public static function paginate(object $query, array $args): array
    {
        $perPage = (int) ($args['first'] ?? $args['last'] ?? config('laragraph.pagination.per_page', 15));
        $page    = 1;

        if (!empty($args['after'])) {
            $page = self::decodeCursor($args['after']) + 1;
        } elseif (!empty($args['before'])) {
            $page = max(1, self::decodeCursor($args['before']) - 1);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $items     = $paginator->items();
        $total     = $paginator->total();
        $offset    = ($page - 1) * $perPage;

        $edges = array_map(
            fn (mixed $item, int $index) => [
                'node'   => $item,
                'cursor' => self::encodeCursor($offset + $index + 1),
            ],
            $items,
            array_keys($items),
        );

        return [
            'edges'    => $edges,
            'pageInfo' => [
                'hasNextPage'     => $paginator->hasMorePages(),
                'hasPreviousPage' => $page > 1,
                'startCursor'     => !empty($edges) ? $edges[0]['cursor'] : null,
                'endCursor'       => !empty($edges) ? $edges[array_key_last($edges)]['cursor'] : null,
                'total'           => $total,
            ],
        ];
    }

    /**
     * Offset-based pagination helper for simpler use-cases.
     *
     * Returns the standard simple paginator format.
     *
     * @param  object  $query
     * @param  array<string, mixed>  $args
     * @return array{data: array<mixed>, total: int, per_page: int, current_page: int, last_page: int, has_more_pages: bool}
     */
    public static function simplePaginate(object $query, array $args): array
    {
        $perPage = (int) ($args['per_page'] ?? config('laragraph.pagination.per_page', 15));
        $page    = (int) ($args['page'] ?? 1);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data'          => $paginator->items(),
            'total'         => $paginator->total(),
            'per_page'      => $paginator->perPage(),
            'current_page'  => $paginator->currentPage(),
            'last_page'     => $paginator->lastPage(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    // -------------------------------------------------------------------------
    // Cursor encoding/decoding
    // -------------------------------------------------------------------------

    public static function encodeCursor(int $offset): string
    {
        return base64_encode('cursor:' . $offset);
    }

    public static function decodeCursor(string $cursor): int
    {
        $decoded = base64_decode($cursor, true);
        if ($decoded === false || !str_starts_with($decoded, 'cursor:')) {
            return 0;
        }
        return (int) substr($decoded, 7);
    }
}
