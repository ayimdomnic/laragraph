<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Middleware;

use Ayimdomnic\Laragraph\Middleware\FieldMiddlewareInterface;
use Ayimdomnic\Laragraph\Middleware\FieldMiddlewarePipeline;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;

// ---------------------------------------------------------------------------
// Stub middleware
// ---------------------------------------------------------------------------

/** Prepends "<tag>>" to the inner result so execution order is visible. */
class TraceMiddleware implements FieldMiddlewareInterface
{
    public function __construct(private readonly string $tag) {}

    public function handle(mixed $root, array $args, mixed $context, ResolveInfo $info, callable $next): mixed
    {
        return "{$this->tag}>" . $next();
    }
}

/** Returns a fixed value without ever calling $next. */
class ShortCircuitMiddleware implements FieldMiddlewareInterface
{
    public function handle(mixed $root, array $args, mixed $context, ResolveInfo $info, callable $next): mixed
    {
        return 'short-circuit';
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class FieldMiddlewarePipelineTest extends TestCase
{
    private ResolveInfo $info;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ResolveInfo $info */
        $info = \Mockery::mock(ResolveInfo::class);
        $info->fieldName = 'testField';
        $this->info = $info;
    }

    public function test_runs_resolver_when_middleware_list_is_empty(): void
    {
        $pipeline = new FieldMiddlewarePipeline([]);
        $result   = $pipeline->run(null, [], null, $this->info, fn () => 'resolved');

        $this->assertSame('resolved', $result);
    }

    public function test_single_middleware_wraps_resolver(): void
    {
        $pipeline = new FieldMiddlewarePipeline([new TraceMiddleware('A')]);
        $result   = $pipeline->run(null, [], null, $this->info, fn () => 'base');

        $this->assertSame('A>base', $result);
    }

    public function test_middleware_runs_in_declared_order_first_is_outermost(): void
    {
        // A is outermost: wraps B which wraps the resolver
        // resolver → 'base', B → 'B>base', A → 'A>B>base'
        $pipeline = new FieldMiddlewarePipeline([
            new TraceMiddleware('A'),
            new TraceMiddleware('B'),
        ]);
        $result = $pipeline->run(null, [], null, $this->info, fn () => 'base');

        $this->assertSame('A>B>base', $result);
    }

    public function test_short_circuit_middleware_prevents_resolver_from_running(): void
    {
        $resolverCalled = false;
        $pipeline       = new FieldMiddlewarePipeline([new ShortCircuitMiddleware()]);

        $result = $pipeline->run(null, [], null, $this->info, function () use (&$resolverCalled) {
            $resolverCalled = true;
            return 'never';
        });

        $this->assertSame('short-circuit', $result);
        $this->assertFalse($resolverCalled, 'Resolver must not run when middleware short-circuits');
    }

    public function test_resolver_receives_correct_root_args_context_and_info(): void
    {
        $root    = new \stdClass();
        $args    = ['id' => 42];
        $context = new \stdClass();

        $capturedRoot = $capturedArgs = $capturedContext = $capturedInfo = null;

        $pipeline = new FieldMiddlewarePipeline([]);
        $pipeline->run($root, $args, $context, $this->info, function ($r, $a, $c, $i) use (
            &$capturedRoot, &$capturedArgs, &$capturedContext, &$capturedInfo
        ) {
            $capturedRoot    = $r;
            $capturedArgs    = $a;
            $capturedContext = $c;
            $capturedInfo    = $i;
            return 'ok';
        });

        $this->assertSame($root, $capturedRoot);
        $this->assertSame($args, $capturedArgs);
        $this->assertSame($context, $capturedContext);
        $this->assertSame($this->info, $capturedInfo);
    }

    public function test_middleware_receives_correct_args_and_info(): void
    {
        $root    = new \stdClass();
        $args    = ['key' => 'value'];
        $context = new \stdClass();

        $capturedArgs = $capturedInfo = null;

        $recorder = new class implements FieldMiddlewareInterface {
            public mixed $capturedRoot    = null;
            public mixed $capturedArgs    = null;
            public mixed $capturedContext = null;
            public mixed $capturedInfo    = null;

            public function handle(mixed $root, array $args, mixed $context, ResolveInfo $info, callable $next): mixed
            {
                $this->capturedRoot    = $root;
                $this->capturedArgs    = $args;
                $this->capturedContext = $context;
                $this->capturedInfo    = $info;
                return $next();
            }
        };

        $pipeline = new FieldMiddlewarePipeline([$recorder]);
        $pipeline->run($root, $args, $context, $this->info, fn () => null);

        $this->assertSame($root, $recorder->capturedRoot);
        $this->assertSame($args, $recorder->capturedArgs);
        $this->assertSame($context, $recorder->capturedContext);
        $this->assertSame($this->info, $recorder->capturedInfo);
    }
}
