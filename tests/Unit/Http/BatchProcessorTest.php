<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Http;

use Ayimdomnic\Laragraph\Exceptions\BatchingDisabledException;
use Ayimdomnic\Laragraph\Exceptions\BatchLimitExceededException;
use Ayimdomnic\Laragraph\Http\BatchProcessor;
use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Mockery;

class BatchProcessorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function mockLaragraph(): Laragraph
    {
        return Mockery::mock(Laragraph::class);
    }

    // -------------------------------------------------------------------------
    // Guard: batching disabled
    // -------------------------------------------------------------------------

    public function test_throws_when_batching_is_disabled(): void
    {
        config(['laragraph.batching.enabled' => false]);

        $processor = new BatchProcessor($this->mockLaragraph());

        $this->expectException(BatchingDisabledException::class);
        $processor->process([['query' => '{ hello }']]);
    }

    public function test_batching_disabled_by_default(): void
    {
        // Ensure default config produces the exception without explicit config key
        $app = $this->app;
        $app['config']->offsetUnset('laragraph.batching');

        $processor = new BatchProcessor($this->mockLaragraph());

        $this->expectException(BatchingDisabledException::class);
        $processor->process([['query' => '{ hello }']]);
    }

    // -------------------------------------------------------------------------
    // Guard: max operations
    // -------------------------------------------------------------------------

    public function test_throws_when_operation_count_exceeds_limit(): void
    {
        config(['laragraph.batching.enabled' => true, 'laragraph.batching.max_operations' => 2]);

        $processor = new BatchProcessor($this->mockLaragraph());

        $this->expectException(BatchLimitExceededException::class);
        $processor->process([
            ['query' => '{ a }'],
            ['query' => '{ b }'],
            ['query' => '{ c }'],
        ]);
    }

    public function test_default_max_is_10(): void
    {
        config(['laragraph.batching.enabled' => true]);
        // Unset max so default kicks in
        $this->app['config']->offsetUnset('laragraph.batching.max_operations');

        $processor = new BatchProcessor($this->mockLaragraph());

        $this->expectException(BatchLimitExceededException::class);
        $processor->process(array_fill(0, 11, ['query' => '{ hello }']));
    }

    public function test_exactly_at_limit_is_accepted(): void
    {
        config(['laragraph.batching.enabled' => true, 'laragraph.batching.max_operations' => 3]);

        $laragraph = $this->mockLaragraph();
        $laragraph->shouldReceive('execute')->times(3)->andReturn(['data' => []]);

        $processor = new BatchProcessor($laragraph);
        $results   = $processor->process([
            ['query' => '{ a }'],
            ['query' => '{ b }'],
            ['query' => '{ c }'],
        ]);

        $this->assertCount(3, $results);
    }

    // -------------------------------------------------------------------------
    // Result assembly
    // -------------------------------------------------------------------------

    public function test_processes_single_operation(): void
    {
        config(['laragraph.batching.enabled' => true]);

        $laragraph = $this->mockLaragraph();
        $laragraph->shouldReceive('execute')
            ->once()
            ->andReturn(['data' => ['hello' => 'world']]);

        $processor = new BatchProcessor($laragraph);
        $results   = $processor->process([['query' => '{ hello }']]);

        $this->assertCount(1, $results);
        $this->assertSame(['data' => ['hello' => 'world']], $results[0]);
    }

    public function test_preserves_operation_order(): void
    {
        config(['laragraph.batching.enabled' => true]);

        $laragraph = $this->mockLaragraph();
        $laragraph->shouldReceive('execute')
            ->twice()
            ->andReturn(
                ['data' => ['first' => 1]],
                ['data' => ['second' => 2]],
            );

        $processor = new BatchProcessor($laragraph);
        $results   = $processor->process([
            ['query' => '{ first }'],
            ['query' => '{ second }'],
        ]);

        $this->assertSame(['data' => ['first' => 1]], $results[0]);
        $this->assertSame(['data' => ['second' => 2]], $results[1]);
    }

    public function test_returns_zero_indexed_array(): void
    {
        config(['laragraph.batching.enabled' => true]);

        $laragraph = $this->mockLaragraph();
        $laragraph->shouldReceive('execute')->twice()->andReturn(['data' => []]);

        $processor = new BatchProcessor($laragraph);
        $results   = $processor->process([['query' => '{ a }'], ['query' => '{ b }']]);

        $this->assertSame([0, 1], array_keys($results));
    }

    // -------------------------------------------------------------------------
    // Argument forwarding
    // -------------------------------------------------------------------------

    public function test_passes_variables_and_operation_name(): void
    {
        config(['laragraph.batching.enabled' => true]);

        $captured = [];
        $laragraph = $this->mockLaragraph();
        $laragraph->shouldReceive('execute')
            ->once()
            ->andReturnUsing(function (
                string $query,
                mixed $context,
                array $variables,
                ?string $operationName,
                string $schemaName,
            ) use (&$captured) {
                $captured = compact('query', 'variables', 'operationName', 'schemaName');
                return ['data' => []];
            });

        $processor = new BatchProcessor($laragraph);
        $processor->process([
            ['query' => '{ hello }', 'variables' => ['name' => 'Alice'], 'operationName' => 'Hello'],
        ]);

        $this->assertSame(['name' => 'Alice'], $captured['variables']);
        $this->assertSame('Hello', $captured['operationName']);
        $this->assertSame('default', $captured['schemaName']);
    }

    public function test_missing_variables_defaults_to_empty_array(): void
    {
        config(['laragraph.batching.enabled' => true]);

        $captured = [];
        $laragraph = $this->mockLaragraph();
        $laragraph->shouldReceive('execute')
            ->once()
            ->andReturnUsing(function ($q, $ctx, array $vars) use (&$captured) {
                $captured['variables'] = $vars;
                return ['data' => []];
            });

        $processor = new BatchProcessor($laragraph);
        $processor->process([['query' => '{ hello }']]);

        $this->assertSame([], $captured['variables']);
    }

    public function test_non_array_variables_coerced_to_empty_array(): void
    {
        config(['laragraph.batching.enabled' => true]);

        $captured  = [];
        $laragraph = $this->mockLaragraph();
        $laragraph->shouldReceive('execute')
            ->once()
            ->andReturnUsing(function ($q, $ctx, array $vars) use (&$captured) {
                $captured['variables'] = $vars;
                return ['data' => [], 'errors' => [['message' => 'Syntax Error']]];
            });

        $processor = new BatchProcessor($laragraph);
        $results   = $processor->process([['query' => '{ hello }', 'variables' => 'invalid']]);

        $this->assertSame([], $captured['variables']);
        $this->assertArrayHasKey('errors', $results[0]);
    }

    public function test_missing_query_coerced_to_empty_string(): void
    {
        config(['laragraph.batching.enabled' => true]);

        $captured  = [];
        $laragraph = $this->mockLaragraph();
        $laragraph->shouldReceive('execute')
            ->once()
            ->andReturnUsing(function (string $query) use (&$captured) {
                $captured['query'] = $query;
                return ['data' => [], 'errors' => [['message' => 'Syntax Error']]];
            });

        $processor = new BatchProcessor($laragraph);
        $results   = $processor->process([[]]);

        $this->assertSame('', $captured['query']);
        $this->assertArrayHasKey('errors', $results[0]);
    }

    public function test_passes_schema_name(): void
    {
        config(['laragraph.batching.enabled' => true]);

        $captured = [];
        $laragraph = $this->mockLaragraph();
        $laragraph->shouldReceive('execute')
            ->once()
            ->andReturnUsing(function ($q, $ctx, $vars, $op, string $schema) use (&$captured) {
                $captured['schema'] = $schema;
                return ['data' => []];
            });

        $processor = new BatchProcessor($laragraph);
        $processor->process([['query' => '{ hello }']], null, 'admin');

        $this->assertSame('admin', $captured['schema']);
    }
}
