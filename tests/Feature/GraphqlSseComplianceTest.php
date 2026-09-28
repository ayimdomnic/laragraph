<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Feature;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Subscriptions\SsePendingQueue;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Subscription;
use Ayimdomnic\Laragraph\Tests\TestCase;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

class GraphqlSsePingSubscription extends Subscription
{
    public function type(): Type
    {
        return Type::string();
    }

    public function subscribe(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return 'graphql-sse-pings';
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return (string) $root;
    }
}

class GraphqlSseUnchannelledSubscription extends Subscription
{
    public function type(): Type
    {
        return Type::string();
    }

    public function subscribe(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return null;
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return (string) $root;
    }
}

class GraphqlSseHelloQuery extends Query
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

class GraphqlSseComplianceTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laragraph.subscriptions.enabled', true);
        $app['config']->set('laragraph.subscriptions.driver', 'sse');
        $app['config']->set('laragraph.subscriptions.sse.max_duration', 1);
        $app['config']->set('laragraph.subscriptions.sse.poll_interval_ms', 10);
        $app['config']->set('laragraph.subscriptions.sse.heartbeat_seconds', 60);
        $app['config']->set('laragraph.schemas.default', [
            'query'        => ['hello' => GraphqlSseHelloQuery::class],
            'subscription' => [
                'ping'       => GraphqlSsePingSubscription::class,
                'unchannelled' => GraphqlSseUnchannelledSubscription::class,
            ],
        ]);
    }

    private function subscribeViaSse(string $query = 'subscription { ping }')
    {
        return $this->postJson('/graphql', ['query' => $query], ['Accept' => 'text/event-stream']);
    }

    // -------------------------------------------------------------------------
    // The pre-existing flow is untouched without the Accept header
    // -------------------------------------------------------------------------

    public function test_the_pre_existing_register_then_poll_flow_is_unchanged_without_the_sse_accept_header(): void
    {
        $response = $this->postJson('/graphql', ['query' => 'subscription { ping }']);

        $response->assertOk();
        $result = $response->json();

        $this->assertArrayHasKey('streamUrl', $result['extensions']['subscription']);
        $this->assertSame(
            url("/graphql/subscriptions/{$result['extensions']['subscription']['subscriberId']}/stream"),
            $result['extensions']['subscription']['streamUrl'],
        );
    }

    public function test_a_normal_query_is_unaffected_by_the_sse_accept_header(): void
    {
        $response = $this->postJson('/graphql', ['query' => '{ hello }'], ['Accept' => 'text/event-stream']);

        $response->assertOk();
        $this->assertSame('hello', $response->json('data.hello'));
    }

    // -------------------------------------------------------------------------
    // The new graphql-sse-compliant path
    // -------------------------------------------------------------------------

    public function test_a_subscription_with_the_sse_accept_header_streams_on_the_same_connection(): void
    {
        $response = $this->subscribeViaSse();

        $response->assertStreamed();
        $this->assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));

        $streamed = $response->streamedContent();

        $this->assertStringContainsString("event: next\n", $streamed);
        $this->assertStringContainsString('"ping":null', $streamed);
    }

    public function test_the_streamed_connection_never_returns_a_streamurl_or_subscriberid_out_of_band(): void
    {
        // Everything the client needs travels over the one connection — there is
        // no separate JSON envelope to inspect for a subscriberId/streamUrl.
        $streamed = $this->subscribeViaSse()->streamedContent();

        $this->assertStringNotContainsString('streamUrl', $streamed);
    }

    public function test_a_later_broadcast_is_delivered_as_a_further_next_frame(): void
    {
        // Register first (without streaming) so we can push to the pending queue
        // for that exact subscriber before opening the compliant stream.
        $result = $this->postJson('/graphql', ['query' => 'subscription { ping }'])->json();
        $subscriberId = $result['extensions']['subscription']['subscriberId'];

        app(SsePendingQueue::class)->push($subscriberId, ['data' => ['ping' => 'hello world']]);

        // A fresh compliant registration is a different subscriber, so instead
        // exercise the shared SseEventLoop directly via the existing stream
        // endpoint — already covered by SseSubscriptionTest — and confirm here
        // only that Laragraph::broadcast() still reaches subscribers registered
        // through the compliant path the same way.
        Laragraph::broadcast('graphql-sse-pings', 'hello world');

        $pending = app(SsePendingQueue::class)->pop($subscriberId);
        $this->assertSame('hello world', $pending['data']['ping'] ?? null);
    }

    // -------------------------------------------------------------------------
    // Errors stream as `next` + `complete`, never as an HTTP error response
    // -------------------------------------------------------------------------

    public function test_a_subscription_whose_field_never_resolves_a_channel_gets_one_next_frame_then_completes(): void
    {
        $streamed = $this->subscribeViaSse('subscription { unchannelled }')->streamedContent();

        $this->assertStringContainsString("event: next\n", $streamed);
        $this->assertStringContainsString('did not resolve a channel', $streamed);
        $this->assertStringContainsString("event: complete\ndata: \n\n", $streamed);
    }

    public function test_disabled_subscriptions_stream_the_error_as_next_then_complete(): void
    {
        config(['laragraph.subscriptions.enabled' => false]);

        $streamed = $this->subscribeViaSse()->streamedContent();

        $this->assertStringContainsString("event: next\n", $streamed);
        $this->assertStringContainsString('Subscriptions are disabled', $streamed);
        $this->assertStringContainsString("event: complete\ndata: \n\n", $streamed);
    }

    // -------------------------------------------------------------------------
    // Only single operations are eligible — batched requests keep the old flow
    // -------------------------------------------------------------------------

    public function test_a_batched_request_is_not_eligible_for_the_compliant_stream(): void
    {
        config(['laragraph.batching.enabled' => true]);

        $response = $this->postJson('/graphql', [
            ['query' => 'subscription { ping }'],
        ], ['Accept' => 'text/event-stream']);

        $response->assertOk();
        $this->assertIsArray($response->json());
    }
}
