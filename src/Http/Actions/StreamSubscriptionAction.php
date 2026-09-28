<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Http\Actions;

use Ayimdomnic\Laragraph\Contracts\SsePendingQueueInterface;
use Ayimdomnic\Laragraph\Contracts\SubscriptionManagerInterface;
use Ayimdomnic\Laragraph\Subscriptions\SseEventLoop;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stream a subscriber's updates over Server-Sent Events — only available
 * when `laragraph.subscriptions.driver` is `'sse'`. Each connection
 * self-closes after `sse.max_duration` seconds; the client's `EventSource`
 * reconnects transparently. See docs/07-subscriptions.md for the capacity
 * trade-offs of this transport before relying on it for many subscribers.
 */
final readonly class StreamSubscriptionAction
{
    public function __construct(
        private SubscriptionManagerInterface $subscriptions,
        private SsePendingQueueInterface $queue,
        private SseEventLoop $loop,
    ) {}

    public function handle(string $subscriberId): StreamedResponse|JsonResponse
    {
        if (
            !config('laragraph.subscriptions.enabled', false)
            || config('laragraph.subscriptions.driver', 'broadcast') !== 'sse'
            || !$this->subscriptions->ownedByCurrentUser($subscriberId)
        ) {
            return response()->json(['errors' => [[
                'message'    => 'Subscription not found.',
                'extensions' => ['code' => 'SUBSCRIPTION_NOT_FOUND'],
            ]]], 404);
        }

        return response()->stream(
            function () use ($subscriberId): void {
                $this->loop->run($subscriberId, $this->queue);
            },
            200,
            [
                'Content-Type'      => 'text/event-stream',
                'Cache-Control'     => 'no-cache',
                'Connection'        => 'keep-alive',
                // nginx buffers proxied responses by default, which would hold
                // every frame until the connection closes — defeating the point.
                'X-Accel-Buffering' => 'no',
            ],
        );
    }
}
