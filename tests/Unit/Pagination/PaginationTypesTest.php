<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Pagination;

use Ayimdomnic\Laragraph\Pagination\ConnectionType;
use Ayimdomnic\Laragraph\Pagination\EdgeType;
use Ayimdomnic\Laragraph\Pagination\PageInfoType;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\Type;
use Illuminate\Pagination\LengthAwarePaginator;

class PaginationTypesTest extends TestCase
{
    // -------------------------------------------------------------------------
    // PageInfoType
    // -------------------------------------------------------------------------

    public function test_page_info_type_name(): void
    {
        $this->assertSame('PageInfo', (new PageInfoType())->name);
    }

    public function test_page_info_type_has_required_fields(): void
    {
        $fields = (new PageInfoType())->getFields();
        $this->assertArrayHasKey('hasNextPage', $fields);
        $this->assertArrayHasKey('hasPreviousPage', $fields);
        $this->assertArrayHasKey('startCursor', $fields);
        $this->assertArrayHasKey('endCursor', $fields);
        $this->assertArrayHasKey('total', $fields);
    }

    // -------------------------------------------------------------------------
    // EdgeType
    // -------------------------------------------------------------------------

    public function test_edge_type_name(): void
    {
        $edge = new EdgeType('UserEdge', Type::string());
        $this->assertSame('UserEdge', $edge->name);
    }

    public function test_edge_type_has_node_and_cursor_fields(): void
    {
        $fields = (new EdgeType('UserEdge', Type::string()))->getFields();
        $this->assertArrayHasKey('node', $fields);
        $this->assertArrayHasKey('cursor', $fields);
    }

    // -------------------------------------------------------------------------
    // ConnectionType
    // -------------------------------------------------------------------------

    public function test_connection_type_name(): void
    {
        $conn = new ConnectionType('UserConnection', Type::string());
        $this->assertSame('UserConnection', $conn->name);
    }

    public function test_connection_type_has_edges_and_page_info(): void
    {
        $fields = (new ConnectionType('UserConnection', Type::string()))->getFields();
        $this->assertArrayHasKey('edges', $fields);
        $this->assertArrayHasKey('pageInfo', $fields);
    }

    // -------------------------------------------------------------------------
    // ConnectionType::args()
    // -------------------------------------------------------------------------

    public function test_args_returns_pagination_arguments(): void
    {
        $args = ConnectionType::args();
        $this->assertArrayHasKey('first', $args);
        $this->assertArrayHasKey('after', $args);
        $this->assertArrayHasKey('last', $args);
        $this->assertArrayHasKey('before', $args);
    }

    // -------------------------------------------------------------------------
    // Cursor encode / decode
    // -------------------------------------------------------------------------

    public function test_cursor_roundtrip(): void
    {
        $encoded = ConnectionType::encodeCursor(42);
        $decoded = ConnectionType::decodeCursor($encoded);
        $this->assertSame(42, $decoded);
    }

    public function test_decode_invalid_cursor_returns_zero(): void
    {
        $this->assertSame(0, ConnectionType::decodeCursor('not-a-cursor'));
        $this->assertSame(0, ConnectionType::decodeCursor(base64_encode('wrongprefix:5')));
    }

    // -------------------------------------------------------------------------
    // ConnectionType::paginate()
    // -------------------------------------------------------------------------

    public function test_paginate_returns_connection_shape(): void
    {
        $items     = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];
        $paginator = new LengthAwarePaginator($items, 10, 2, 1);

        $builder = new class ($paginator) {
            public function __construct(private LengthAwarePaginator $p) {}
            public function paginate(int $perPage, array $cols, string $name, int $page): LengthAwarePaginator
            {
                return $this->p;
            }
        };

        $result = ConnectionType::paginate($builder, ['first' => 2]);

        $this->assertArrayHasKey('edges', $result);
        $this->assertArrayHasKey('pageInfo', $result);
        $this->assertCount(2, $result['edges']);
        $this->assertArrayHasKey('cursor', $result['edges'][0]);
        $this->assertArrayHasKey('node', $result['edges'][0]);
        $this->assertTrue($result['pageInfo']['hasNextPage']);
        $this->assertSame(10, $result['pageInfo']['total']);
    }

    public function test_paginate_with_after_cursor(): void
    {
        $paginator = new LengthAwarePaginator([['id' => 3]], 10, 1, 2);
        $builder   = new class ($paginator) {
            public function __construct(private LengthAwarePaginator $p) {}
            public function paginate(int $perPage, array $cols, string $name, int $page): LengthAwarePaginator
            {
                return $this->p;
            }
        };

        $cursor = ConnectionType::encodeCursor(1); // first page cursor
        $result = ConnectionType::paginate($builder, ['first' => 1, 'after' => $cursor]);

        $this->assertArrayHasKey('edges', $result);
        $this->assertTrue($result['pageInfo']['hasPreviousPage']);
    }

    public function test_paginate_with_before_cursor(): void
    {
        $paginator = new LengthAwarePaginator([['id' => 1]], 5, 1, 1);
        $builder   = new class ($paginator) {
            public function __construct(private LengthAwarePaginator $p) {}
            public function paginate(int $perPage, array $cols, string $name, int $page): LengthAwarePaginator
            {
                return $this->p;
            }
        };

        $cursor = ConnectionType::encodeCursor(3); // page 3
        $result = ConnectionType::paginate($builder, ['last' => 1, 'before' => $cursor]);

        $this->assertArrayHasKey('edges', $result);
    }

    public function test_paginate_empty_result(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 10, 1);
        $builder   = new class ($paginator) {
            public function __construct(private LengthAwarePaginator $p) {}
            public function paginate(int $perPage, array $cols, string $name, int $page): LengthAwarePaginator
            {
                return $this->p;
            }
        };

        $result = ConnectionType::paginate($builder, []);

        $this->assertSame([], $result['edges']);
        $this->assertNull($result['pageInfo']['startCursor']);
        $this->assertNull($result['pageInfo']['endCursor']);
    }

    // -------------------------------------------------------------------------
    // ConnectionType::simplePaginate()
    // -------------------------------------------------------------------------

    public function test_simple_paginate_returns_flat_shape(): void
    {
        $paginator = new LengthAwarePaginator([['id' => 1]], 1, 10, 1);
        $builder   = new class ($paginator) {
            public function __construct(private LengthAwarePaginator $p) {}
            public function paginate(int $perPage, array $cols, string $name, int $page): LengthAwarePaginator
            {
                return $this->p;
            }
        };

        $result = ConnectionType::simplePaginate($builder, ['per_page' => 10, 'page' => 1]);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertArrayHasKey('last_page', $result);
        $this->assertArrayHasKey('has_more_pages', $result);
    }
}
