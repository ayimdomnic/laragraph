<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Events;

use Ayimdomnic\Laragraph\Events\QueryExecuting;
use Ayimdomnic\Laragraph\Tests\TestCase;

class QueryExecutingTest extends TestCase
{
    public function test_stores_query_string(): void
    {
        $event = new QueryExecuting('{ users }', [], null, 'default');

        $this->assertSame('{ users }', $event->query);
    }

    public function test_stores_variables(): void
    {
        $vars  = ['id' => 1, 'limit' => 10];
        $event = new QueryExecuting('{ users }', $vars, null, 'default');

        $this->assertSame($vars, $event->variables);
    }

    public function test_stores_operation_name(): void
    {
        $event = new QueryExecuting('query GetUsers { users }', [], 'GetUsers', 'default');

        $this->assertSame('GetUsers', $event->operationName);
    }

    public function test_operation_name_may_be_null(): void
    {
        $event = new QueryExecuting('{ users }', [], null, 'default');

        $this->assertNull($event->operationName);
    }

    public function test_stores_schema_name(): void
    {
        $event = new QueryExecuting('{ users }', [], null, 'admin');

        $this->assertSame('admin', $event->schemaName);
    }
}
