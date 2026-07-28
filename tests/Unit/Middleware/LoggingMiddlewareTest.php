<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Middleware;

use Ayimdomnic\Laragraph\Middleware\LoggingMiddleware;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Log;

class LoggingMiddlewareTest extends TestCase
{
    private ResolveInfo $info;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ResolveInfo $info */
        $info = \Mockery::mock(ResolveInfo::class);
        $info->fieldName = 'loggedField';
        $this->info = $info;
    }

    public function test_calls_next_and_returns_its_result(): void
    {
        Log::shouldReceive('debug')->once();

        $mw     = new LoggingMiddleware();
        $result = $mw->handle(null, [], null, $this->info, fn () => 'logging-result');

        $this->assertSame('logging-result', $result);
    }

    public function test_log_message_contains_field_name(): void
    {
        Log::shouldReceive('debug')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'loggedField'));

        (new LoggingMiddleware())->handle(null, [], null, $this->info, fn () => null);
    }

    public function test_log_context_contains_elapsed_ms(): void
    {
        Log::shouldReceive('debug')
            ->once()
            ->withArgs(fn (string $msg, array $ctx) => isset($ctx['elapsed_ms']) && is_float($ctx['elapsed_ms']));

        (new LoggingMiddleware())->handle(null, [], null, $this->info, fn () => null);
    }

    public function test_log_context_contains_field_key(): void
    {
        Log::shouldReceive('debug')
            ->once()
            ->withArgs(fn (string $msg, array $ctx) => ($ctx['field'] ?? null) === 'loggedField');

        (new LoggingMiddleware())->handle(null, [], null, $this->info, fn () => null);
    }

    public function test_uses_configured_log_channel(): void
    {
        $this->app['config']->set('laragraph.logging.channel', 'custom-channel');

        $channelLogger = \Mockery::mock(\Psr\Log\LoggerInterface::class);
        $channelLogger->shouldReceive('debug')->once();

        Log::shouldReceive('channel')
            ->once()
            ->with('custom-channel')
            ->andReturn($channelLogger);

        (new LoggingMiddleware())->handle(null, [], null, $this->info, fn () => null);
    }

    public function test_uses_default_channel_when_config_is_null(): void
    {
        $this->app['config']->set('laragraph.logging.channel', null);

        Log::shouldReceive('debug')->once();

        (new LoggingMiddleware())->handle(null, [], null, $this->info, fn () => null);
    }

    public function test_uses_default_channel_when_config_is_empty_string(): void
    {
        $this->app['config']->set('laragraph.logging.channel', '');

        Log::shouldReceive('debug')->once();

        (new LoggingMiddleware())->handle(null, [], null, $this->info, fn () => null);
    }
}
