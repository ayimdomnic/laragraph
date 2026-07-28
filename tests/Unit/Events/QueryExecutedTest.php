<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Events;

use Ayimdomnic\Laragraph\Events\QueryExecuted;
use Ayimdomnic\Laragraph\Tests\TestCase;

class QueryExecutedTest extends TestCase
{
    private function makeEvent(array $overrides = []): QueryExecuted
    {
        return new QueryExecuted(
            query:         $overrides['query']         ?? '{ ping }',
            variables:     $overrides['variables']     ?? [],
            operationName: $overrides['operationName'] ?? null,
            schemaName:    $overrides['schemaName']    ?? 'default',
            result:        $overrides['result']        ?? ['data' => ['ping' => 'pong']],
            executionMs:   $overrides['executionMs']   ?? 12.5,
            hasErrors:     $overrides['hasErrors']     ?? false,
        );
    }

    public function test_stores_all_properties(): void
    {
        $result = ['data' => ['ping' => 'pong']];
        $event  = $this->makeEvent([
            'query'         => '{ ping }',
            'variables'     => ['x' => 1],
            'operationName' => 'GetPing',
            'schemaName'    => 'api',
            'result'        => $result,
            'executionMs'   => 7.3,
            'hasErrors'     => false,
        ]);

        $this->assertSame('{ ping }', $event->query);
        $this->assertSame(['x' => 1], $event->variables);
        $this->assertSame('GetPing', $event->operationName);
        $this->assertSame('api', $event->schemaName);
        $this->assertSame($result, $event->result);
        $this->assertSame(7.3, $event->executionMs);
        $this->assertFalse($event->hasErrors);
    }

    public function test_has_errors_is_true_when_errors_present(): void
    {
        $event = $this->makeEvent(['hasErrors' => true]);

        $this->assertTrue($event->hasErrors);
    }

    public function test_has_errors_is_false_when_no_errors(): void
    {
        $event = $this->makeEvent(['hasErrors' => false]);

        $this->assertFalse($event->hasErrors);
    }

    public function test_operation_name_may_be_null(): void
    {
        $event = $this->makeEvent(['operationName' => null]);

        $this->assertNull($event->operationName);
    }
}
