<?php

declare(strict_types=1);

namespace Tests\Feature\GraphQL;

use App\Models\Post;

class PaginationTest extends GraphQLTestCase
{
    private const PAGE = 'query ($first: Int, $after: String, $last: Int, $before: String) {
        posts(first: $first, after: $after, last: $last, before: $before) {
            edges { cursor node { title } }
            pageInfo { hasNextPage hasPreviousPage startCursor endCursor total }
        }
    }';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (range(1, 25) as $i) {
            Post::factory()->published()->by($this->member)->create(['title' => "Post {$i}"]);
        }
    }

    public function test_following_end_cursor_visits_every_post_once(): void
    {
        $titles = [];
        $after = null;

        do {
            $page = $this->graphql(self::PAGE, ['first' => 10, 'after' => $after])->json('data.posts');
            $titles = [...$titles, ...array_column(array_column($page['edges'], 'node'), 'title')];
            $after = $page['pageInfo']['endCursor'];
        } while ($page['pageInfo']['hasNextPage']);

        $this->assertCount(25, $titles);
        $this->assertSame('Post 25', $titles[0]); // newest first
        $this->assertSame('Post 1', $titles[24]);
        $this->assertSame(25, $page['pageInfo']['total']);
    }

    public function test_walking_backwards_with_last_and_before(): void
    {
        $tail = $this->graphql(self::PAGE, ['last' => 5])->json('data.posts');
        $this->assertSame(['Post 5', 'Post 4', 'Post 3', 'Post 2', 'Post 1'], array_column(array_column($tail['edges'], 'node'), 'title'));
        $this->assertFalse($tail['pageInfo']['hasNextPage']);

        $previous = $this->graphql(self::PAGE, ['last' => 5, 'before' => $tail['pageInfo']['startCursor']])->json('data.posts');
        $this->assertSame('Post 10', $previous['edges'][0]['node']['title']);
    }

    public function test_page_sizes_are_capped(): void
    {
        config(['laragraph.pagination.max_per_page' => 7]);

        $this->assertCount(7, $this->graphql(self::PAGE, ['first' => 1000])->json('data.posts.edges'));
    }
}
