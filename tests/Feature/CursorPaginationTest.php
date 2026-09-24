<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Pagination\ConnectionType;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PagedNumber extends Model
{
    protected $table = 'paged_numbers';

    public $timestamps = false;
}

class PagedNumbersQuery extends Query
{
    public function type(): Type
    {
        return new ConnectionType('PagedNumberConnection', Type::int());
    }

    public function args(): array
    {
        return ConnectionType::args();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $connection = ConnectionType::paginate(PagedNumber::query()->orderBy('n'), $args);

        foreach ($connection['edges'] as &$edge) {
            $edge['node'] = $edge['node']->n;
        }

        return $connection;
    }
}

/** An object that only knows Laravel's paginate() signature. */
class PaginateOnlySource
{
    public function paginate(int $perPage, array $columns, string $pageName, int $page): LengthAwarePaginator
    {
        $all = range(1, 25);

        return new LengthAwarePaginator(array_slice($all, ($page - 1) * $perPage, $perPage), count($all), $perPage, $page);
    }
}

class CursorPaginationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('laragraph.schemas.default.query', ['numbers' => PagedNumbersQuery::class]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('paged_numbers', fn($table) => $table->integer('n'));
        DB::table('paged_numbers')->insert(array_map(fn(int $n): array => ['n' => $n], range(1, 25)));
    }

    /**
     * @return array{nodes: list<int>, pageInfo: array<string, mixed>}
     */
    private function page(string $arguments): array
    {
        $result = $this->graphql("{ numbers({$arguments}) { edges { node cursor } pageInfo { hasNextPage hasPreviousPage startCursor endCursor total } } }");
        $this->assertArrayNotHasKey('errors', $result, json_encode($result));

        return [
            'nodes'    => array_column($result['data']['numbers']['edges'], 'node'),
            'pageInfo' => $result['data']['numbers']['pageInfo'],
        ];
    }

    public function test_walking_forward_visits_every_item_exactly_once(): void
    {
        $seen   = [];
        $cursor = null;

        do {
            $page   = $this->page('first: 10' . ($cursor !== null ? ", after: \"{$cursor}\"" : ''));
            $seen   = [...$seen, ...$page['nodes']];
            $cursor = $page['pageInfo']['endCursor'];
        } while ($page['pageInfo']['hasNextPage']);

        $this->assertSame(range(1, 25), $seen);
        $this->assertSame(25, $page['pageInfo']['total']);
    }

    public function test_page_size_may_change_between_requests(): void
    {
        $first = $this->page('first: 3');
        $next  = $this->page("first: 5, after: \"{$first['pageInfo']['endCursor']}\"");

        $this->assertSame([4, 5, 6, 7, 8], $next['nodes']);
        $this->assertTrue($next['pageInfo']['hasPreviousPage']);
    }

    public function test_walking_backward_with_last_and_before(): void
    {
        $tail = $this->page('last: 10');
        $this->assertSame(range(16, 25), $tail['nodes']);
        $this->assertFalse($tail['pageInfo']['hasNextPage']);
        $this->assertTrue($tail['pageInfo']['hasPreviousPage']);

        $previous = $this->page("last: 10, before: \"{$tail['pageInfo']['startCursor']}\"");
        $this->assertSame(range(6, 15), $previous['nodes']);

        $head = $this->page("last: 10, before: \"{$previous['pageInfo']['startCursor']}\"");
        $this->assertSame(range(1, 5), $head['nodes']);
        $this->assertFalse($head['pageInfo']['hasPreviousPage']);
    }

    public function test_after_and_before_bound_a_window(): void
    {
        $after  = ConnectionType::encodeCursor(5);
        $before = ConnectionType::encodeCursor(9);

        $this->assertSame([6, 7, 8], $this->page("first: 10, after: \"{$after}\", before: \"{$before}\"")['nodes']);
    }

    public function test_page_size_defaults_and_is_capped(): void
    {
        config(['laragraph.pagination.per_page' => 4, 'laragraph.pagination.max_per_page' => 7]);

        $this->assertCount(4, $this->page('after: ""')['nodes']);
        $this->assertCount(7, $this->page('first: 1000000')['nodes']);
        $this->assertCount(7, $this->page('last: 1000000')['nodes']);
    }

    public function test_the_cap_can_be_removed(): void
    {
        config(['laragraph.pagination.max_per_page' => null]);

        $this->assertCount(25, $this->page('first: 1000')['nodes']);
    }

    public function test_negative_sizes_are_rejected(): void
    {
        $result = $this->graphql('{ numbers(first: -1) { edges { node } } }');

        $this->assertSame('`first` must not be negative.', $result['errors'][0]['message'] ?? null);
    }

    public function test_paginate_only_sources_walk_forward_correctly(): void
    {
        $seen = [];
        $args = ['first' => 10];

        do {
            $page = ConnectionType::paginate(new PaginateOnlySource(), $args);
            $seen = [...$seen, ...array_column($page['edges'], 'node')];
            $args = ['first' => 10, 'after' => $page['pageInfo']['endCursor']];
        } while ($page['pageInfo']['hasNextPage']);

        $this->assertSame(range(1, 25), $seen);
    }

    public function test_paginate_only_sources_support_last_before(): void
    {
        $page = ConnectionType::paginate(new PaginateOnlySource(), ['last' => 5, 'before' => ConnectionType::encodeCursor(11)]);

        $this->assertSame([6, 7, 8, 9, 10], array_column($page['edges'], 'node'));
    }

    public function test_simple_paginate_is_capped_too(): void
    {
        config(['laragraph.pagination.max_per_page' => 5]);

        $this->assertSame(5, ConnectionType::simplePaginate(PagedNumber::query(), ['per_page' => 500])['per_page']);
    }
}
