<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Tracing;

use Ayimdomnic\Laragraph\Tests\TestCase;
use Ayimdomnic\Laragraph\Tracing\TracingCollector;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

class TracingCollectorTest extends TestCase
{
    private function makeResolveInfo(string $parentTypeName, string $fieldName, array $path): ResolveInfo
    {
        $fieldDef = new \GraphQL\Type\Definition\FieldDefinition([
            'name' => $fieldName,
            'type' => Type::string(),
        ]);

        $parentType = new ObjectType(['name' => $parentTypeName, 'fields' => []]);

        $operation = \GraphQL\Language\Parser::parse('{ hello }')->definitions[0];

        return new ResolveInfo(
            $fieldDef,
            new \ArrayObject(),
            $parentType,
            $path,
            new \GraphQL\Type\Schema(['query' => $parentType]),
            [],
            null,
            $operation,
            [],
            $path,
        );
    }

    public function test_is_active_is_false_before_reset(): void
    {
        $collector = new TracingCollector();

        $this->assertFalse($collector->isActive());
    }

    public function test_is_active_is_true_after_reset(): void
    {
        $collector = new TracingCollector();
        $collector->reset();

        $this->assertTrue($collector->isActive());
        $this->assertNotNull($collector->startedAt());
    }

    public function test_start_returns_negative_one_when_not_reset(): void
    {
        $collector = new TracingCollector();
        $info      = $this->makeResolveInfo('Query', 'hello', ['hello']);

        $this->assertSame(-1, $collector->start($info));
        $this->assertSame([], $collector->spans());
    }

    public function test_start_and_stop_record_a_span(): void
    {
        $collector = new TracingCollector();
        $collector->reset();

        $info   = $this->makeResolveInfo('Query', 'hello', ['hello']);
        $spanId = $collector->start($info);
        $collector->stop($spanId);

        $spans = $collector->spans();
        $this->assertCount(1, $spans);
        $this->assertSame(['hello'], $spans[0]['path']);
        $this->assertSame('Query', $spans[0]['parentType']);
        $this->assertSame('hello', $spans[0]['fieldName']);
        $this->assertSame('String', $spans[0]['returnType']);
        $this->assertGreaterThanOrEqual(0, $spans[0]['startOffset']);
        $this->assertGreaterThanOrEqual(0, $spans[0]['duration']);
    }

    public function test_stop_is_a_no_op_for_unknown_span_id(): void
    {
        $collector = new TracingCollector();
        $collector->reset();

        $collector->stop(999);

        $this->assertSame([], $collector->spans());
    }

    public function test_reset_clears_previous_spans(): void
    {
        $collector = new TracingCollector();
        $collector->reset();

        $info = $this->makeResolveInfo('Query', 'hello', ['hello']);
        $collector->stop($collector->start($info));
        $this->assertCount(1, $collector->spans());

        $collector->reset();

        $this->assertSame([], $collector->spans());
    }

    public function test_elapsed_ns_is_zero_before_reset(): void
    {
        $collector = new TracingCollector();

        $this->assertSame(0, $collector->elapsedNs());
    }

    public function test_elapsed_ns_increases_after_reset(): void
    {
        $collector = new TracingCollector();
        $collector->reset();

        $this->assertGreaterThanOrEqual(0, $collector->elapsedNs());
    }

    public function test_wrap_invokes_the_original_resolver_and_returns_its_value(): void
    {
        $collector = new TracingCollector();
        $collector->reset();
        $this->app->instance(TracingCollector::class, $collector);

        $info = $this->makeResolveInfo('Query', 'hello', ['hello']);

        $wrapped = TracingCollector::wrap(fn (mixed $root, array $args, mixed $context, ResolveInfo $i) => 'resolved:' . $i->fieldName);

        $result = $wrapped(null, [], null, $info);

        $this->assertSame('resolved:hello', $result);
        $this->assertCount(1, $collector->spans());
    }

    public function test_wrap_records_a_span_even_when_the_resolver_throws(): void
    {
        $collector = new TracingCollector();
        $collector->reset();
        $this->app->instance(TracingCollector::class, $collector);

        $info = $this->makeResolveInfo('Query', 'hello', ['hello']);

        $wrapped = TracingCollector::wrap(function (): void {
            throw new \RuntimeException('boom');
        });

        try {
            $wrapped(null, [], null, $info);
            $this->fail('Expected exception was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $spans = $collector->spans();
        $this->assertCount(1, $spans);
        $this->assertNotNull($spans[0]['duration']);
    }
}
