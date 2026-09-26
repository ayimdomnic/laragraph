<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Subscriptions\SsePendingQueue;
use Ayimdomnic\Laragraph\Subscriptions\SubscriptionMessage;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Subscription;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Facades\Event;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class SsePingSubscription extends Subscription
{
    public function type(): Type
    {
        return Type::string();
    }

    public function subscribe(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'sse-pings';
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return (string) $root;
    }
}

class SseHelloQuery extends Query
{
    public function type(): Type
    {
        return Type::string();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'hello';
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class SseSubscriptionTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.subscriptions.enabled', true);
        $app['config']->set('laragraph.schemas.default', [
            'query'        => ['hello' => SseHelloQuery::class],
            'subscription' => ['ping' => SsePingSubscription::class],
        ]);
    }

    private function register(): array
    {
        return $this->graphql('subscription { ping }');
    }

    // -------------------------------------------------------------------------
    // Registration response shape
    // -------------------------------------------------------------------------

    public function test_registration_omits_stream_url_for_the_default_broadcast_driver(): void
    {
        $result = $this->register();

        $this->assertArrayNotHasKey('streamUrl', $result['extensions']['subscription']);
    }

    public function test_registration_includes_a_stream_url_for_the_sse_driver(): void
    {
        config(['laragraph.subscriptions.driver' => 'sse']);

        $result = $this->register();
        $subscriberId = $result['extensions']['subscription']['subscriberId'];

        $this->assertSame(
            url("/graphql/subscriptions/{$subscriberId}/stream"),
            $result['extensions']['subscription']['streamUrl'],
        );
    }

    // -------------------------------------------------------------------------
    // Dispatch routing
    // -------------------------------------------------------------------------

    public function test_sse_driver_pushes_to_the_pending_queue_instead_of_broadcasting(): void
    {
        Event::fake([SubscriptionMessage::class]);
        config(['laragraph.subscriptions.driver' => 'sse']);

        $result = $this->register();
        $subscriberId = $result['extensions']['subscription']['subscriberId'];

        Laragraph::broadcast('sse-pings', 'hello world');

        Event::assertNotDispatched(SubscriptionMessage::class);

        $pending = app(SsePendingQueue::class)->pop($subscriberId);
        $this->assertSame('hello world', $pending['data']['ping'] ?? null);
    }

    // -------------------------------------------------------------------------
    // Stream endpoint — access control
    // -------------------------------------------------------------------------

    public function test_stream_returns_404_when_subscriptions_are_disabled(): void
    {
        config(['laragraph.subscriptions.driver' => 'sse', 'laragraph.subscriptions.enabled' => false]);

        $this->getJson('/graphql/subscriptions/unknown/stream')->assertStatus(404);
    }

    public function test_stream_returns_404_when_driver_is_not_sse(): void
    {
        $result = $this->register();
        $subscriberId = $result['extensions']['subscription']['subscriberId'];

        $this->getJson("/graphql/subscriptions/{$subscriberId}/stream")->assertStatus(404);
    }

    public function test_stream_returns_404_for_an_unknown_subscriber(): void
    {
        config(['laragraph.subscriptions.driver' => 'sse']);

        $this->getJson('/graphql/subscriptions/does-not-exist/stream')->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Stream endpoint — actual frames
    // -------------------------------------------------------------------------

    public function test_stream_serves_a_pending_update_as_an_sse_frame(): void
    {
        config([
            'laragraph.subscriptions.driver' => 'sse',
            'laragraph.subscriptions.sse.max_duration' => 1,
            'laragraph.subscriptions.sse.poll_interval_ms' => 10,
            'laragraph.subscriptions.sse.heartbeat_seconds' => 60,
        ]);

        $result = $this->register();
        $subscriberId = $result['extensions']['subscription']['subscriberId'];

        app(SsePendingQueue::class)->push($subscriberId, ['data' => ['ping' => 'hello world']]);

        $response = $this->get("/graphql/subscriptions/{$subscriberId}/stream");

        $response->assertStreamed();
        $this->assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));

        $streamed = $response->streamedContent();

        $this->assertStringContainsString("event: next\n", $streamed);
        $this->assertStringContainsString('"ping":"hello world"', $streamed);
    }

    public function test_stream_sends_heartbeats_while_idle(): void
    {
        config([
            'laragraph.subscriptions.driver' => 'sse',
            'laragraph.subscriptions.sse.max_duration' => 1,
            'laragraph.subscriptions.sse.poll_interval_ms' => 10,
            'laragraph.subscriptions.sse.heartbeat_seconds' => 0,
        ]);

        $result = $this->register();
        $subscriberId = $result['extensions']['subscription']['subscriberId'];

        $streamed = $this->get("/graphql/subscriptions/{$subscriberId}/stream")->streamedContent();

        $this->assertStringContainsString(": heartbeat\n\n", $streamed);
    }
}
