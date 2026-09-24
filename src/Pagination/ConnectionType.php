<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Pagination;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;

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
    /** @var array<string, self> Connections by name and node type instance. */
    private static array $instances = [];

    /**
     * A connection type, reused when several fields return the same
     * connection (a schema may contain only one type per name).
     *
     *   public function type(): Type
     *   {
     *       return ConnectionType::make('PostConnection', Laragraph::type('Post'));
     *   }
     */
    public static function make(string $name, Type $nodeType): self
    {
        return self::$instances[$name . '#' . spl_object_id($nodeType)] ??= new self($name, $nodeType);
    }

    public function __construct(string $name, Type $nodeType)
    {
        $edgeType    = new EdgeType("{$name}Edge", $nodeType);
        $pageInfo    = PageInfoType::instance();

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
     * @return array<string, array{type: Type, description: string}>
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
     * Relay cursor pagination.
     *
     * Cursors identify an item's 1-based position in the full result set, and
     * the window follows the Relay spec: `after`/`before` narrow the list,
     * then `first` keeps the leading and `last` the trailing N items. Page
     * sizes are capped at `laragraph.pagination.max_per_page`.
     *
     * Eloquent builders, query builders and relations are paged with exact
     * offsets. Any other object exposing Laravel's `paginate()` signature is
     * supported for page-aligned windows (a constant `first`, moving forward).
     *
     * @param  array<string, mixed>  $args
     * @return array{edges: list<array{node: mixed, cursor: string}>, pageInfo: array{hasNextPage: bool, hasPreviousPage: bool, startCursor: string|null, endCursor: string|null, total: int}}
     *
     * @throws Error When `first` or `last` is negative.
     */
    public static function paginate(object $query, array $args): array
    {
        $first = self::limit($args, 'first');
        $last  = self::limit($args, 'last');

        if ($first === null && $last === null) {
            $first = self::clamp((int) config('laragraph.pagination.per_page', 15));
        }

        $after  = empty($args['after']) ? 0 : self::decodeCursor((string) $args['after']);
        $before = empty($args['before']) ? null : self::decodeCursor((string) $args['before']);

        if ($query instanceof EloquentBuilder || $query instanceof QueryBuilder || $query instanceof Relation) {
            $total = (clone $query)->count();
            $start = $after;
            $end   = $before === null ? $total : min($total, max(0, $before - 1));

            if ($first !== null) {
                $end = min($end, $start + $first);
            }

            if ($last !== null) {
                $start = max($start, $end - $last);
            }

            $limit = max(0, $end - $start);
            $items = $limit === 0 ? [] : (clone $query)->skip($start)->take($limit)->get()->all();
        } else {
            // paginate()-only objects: translate the window to a page of $size items.
            $size      = max(1, $first ?? $last);
            $start     = $before !== null && $first === null ? max(0, $before - 1 - $size) : $after;
            $paginator = self::paginator($query, $size, intdiv($start, $size) + 1);
            $items     = $paginator->items();
            $total     = $paginator->total();
            $start     = ($paginator->currentPage() - 1) * $size;
            $end       = $start + count($items);
        }

        $edges = [];
        foreach (array_values($items) as $index => $item) {
            $edges[] = [
                'node'   => $item,
                'cursor' => self::encodeCursor($start + $index + 1),
            ];
        }

        return [
            'edges'    => $edges,
            'pageInfo' => [
                'hasNextPage'     => $end < $total,
                'hasPreviousPage' => $start > 0,
                'startCursor'     => $edges !== [] ? $edges[0]['cursor'] : null,
                'endCursor'       => $edges !== [] ? $edges[array_key_last($edges)]['cursor'] : null,
                'total'           => $total,
            ],
        ];
    }

    /**
     * Offset-based pagination helper for simpler use-cases.
     *
     * Returns the standard simple paginator format.
     *
     * @param  array<string, mixed>  $args
     * @return array{data: array<mixed>, total: int, per_page: int, current_page: int, last_page: int, has_more_pages: bool}
     */
    public static function simplePaginate(object $query, array $args): array
    {
        $perPage = self::clamp((int) ($args['per_page'] ?? config('laragraph.pagination.per_page', 15)));
        $page    = max(1, (int) ($args['page'] ?? 1));

        $paginator = self::paginator($query, $perPage, $page);

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

    /**
     * @param array<string, mixed> $args
     *
     * @throws Error
     */
    private static function limit(array $args, string $name): ?int
    {
        if (!isset($args[$name])) {
            return null;
        }

        $value = (int) $args[$name];

        if ($value < 0) {
            throw new Error("`{$name}` must not be negative.");
        }

        return self::clamp($value);
    }

    /**
     * Cap a page size at `laragraph.pagination.max_per_page` (null: no cap).
     */
    private static function clamp(int $size): int
    {
        $max = config('laragraph.pagination.max_per_page', 100);

        return $max === null ? max(0, $size) : max(0, min($size, (int) $max));
    }

    /**
     * Run `paginate()` on an Eloquent/query builder, relation or anything
     * else exposing Laravel's paginate() signature.
     *
     * @return LengthAwarePaginator<array-key, mixed>
     */
    private static function paginator(object $query, int $perPage, int $page): LengthAwarePaginator
    {
        if (!method_exists($query, 'paginate')) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot paginate an instance of %s: it has no paginate() method.',
                $query::class,
            ));
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        if (!$paginator instanceof LengthAwarePaginator) {
            throw new \UnexpectedValueException(sprintf(
                '%s::paginate() must return a %s.',
                $query::class,
                LengthAwarePaginator::class,
            ));
        }

        return $paginator;
    }
}
