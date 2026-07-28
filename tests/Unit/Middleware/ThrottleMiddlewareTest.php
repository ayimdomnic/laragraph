<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Middleware;

use Ayimdomnic\Laragraph\Middleware\ThrottleMiddleware;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\RateLimiter;

class ThrottleMiddlewareTest extends TestCase
{
    private ResolveInfo $info;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ResolveInfo $info */
        $info = \Mockery::mock(ResolveInfo::class);
        $info->fieldName = 'throttledField';
        $this->info = $info;
    }

    public function test_passes_request_through_when_under_limit(): void
    {
        RateLimiter::shouldReceive('tooManyAttempts')->once()->andReturn(false);
        RateLimiter::shouldReceive('hit')->once();

        $mw     = new ThrottleMiddleware(maxAttempts: 5, decaySeconds: 30);
        $result = $mw->handle(null, [], null, $this->info, fn () => 'ok');

        $this->assertSame('ok', $result);
    }

    public function test_throws_error_when_rate_limit_exceeded(): void
    {
        RateLimiter::shouldReceive('tooManyAttempts')->once()->andReturn(true);
        RateLimiter::shouldReceive('availableIn')->once()->andReturn(30);

        $this->expectException(Error::class);
        $this->expectExceptionMessageMatches('/throttledField/');

        (new ThrottleMiddleware())->handle(null, [], null, $this->info, fn () => 'never');
    }

    public function test_error_message_includes_retry_seconds(): void
    {
        RateLimiter::shouldReceive('tooManyAttempts')->once()->andReturn(true);
        RateLimiter::shouldReceive('availableIn')->once()->andReturn(42);

        try {
            (new ThrottleMiddleware())->handle(null, [], null, $this->info, fn () => null);
            $this->fail('Expected Error to be thrown');
        } catch (Error $e) {
            $this->assertStringContainsString('42s', $e->getMessage());
        }
    }

    public function test_rate_limiter_key_contains_field_name(): void
    {
        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->withArgs(fn (string $key) => str_contains($key, 'throttledField'))
            ->andReturn(false);
        RateLimiter::shouldReceive('hit')->once();

        (new ThrottleMiddleware())->handle(null, [], null, $this->info, fn () => null);
    }

    public function test_custom_decay_seconds_forwarded_to_rate_limiter(): void
    {
        RateLimiter::shouldReceive('tooManyAttempts')->once()->andReturn(false);
        RateLimiter::shouldReceive('hit')
            ->once()
            ->withArgs(fn (string $key, int $decay) => $decay === 120);

        (new ThrottleMiddleware(maxAttempts: 10, decaySeconds: 120))
            ->handle(null, [], null, $this->info, fn () => null);
    }

    public function test_resolver_is_not_called_when_throttled(): void
    {
        RateLimiter::shouldReceive('tooManyAttempts')->once()->andReturn(true);
        RateLimiter::shouldReceive('availableIn')->once()->andReturn(1);

        $called = false;
        try {
            (new ThrottleMiddleware())->handle(null, [], null, $this->info, function () use (&$called) {
                $called = true;
                return null;
            });
        } catch (Error) {
            // expected
        }

        $this->assertFalse($called);
    }
}
