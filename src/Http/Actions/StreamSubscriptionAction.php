<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Http\Actions;

use Ayimdomnic\Laragraph\Contracts\SsePendingQueueInterface;
use Ayimdomnic\Laragraph\Contracts\SubscriptionManagerInterface;
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

        $queue            = $this->queue;
        $maxDuration      = (int) config('laragraph.subscriptions.sse.max_duration', 30);
        $pollIntervalUs   = (int) config('laragraph.subscriptions.sse.poll_interval_ms', 500) * 1000;
        $heartbeatSeconds = (int) config('laragraph.subscriptions.sse.heartbeat_seconds', 15);

        return response()->stream(function () use ($subscriberId, $queue, $maxDuration, $pollIntervalUs, $heartbeatSeconds): void {
            $deadline      = microtime(true) + $maxDuration;
            $lastHeartbeat = microtime(true);

            while (microtime(true) < $deadline) {
                if (connection_aborted()) {
                    break;
                }

                $payload = $queue->pop($subscriberId);

                if ($payload !== null) {
                    echo "event: next\n";
                    echo 'data: ' . json_encode($payload) . "\n\n";
                    $lastHeartbeat = microtime(true);
                } elseif (microtime(true) - $lastHeartbeat >= $heartbeatSeconds) {
                    echo ": heartbeat\n\n";
                    $lastHeartbeat = microtime(true);
                } else {
                    usleep($pollIntervalUs);

                    continue;
                }

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'Connection'        => 'keep-alive',
            // nginx buffers proxied responses by default, which would hold
            // every frame until the connection closes — defeating the point.
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
