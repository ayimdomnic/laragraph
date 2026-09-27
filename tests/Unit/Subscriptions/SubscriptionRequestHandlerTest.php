<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Subscriptions;

use Ayimdomnic\Laragraph\Contracts\QueryExecutorInterface;
use Ayimdomnic\Laragraph\Contracts\SubscriptionManagerInterface;
use Ayimdomnic\Laragraph\Http\GraphQLContext;
use Ayimdomnic\Laragraph\Subscriptions\SubscriptionRequestHandler;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Http\Request;

class SubscriptionRequestHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function test_is_subscription_operation_detects_a_subscription(): void
    {
        $handler = new SubscriptionRequestHandler(\Mockery::mock(QueryExecutorInterface::class), \Mockery::mock(SubscriptionManagerInterface::class));

        $this->assertTrue($handler->isSubscriptionOperation('subscription { postPublished { id } }', null));
        $this->assertFalse($handler->isSubscriptionOperation('query { hello }', null));
    }

    public function test_register_returns_an_error_when_subscriptions_are_disabled(): void
    {
        config(['laragraph.subscriptions.enabled' => false]);

        $handler = new SubscriptionRequestHandler(\Mockery::mock(QueryExecutorInterface::class), \Mockery::mock(SubscriptionManagerInterface::class));

        $result = $handler->register('subscription { x }', [], null, 'default', Request::create('/graphql', 'POST'));

        $this->assertArrayHasKey('errors', $result);
        $this->assertStringContainsString('disabled', $result['errors'][0]['message']);
    }

    public function test_register_returns_the_executor_errors_without_registering_a_subscriber(): void
    {
        config(['laragraph.subscriptions.enabled' => true]);

        $executor = \Mockery::mock(QueryExecutorInterface::class);
        $executor->shouldReceive('execute')->once()->andReturn(['errors' => [['message' => 'Unauthorized']]]);

        $subscriptions = \Mockery::mock(SubscriptionManagerInterface::class);
        $subscriptions->shouldNotReceive('register');

        $handler = new SubscriptionRequestHandler($executor, $subscriptions);
        $result  = $handler->register('subscription { x }', [], null, 'default', Request::create('/graphql', 'POST'));

        $this->assertSame(['errors' => [['message' => 'Unauthorized']]], $result);
    }

    public function test_register_errors_when_the_field_never_resolved_a_channel(): void
    {
        config(['laragraph.subscriptions.enabled' => true]);

        $executor = \Mockery::mock(QueryExecutorInterface::class);
        $executor->shouldReceive('execute')->once()->andReturn(['data' => ['x' => null]]);

        $handler = new SubscriptionRequestHandler($executor, \Mockery::mock(SubscriptionManagerInterface::class));
        $result  = $handler->register('subscription { x }', [], null, 'default', Request::create('/graphql', 'POST'));

        $this->assertArrayHasKey('errors', $result);
        $this->assertStringContainsString('did not resolve a channel', $result['errors'][0]['message']);
    }

    public function test_register_returns_the_subscriber_id_and_channel_on_success(): void
    {
        config(['laragraph.subscriptions.enabled' => true, 'laragraph.subscriptions.driver' => 'broadcast']);

        $executor = \Mockery::mock(QueryExecutorInterface::class);
        $executor->shouldReceive('execute')
            ->once()
            ->andReturnUsing(function (string $query, GraphQLContext $context): array {
                $context->subscriptionRegistrar->capture('posts.1');

                return ['data' => ['postPublished' => null]];
            });

        $subscriptions = \Mockery::mock(SubscriptionManagerInterface::class);
        $subscriptions->shouldReceive('register')
            ->once()
            ->with('posts.1', \Mockery::on(fn(array $record): bool => $record['query'] === 'subscription { x }'))
            ->andReturn('subscriber-123');

        $handler = new SubscriptionRequestHandler($executor, $subscriptions);
        $result  = $handler->register('subscription { x }', [], null, 'default', Request::create('/graphql', 'POST'));

        $this->assertSame('posts.1', $result['extensions']['subscription']['channel']);
        $this->assertSame('subscriber-123', $result['extensions']['subscription']['subscriberId']);
        $this->assertArrayNotHasKey('streamUrl', $result['extensions']['subscription']);
    }

    public function test_register_includes_a_stream_url_when_the_sse_driver_is_configured(): void
    {
        config(['laragraph.subscriptions.enabled' => true, 'laragraph.subscriptions.driver' => 'sse']);

        $executor = \Mockery::mock(QueryExecutorInterface::class);
        $executor->shouldReceive('execute')
            ->once()
            ->andReturnUsing(function (string $query, GraphQLContext $context): array {
                $context->subscriptionRegistrar->capture('posts.1');

                return ['data' => []];
            });

        $subscriptions = \Mockery::mock(SubscriptionManagerInterface::class);
        $subscriptions->shouldReceive('register')->once()->andReturn('subscriber-123');

        $handler = new SubscriptionRequestHandler($executor, $subscriptions);
        $result  = $handler->register('subscription { x }', [], null, 'default', Request::create('/graphql', 'POST'));

        $this->assertArrayHasKey('streamUrl', $result['extensions']['subscription']);
    }
}
