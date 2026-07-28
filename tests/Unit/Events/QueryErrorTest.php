<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Events;

use Ayimdomnic\Laragraph\Events\QueryError;
use Ayimdomnic\Laragraph\Tests\TestCase;

class QueryErrorTest extends TestCase
{
    public function test_stores_query_string(): void
    {
        $event = new QueryError('{ bad }', [], null, 'default', [['message' => 'err']]);

        $this->assertSame('{ bad }', $event->query);
    }

    public function test_stores_errors_array(): void
    {
        $errors = [['message' => 'Not found.', 'path' => ['users']]];
        $event  = new QueryError('{ users }', [], null, 'default', $errors);

        $this->assertSame($errors, $event->errors);
    }

    public function test_stores_schema_name(): void
    {
        $event = new QueryError('{ x }', [], null, 'admin', [['message' => 'e']]);

        $this->assertSame('admin', $event->schemaName);
    }

    public function test_stores_variables(): void
    {
        $vars  = ['id' => 99];
        $event = new QueryError('{ x }', $vars, null, 'default', [['message' => 'e']]);

        $this->assertSame($vars, $event->variables);
    }

    public function test_operation_name_may_be_null(): void
    {
        $event = new QueryError('{ x }', [], null, 'default', [['message' => 'e']]);

        $this->assertNull($event->operationName);
    }
}
