<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Middleware\FieldMiddlewareInterface;
use Ayimdomnic\Laragraph\Middleware\ThrottleMiddleware;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

/** Prepends "<tag>>" to the resolver's return value so execution order is visible. */
class MwTagMiddleware implements FieldMiddlewareInterface
{
    public static array $calls = [];

    public function __construct(private readonly string $tag = 'mw') {}

    public function handle(mixed $root, array $args, mixed $context, ResolveInfo $info, callable $next): mixed
    {
        static::$calls[] = $this->tag;
        return "{$this->tag}>" . $next();
    }
}

class MwPlainQuery extends Query
{
    public function type(): Type { return Type::string(); }
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed { return 'pong'; }
}

class MwSingleQuery extends Query
{
    public function type(): Type { return Type::string(); }
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed { return 'base'; }
    /** @return list<FieldMiddlewareInterface> */
    public function middleware(): array { return [new MwTagMiddleware('A')]; }
}

class MwDoubleQuery extends Query
{
    public function type(): Type { return Type::string(); }
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed { return 'base'; }
    /** @return list<FieldMiddlewareInterface> */
    public function middleware(): array { return [new MwTagMiddleware('A'), new MwTagMiddleware('B')]; }
}

class MwThrottledQuery extends Query
{
    public function type(): Type { return Type::string(); }
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed { return 'throttle-pong'; }
    /** @return list<FieldMiddlewareInterface> */
    public function middleware(): array { return [new ThrottleMiddleware(maxAttempts: 1, decaySeconds: 60)]; }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class FieldMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        MwTagMiddleware::$calls = [];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('laragraph.schemas.default', [
            'query' => [
                'mwPlain'    => MwPlainQuery::class,
                'mwSingle'   => MwSingleQuery::class,
                'mwDouble'   => MwDoubleQuery::class,
                'mwThrottle' => MwThrottledQuery::class,
            ],
        ]);
    }

    public function test_field_with_no_middleware_resolves_normally(): void
    {
        $result = $this->graphql('{ mwPlain }');

        $this->assertSame('pong', $result['data']['mwPlain']);
    }

    public function test_single_per_field_middleware_wraps_result(): void
    {
        $result = $this->graphql('{ mwSingle }');

        $this->assertSame('A>base', $result['data']['mwSingle']);
    }

    public function test_two_middleware_run_in_declared_order(): void
    {
        // A is outermost: A wraps (B wraps resolver)
        // resolver → 'base', B → 'B>base', A → 'A>B>base'
        $result = $this->graphql('{ mwDouble }');

        $this->assertSame('A>B>base', $result['data']['mwDouble']);
    }

    public function test_global_middleware_class_string_applies_to_all_fields(): void
    {
        $this->app['config']->set('laragraph.middleware', [MwTagMiddleware::class]);

        $result = $this->graphql('{ mwPlain }');

        // MwTagMiddleware default tag = 'mw', so result = 'mw>pong'
        $this->assertSame('mw>pong', $result['data']['mwPlain']);
    }

    public function test_global_and_per_field_middleware_combine_in_order(): void
    {
        // Global (tag='mw') is outer; field (tag='A') is inner
        // resolver → 'base', A → 'A>base', mw → 'mw>A>base'
        $this->app['config']->set('laragraph.middleware', [MwTagMiddleware::class]);

        $result = $this->graphql('{ mwSingle }');

        $this->assertSame('mw>A>base', $result['data']['mwSingle']);
    }

    public function test_throttle_middleware_blocks_after_limit_exceeded(): void
    {
        // maxAttempts = 1, so first call passes, second is throttled
        $first = $this->graphql('{ mwThrottle }');
        $this->assertSame('throttle-pong', $first['data']['mwThrottle']);

        $second = $this->graphql('{ mwThrottle }');
        $this->assertNotEmpty($second['errors'] ?? []);
        $this->assertStringContainsString('Too many requests', $second['errors'][0]['message']);
    }

    public function test_middleware_instance_is_resolved_by_container_when_string(): void
    {
        $this->app['config']->set('laragraph.middleware', [MwTagMiddleware::class]);

        // Verify the container resolves the class without error
        $result = $this->graphql('{ mwPlain }');

        $this->assertArrayNotHasKey('errors', $result);
    }
}
