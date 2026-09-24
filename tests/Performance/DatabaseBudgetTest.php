<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Performance;

use Ayimdomnic\Laragraph\Tests\Support\Blog\Blog;

/**
 * SQL budgets: the number of queries an operation runs must not depend on
 * how many rows it returns. A regression here is an N+1.
 */
class DatabaseBudgetTest extends PerformanceTestCase
{
    public function test_a_paginated_list_runs_two_queries(): void
    {
        Blog::seed(organizations: 10);

        $queries = $this->queriesDuring(fn() => $this->execute(Blog::LIST));

        // select count(*) …; select … limit 50
        $this->assertCount(2, $queries, implode("\n", $queries));
    }

    public function test_nested_batched_relations_run_one_query_per_level(): void
    {
        Blog::seed(organizations: 20);

        $queries = $this->queriesDuring(fn() => $this->execute(Blog::NESTED));

        // organizations; author counts (custom DataLoader); authors; posts; post authors
        $this->assertCount(5, $queries, implode("\n", $queries));
    }

    public function test_the_query_count_does_not_grow_with_the_data(): void
    {
        Blog::seed(organizations: 2, authors: 2, posts: 2);
        $small = $this->queriesDuring(fn() => $this->execute(Blog::NESTED));

        Blog::seed(organizations: 30, authors: 8, posts: 6);
        $large = $this->queriesDuring(fn() => $this->execute(Blog::NESTED));

        $this->assertCount(count($small), $large);
    }

    public function test_a_validated_mutation_runs_only_its_own_queries(): void
    {
        Blog::seed(organizations: 1);

        $queries = $this->queriesDuring(fn() => $this->execute(Blog::MUTATION));

        // select the organization; update it
        $this->assertCount(2, $queries, implode("\n", $queries));
    }
}
